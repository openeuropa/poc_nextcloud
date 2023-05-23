<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Tracking;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\poc_nextcloud\Job\Collector\JobCollectorInterface;
use Drupal\poc_nextcloud\Job\DependentPostDeleteJob;
use Drupal\poc_nextcloud\Job\DependentPreDeleteJob;
use Drupal\poc_nextcloud\Job\TrackingTableOpJob;
use Drupal\poc_nextcloud\Tracking\RecordSubmit\TrackingRecordSubmitInterface;

/**
 * Object to control a database table to track pending writes to Nextcloud.
 *
 * @template RecordType as array
 */
class TrackingTable {

  /**
   * The 'fields' part of the table schema.
   *
   * @var array[]
   */
  private array $schemaFields = [
    'pending_hash' => [
      'description' => 'Hash of the values to be written to the remote, or NULL if object should not exist in the remote.',
      'type' => 'varchar',
      'length' => 254,
    ],
    'remote_hash' => [
      'description' => 'Hash of the values currently in the remote, or NULL if object does not exist in the remote.',
      'type' => 'varchar',
      'length' => 254,
    ],
  ];

  /**
   * Primary key. Array keys are the same as the values.
   *
   * @var string[]
   */
  private array $primaryKey = [];

  /**
   * Local part of the primary key. Array keys are the same as the values.
   *
   * @var string[]
   */
  private array $localKey = [];

  /**
   * Remote part of the primary key. Array keys are the same as the values.
   *
   * @var string[]
   */
  private array $remoteKey = [];

  /**
   * Field names of data fields. Array keys are the same as the values.
   *
   * @var string[]
   */
  private array $dataFields = [];

  /**
   * Parent table relationships, by alias.
   *
   * @var \Drupal\poc_nextcloud\Tracking\TrackingTableRelationship[]
   */
  private array $relationships = [];

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   * @param string $tableName
   *   Table name.
   */
  public function __construct(
    private Connection $connection,
    private string $tableName,
  ) {}

  /**
   * Adds a column that is part of the local primary key.
   *
   * Usually this is an entity id in Drupal.
   *
   * @param string $key
   *   Column name.
   * @param array $schema
   *   Table schema.
   *
   * @return $this
   */
  public function addLocalPrimaryField(string $key, array $schema): static {
    $this->localKey[$key] = $key;
    $this->primaryKey[$key] = $key;
    $this->schemaFields[$key] = $schema;
    return $this;
  }

  /**
   * Adds a column that is part of the remote primary key.
   *
   * This is usually an object id in Nextcloud.
   *
   * @param string $key
   *   Column name.
   * @param array $schema
   *   Field schema.
   *
   * @return $this
   */
  public function addRemotePrimaryField(string $key, array $schema): static {
    $this->remoteKey[$key] = $key;
    $this->primaryKey[$key] = $key;
    $this->schemaFields[$key] = $schema;
    return $this;
  }

  /**
   * Adds a parent table relationship.
   *
   * @param string $alias
   *   Alias for the relationship.
   * @param \Drupal\poc_nextcloud\Tracking\TrackingTableRelationship $relationship
   *   Relationship.
   *
   * @return $this
   */
  public function addParentTableRelationship(string $alias, TrackingTableRelationship $relationship): static {
    $this->relationships[$alias] = $relationship;
    return $this;
  }

  /**
   * Adds a data field.
   *
   * @param string $key
   *   Column name.
   * @param array $schema
   *   Field schema.
   *
   * @return $this
   */
  public function addDataField(string $key, array $schema): static {
    $this->dataFields[$key] = $key;
    $this->schemaFields[$key] = $schema;
    return $this;
  }

  /**
   * Gets the table name.
   *
   * @return string
   *   Table name.
   */
  public function getTableName(): string {
    return $this->tableName;
  }

  /**
   * Gets a table schema for hook_schema().
   *
   * @return array
   *   Table schema.
   */
  public function getTableSchema(): array {
    return [
      'fields' => $this->schemaFields,
      'primary key' => array_keys($this->primaryKey),
    ];
  }

  /**
   * Sets the should-be value for records in Nextcloud.
   *
   * @param array $values
   *   Values that _should_ be in Nextcloud.
   *
   * @psalm-param RecordType $values
   */
  public function queueWrite(array $values): void {
    // Prevent calling code from overwriting the pending operation value.
    unset($values['pending_operation']);

    if ($this->remoteKey) {
      // Delete tracking records with wrong remote key, where the remote object
      // has not been created yet.
      $q = $this->connection->delete($this->tableName);
      $this->filterQuery($q, $values, TRUE);
      $q->condition('pending_operation', Op::INSERT);
      $q->execute();

      // Queue removal of existing objects with wrong remote key.
      $q = $this->connection->update($this->tableName);
      $this->filterQuery($q, $values, TRUE);
      // Don't update records that are already marked for deletion.
      $q->condition('pending_operation', Op::DELETE, '!=');
      $q->fields(['pending_operation' => Op::DELETE]);
      $q->execute();
    }

    // Update existing records with correct remote key.
    $values_to_update = array_diff_key(
      $values,
      $this->primaryKey,
    );

    if (!$this->dataFields) {
      if ($values_to_update) {
        throw new \InvalidArgumentException('Values to update must be empty on a table without data fields.');
      }
      // This is a special case, where the entire table consists of the primary
      // key.
      // @todo Make this a configuration in the object itself.
      $q = $this->connection->select($this->tableName, 't');
      $this->filterQuery($q, $values);
      // @todo Remove this condition once we implement locking.
      //   There should be no pending deletes, if the table was properly locked.
      $q->condition('pending_operation', Op::DELETE, '!=');
      $n_existing = $q->countQuery()->execute()->fetchField();
      if ($n_existing) {
        return;
      }
    }
    else {
      if (!$values_to_update) {
        throw new \InvalidArgumentException('Values to update must not be empty on a table with data fields.');
      }

      // Update records that already have a pending insert or update.
      $q = $this->connection->update($this->tableName);
      $this->filterQuery($q, $values);
      $q->condition('pending_operation', [
        Op::UPDATE,
        Op::INSERT,
      ], 'IN');
      $q->fields($values_to_update);
      $n_updated = $q->execute();
      if ($n_updated) {
        return;
      }

      // Update records that don't have a pending operation.
      // @todo Introduce a checksum to avoid marking records for update that have
      //   no change.
      $q = $this->connection->update($this->tableName);
      $this->filterQuery($q, $values);
      $q = clone $q;
      $q->condition('pending_operation', Op::UNCHANGED);
      $q->fields(['pending_operation' => Op::UPDATE] + $values_to_update);
      $n_updated = $q->execute();
      if ($n_updated) {
        return;
      }
    }

    // Insert new record.
    $values_to_insert = $values;
    $values_to_insert['pending_operation'] = Op::INSERT;

    try {
      $this->connection->insert($this->tableName)
        ->fields($values_to_insert)
        ->execute();
    }
    catch (\Exception $e) {
      // Wrap into unhandled exception, because this is a programming error.
      // @todo Add details to the message.
      throw new \InvalidArgumentException("Failed to insert values.", 0, $e);
    }
  }

  /**
   * Marks a record or a range of records for deletion.
   *
   * @param array $condition
   *   Condition to determine which records to mark for deletion.
   *
   * @psalm-param RecordType $condition
   */
  public function queueDelete(array $condition): void {
    // Delete matching records that are marked for insert.
    $q = $this->connection->delete($this->tableName);
    $this->filterQueryPartial($q, $condition);
    $q->condition('pending_operation', Op::INSERT);
    $q->execute();

    // Mark remaining matching records for deletion.
    $q = $this->connection->update($this->tableName);
    $this->filterQueryPartial($q, $condition);
    // Don't update records that are already marked for deletion.
    $q->condition('pending_operation', Op::DELETE, '!=');
    $q->fields(['pending_operation' => Op::DELETE]);
    $q->execute();
  }

  /**
   * Collects the jobs to write the tracked changes to the remote end.
   *
   * This has a few additional parameters than the similar interface method.
   *
   * @param \Drupal\poc_nextcloud\Job\Collector\JobCollectorInterface $collector
   *   Job collector.
   * @param \Drupal\poc_nextcloud\Tracking\RecordSubmit\TrackingRecordSubmitInterface $submit
   *   Submit handler that writes the changes to Nextcloud.
   */
  public function collectJobs(JobCollectorInterface $collector, TrackingRecordSubmitInterface $submit): void {
    // @todo More sophisticated way to determine order.
    $dependency_level ??= ($this->relationships ? 10 : 0);
    foreach ($this->relationships as $alias => $relationship) {
      if ($relationship->isAutoDelete()) {
        $collector->addJob(FALSE, $dependency_level, new DependentPostDeleteJob(
          $this,
          $relationship,
        ));
      }
      else {
        $collector->addJob(FALSE, -$dependency_level, new DependentPreDeleteJob(
          $this,
          $submit,
          $alias,
        ));
      }
    }
    foreach ([[Op::DELETE], [Op::INSERT, Op::UPDATE]] as $phase => $ops) {
      foreach ($ops as $op) {
        $collector->addJob((bool) $phase, $dependency_level, new TrackingTableOpJob(
          $this,
          $submit,
          $op,
        ));
      }
    }
  }

  /**
   * Reports that a record has been written to Nextcloud and is now "synced".
   *
   * @param array $condition
   *   Array with at least all values for the primary key.
   *   This can simply be the old version of the record.
   * @param array $values_to_update
   *   Values of the record that should be updated.
   *   This should not contain the 'pending_operation' key.
   */
  public function reportRemoteValues(array $condition, array $values_to_update): void {
    $values_to_update = array_diff_assoc($values_to_update, $condition);
    $values_to_update['pending_operation'] = Op::UNCHANGED;
    $q = $this->connection->update($this->tableName);
    $q->condition('pending_operation', [Op::INSERT, Op::UPDATE], 'IN');
    $this->filterQuery($q, $condition);
    $q->fields($values_to_update);
    $n_updated = $q->execute();
    if (!$n_updated) {
      // @todo Further investigation!
      throw new \RuntimeException('The record that was supposedly synced was not found.');
    }
  }

  /**
   * Reports that a range of objects no longer exist in Nextcloud.
   *
   * This is typically called when objects were deleted automatically following
   * the deletion of a parent object.
   *
   * @param array $condition
   *   Condition to determine which records no longer exist.
   */
  public function reportRemoteAbsence(array $condition): void {
    // Forget tracking records, that were already marked for deletion.
    $q = $this->connection->delete($this->tableName);
    $q->condition('pending_operation', Op::DELETE);
    $this->filterQueryPartial($q, $condition);
    $q->execute();
    // Mark for re-insert, if it was not marked for deletion.
    // This can happen if the target has to be recreated with new keys.
    $q = $this->connection->update($this->tableName);
    $q->condition('pending_operation', [
      Op::INSERT,
      Op::DELETE,
    ], 'NOT IN');
    $this->filterQueryPartial($q, $condition);
  }

  /**
   * Filters a database query by (in)complete primary key values.
   *
   * @param \Drupal\Core\Database\Query\ConditionInterface $query
   *   Database query or condition.
   * @param array $values
   *   An array with values for some of the local primary key columns.
   */
  private function filterQueryPartial(ConditionInterface $query, array $values): void {
    self::addQueryConditions($query, $this->localKey, $values, FALSE);
    foreach (array_intersect_key($values, $this->localKey) as $k => $v) {
      $query->condition($k, $v);
    }
  }

  /**
   * Filters a database query by complete primary key values.
   *
   * @param \Drupal\Core\Database\Query\ConditionInterface $query
   *   Database query or condition.
   * @param array $values
   *   A full record, or an array with values for at least all the primary key
   *   columns.
   * @param string|null $negate_remote_key
   *   TRUE to negate the condition for the remote key.
   */
  private function filterQuery(ConditionInterface $query, array $values, bool $negate_remote_key = FALSE): void {
    self::addQueryConditions($query, $this->localKey, $values, TRUE);
    self::addQueryConditions($query, $this->remoteKey, $values, TRUE, $negate_remote_key);
  }

  /**
   * Adds conditions to a database query.
   *
   * @param \Drupal\Core\Database\Query\ConditionInterface $condition
   *   Query or condition to modify.
   * @param array $keys
   *   Map of keys.
   * @param array $values
   *   Associative array of values.
   * @param bool $complete
   *   TRUE if all keys are required.
   * @param bool $negate
   *   TRUE to negate the condition group.
   */
  private static function addQueryConditions(ConditionInterface $condition, array $keys, array $values, bool $complete, bool $negate = FALSE): void {
    if ($complete) {
      $missing_keys = array_diff_key($keys, $values);
      if ($missing_keys) {
        throw new \InvalidArgumentException(
          sprintf(
            "Missing keys: %s.",
            implode(', ', $keys)),
        );
      }
    }
    assert($condition->conditions()['#conjunction'] === 'AND', 'Condition groups with OR are not supported.');
    $condition_values = array_intersect_key($values, $keys);
    if ($negate) {
      $subcondition = $condition->orConditionGroup();
      foreach ($condition_values as $key => $value) {
        $subcondition->condition($key, $value, '!=');
      }
      if (array_keys($subcondition->conditions()) === ['#conjunction']) {
        throw new \InvalidArgumentException('Subcondition is empty.');
      }
      $condition->condition($subcondition);
    }
    else {
      foreach ($condition_values as $key => $value) {
        $condition->condition($key, $value, '=');
      }
    }
  }

  /**
   * Builds a select query to find orphaned foreign key combinations.
   *
   * @param \Drupal\poc_nextcloud\Tracking\TrackingTableRelationship $relationship
   *   Relationship by which to determine what is an orphan.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   Select query.
   */
  public function selectOrphanedDependentKeyCombos(TrackingTableRelationship $relationship): SelectInterface {
    // Find orphaned tuples of key values.
    // See https://stackoverflow.com/a/36694478/246724
    $q = $this->connection->select($this->tableName, 't');
    $subquery = $this->connection
      ->select($relationship->getSourceTableName(), 's');
    foreach ($relationship->getJoinKeys() as $local_key => $source_key) {
      $q->addField('t', $local_key);
      $q->groupBy('t.' . $local_key);
      $subquery->where("t.$local_key = s.$source_key");
      $subquery->addField('s', $source_key);
    }
    $q->havingNotExists($subquery);
    $q->condition('t.pending_operation', Op::INSERT, '!=');
    return $q;
  }

  /**
   * Builds a select query for the tracking table.
   *
   * @param string $alias
   *   Alias to give to the table.
   *   Usually this can remain the default 't'.
   *   This parameter exists mainly so that calling code makes more sense.
   * @param \Drupal\poc_nextcloud\Tracking\TrackingTableRelationship[] $relationships
   *   Relationships to parent tables, by alias.
   * @param bool $require_relationships
   *   TRUE to only fetch records with matching data for all relationships.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   Select query.
   */
  public function select(string $alias = 't', array $relationships = [], bool $require_relationships = TRUE): SelectInterface {
    $q = $this->connection->select($this->tableName, $alias);
    $q->fields($alias);
    foreach ($this->relationships as $source_alias => $relationship) {
      $join_condition = $q->andConditionGroup();
      foreach ($relationship->getJoinKeys() as $local_key => $source_key) {
        $join_condition->where("$alias.$local_key = $source_alias.$source_key");
      }
      $join_condition->condition("$source_alias.pending_operation", Op::INSERT, '!=');
      $q->addJoin(
        $require_relationships ? 'INNER' : 'LEFT',
        $relationship->getSourceTableName(),
        $source_alias,
        $join_condition,
      );
      $q->fields($source_alias, $relationship->getSourceFields());
    }
    return $q;
  }

}
