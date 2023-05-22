<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Tracking;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Database\Query\SelectInterface;

/**
 * Object to control a database table to track pending writes to Nextcloud.
 *
 * @template RecordType as array
 */
class TrackingTable {

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   * @param string $tableName
   *   Table name.
   * @param string[] $localKey
   *   Local part of the primary key.
   * @param array $remoteKey
   *   Remote part of the primary key.
   * @param bool $hasDataFields
   *   TRUE, if the table has any fields beyond the primary key.
   */
  public function __construct(
    private Connection $connection,
    private string $tableName,
    private array $localKey,
    private array $remoteKey,
    private bool $hasDataFields,
  ) {}

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
   * Gets the primary key.
   *
   * @return string[]
   *   Column names of the primary key.
   */
  public function getPrimaryKey(): array {
    return [...$this->localKey, ...$this->remoteKey];
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
      $this->filterQuery($q, $values, '!=');
      $q->condition('pending_operation', Op::INSERT);
      $q->execute();

      // Queue removal of existing objects with wrong remote key.
      $q = $this->connection->update($this->tableName);
      $this->filterQuery($q, $values, '!=');
      // Don't update records that are already marked for deletion.
      $q->condition('pending_operation', Op::DELETE, '!=');
      $q->fields(['pending_operation' => Op::DELETE]);
      $q->execute();
    }

    // Update existing records with correct remote key.
    $values_to_update = array_diff_key(
      $values,
      array_fill_keys($this->getPrimaryKey(), TRUE),
    );

    if (!$this->hasDataFields) {
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
   * Filters a database query by primary key values.
   *
   * @param \Drupal\Core\Database\Query\ConditionInterface $query
   *   Database query or condition.
   * @param array $values
   *   An array with values for some of the local primary key columns.
   */
  private function filterQueryPartial(ConditionInterface $query, array $values): void {
    foreach (array_intersect_key($values, array_fill_keys($this->localKey, TRUE)) as $k => $v) {
      $query->condition($k, $v);
    }
  }

  /**
   * Filters a database query by primary key values.
   *
   * @param \Drupal\Core\Database\Query\ConditionInterface $query
   *   Database query or condition.
   * @param array $values
   *   A full record, or an array with values for at least all the primary key
   *   columns.
   * @param string|null $remote_key_comparator
   *   Comparison operator for remote keys.
   */
  private function filterQuery(ConditionInterface $query, array $values, ?string $remote_key_comparator = '='): void {
    foreach ($this->localKey as $k) {
      $query->condition($k, $values[$k] ?? throw new \InvalidArgumentException("Missing value for '$k'."));
    }
    if ($remote_key_comparator === '=') {
      // Find records where the remote key also matches.
      foreach ($this->remoteKey as $k) {
        $query->condition($k, $values[$k] ?? throw new \InvalidArgumentException("Missing value for '$k'."));
      }
    }
    elseif ($remote_key_comparator === '!=') {
      if (!$this->remoteKey) {
        throw new \RuntimeException('Cannot apply negated remote key conditions, if remote key is empty.');
      }
      // Find records where the remote key does not match.
      $or = $query->orConditionGroup();
      foreach ($this->remoteKey as $k) {
        $or->condition($k, $values[$k] ?? throw new \InvalidArgumentException("Missing value for '$k'."), '!=');
      }
      $query->condition($or);
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
    foreach ($relationships as $source_alias => $relationship) {
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
