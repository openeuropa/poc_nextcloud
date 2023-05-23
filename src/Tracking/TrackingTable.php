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
   * Fields that get their value from the remote.
   *
   * E.g. auto-increment ids or uuids generated in Nextcloud.
   *
   * Array keys are the same as the values.
   *
   * @var string[]
   */
  private array $remoteControlledFields = [];

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
   * Adds a field that gets its value from the remote.
   *
   * @param string $key
   *   Column name.
   * @param array $schema
   *   Field schema.
   *
   * @return $this
   */
  public function addRemoteControlledField(string $key, array $schema): static {
    $this->remoteControlledFields[$key] = $key;
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
    // Prevent calling code from overwriting reserved fields.
    unset($values['pending_hash'], $values['remote_hash']);

    if ($this->remoteKey) {
      // Delete tracking records with wrong remote key, where the remote object
      // has not been created yet.
      $q = $this->connection->delete($this->tableName);
      $this->filterQuery($q, $values, TRUE);
      $q->isNull('remote_hash');
      $q->execute();

      // Queue removal of existing objects with wrong remote key.
      $q = $this->connection->update($this->tableName);
      $this->filterQuery($q, $values, TRUE);
      // Don't produce records that have NULL for both hashes.
      // @todo Remove this condition once a lock is introduced.
      $q->isNotNull('remote_hash');
      $q->fields(['pending_hash' => NULL]);
      $q->execute();
    }

    // Update existing records with correct remote key.
    $values_to_update = array_intersect_key($values, $this->dataFields);
    $missing_keys = array_diff_key($this->dataFields, $values);
    if ($missing_keys) {
      throw new \InvalidArgumentException(sprintf(
        'Missing data fields %s.',
        implode(', ', array_keys($missing_keys)),
      ));
    }
    $hash = $this->hashValues($values_to_update);
    $values_to_update['pending_hash'] = $hash;

    // Update existing records.
    $q = $this->connection->update($this->tableName);
    $this->filterQuery($q, $values);
    $q->fields($values_to_update);
    $n_updated = $q->execute();
    if ($n_updated) {
      return;
    }

    // Insert new record.
    $values_to_insert = $values;
    $values_to_insert['pending_hash'] = $hash;
    $values_to_insert['remote_hash'] = NULL;

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
    self::addQueryConditions($q, $this->localKey, $condition, FALSE);
    $this->filterQueryPartial($q, $condition);
    $q->isNull('remote_hash');
    $q->execute();

    // Mark remaining matching records for deletion.
    $q = $this->connection->update($this->tableName);
    self::addQueryConditions($q, $this->localKey, $condition, FALSE);
    // Do not create records with NULL for both hashes.
    $q->isNotNull('remote_hash');
    $q->fields(['pending_hash' => NULL]);
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
   *
   * @see \Drupal\poc_nextcloud\Job\Provider\JobProviderInterface::collectJobs()
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
   * Reports values currently in the remote.
   *
   * This is called in the following scenarios:
   * - A remote object was just updated or created.
   * - A checkup script finds a remote object.
   *
   * @param array $values
   *   Values of the remote object.
   */
  public function reportRemoteValues(array $values): void {
    $hash = $this->hashValues($values);
    $q = $this->connection->update($this->tableName);
    $this->filterQuery($q, $values);
    $values_to_update = ['remote_hash' => $hash];
    if ($this->remoteControlledFields) {
      $missing_keys = array_diff_key($this->remoteControlledFields, $values);
      if ($missing_keys) {
        throw new \InvalidArgumentException(sprintf(
          "Missing keys: %s.",
          implode(', ', $missing_keys),
        ));
      }
      foreach ($this->remoteControlledFields as $k) {
        $values_to_update[$k] = $values[$k];
      }
    }
    $q->fields($values_to_update);
    $n_updated = $q->execute();
    if ($n_updated) {
      // Existing records were updated.
      return;
    }
    // Create new tracking record.
    // @todo Does this case ever occur?
    $q = $this->connection->insert($this->tableName);
    $values['pending_hash'] = NULL;
    $values['remote_hash'] = $hash;
    $q->fields($values);
    $q->execute();
  }

  /**
   * Reports that no objects exist in Nextcloud that match a given condition.
   *
   * There are different scenarios when this is called:
   * - A specific object tracked with this table was deleted.
   * - A parent object no longer exists, and we know that Nextcloud
   *   automatically removes all dependent objects.
   *   E.g. a user no longer exists in Nextcloud, this means all the group
   *   memberships of that user no longer exist.
   * - A checkup finds that an object is missing.
   *
   * @param array $condition
   *   Condition to specify which objects no longer exist.
   */
  public function reportRemoteAbsence(array $condition): void {
    // Forget tracking records that were already marked for deletion.
    $q = $this->connection->delete($this->tableName);
    $this->filterQueryPartial($q, $condition);
    $q->isNull('pending_hash');
    $q->execute();
    // Update records that are not marked for deletion.
    // Mark for re-insert, if it was not marked for deletion.
    // This can happen if the target has to be recreated with new keys.
    $q = $this->connection->update($this->tableName);
    $this->filterQueryPartial($q, $condition);
    // Avoid creating records where both hashes are NULL.
    // @todo Remove this condition once locking has been added.
    $q->isNotNull('pending_hash');
    $q->fields(['remote_hash' => NULL]);
    $q->execute();
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
    $subquery = $this->connection->select($relationship->getSourceTableName(), 's');
    foreach ($relationship->getJoinKeys() as $local_key => $source_key) {
      $q->addField('t', $local_key);
      $q->groupBy('t.' . $local_key);
      $subquery->where("t.$local_key = s.$source_key");
      $subquery->addField('s', $source_key);
    }
    // Find records where the remote object is tracked as existing, but the
    // parent remote object is not tracked as existing.
    // It is assumed that the remote object has been deleted automatically as a
    // side effect of deleting the parent remote object. The tracking data just
    // needs to be updated to reflect this change.
    $subquery->isNotNull('s.remote_hash');
    $q->havingNotExists($subquery);
    $q->isNotNull('t.remote_hash');
    return $q;
  }

  /**
   * Builds a select query for the tracking table.
   *
   * @param string $alias
   *   Alias to give to the table.
   *   Usually this can remain the default 't'.
   *   This parameter exists mainly so that calling code makes more sense.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   Select query.
   */
  public function select(string $alias = 't'): SelectInterface {
    $q = $this->connection->select($this->tableName, $alias);
    $q->fields($alias);
    foreach ($this->relationships as $source_alias => $relationship) {
      $join_condition = $q->andConditionGroup();
      foreach ($relationship->getJoinKeys() as $local_key => $source_key) {
        $join_condition->where("$alias.$local_key = $source_alias.$source_key");
      }
      // Find objects where the parent remote object actually exists.
      $join_condition->isNotNull("$source_alias.remote_hash");
      $q->innerJoin(
        $relationship->getSourceTableName(),
        $source_alias,
        $join_condition,
      );
      $q->fields($source_alias, $relationship->getSourceFields());
    }
    return $q;
  }

  /**
   * Generates a hash of a set of values.
   *
   * @param array $values
   *   Values to hash.
   *   Any keys that are part of the primary key will be removed.
   *
   * @return string|int
   *   Hash for the given values.
   */
  private function hashValues(array $values): string|int {
    if (!$this->dataFields) {
      return 1;
    }
    $values = array_intersect_key($values, $this->dataFields);
    $missing_keys = array_diff_key($this->dataFields, $values);
    if ($missing_keys) {
      throw new \InvalidArgumentException(sprintf("Missing keys: %s", implode(', ', $missing_keys)));
    }
    foreach ($values as &$value) {
      if (is_string($value) && (string) (int) $value === $value) {
        $value = (int) $value;
      }
    }
    // Different order should produce same hash.
    ksort($values);
    $hash = hash('sha256', serialize($values));
    return $hash;
  }

}
