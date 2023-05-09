<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Tracking;

/**
 * Relationship from a dependent tracking table to a dependee tracking table.
 */
class TrackingTableRelationship {

  /**
   * Constructor.
   *
   * @param string $sourceTableName
   *   Name of the dependee table.
   * @param string[] $joinKeys
   *   Map of keys to build a table join condition.
   * @param string[] $sourceFields
   *   Fields from the dependee table to add to the query results.
   * @param bool $autoDelete
   *   TRUE, if dependent records are automatically deleted when the dependee is
   *     deleted in Nextcloud.
   *   FALSE, if orphan dependent records remain in Nextcloud. This could be due
   *     to a shortcoming in Nextcloud, or because the dependee is not actually
   *     deleted but just "forgotten".
   */
  public function __construct(
    private string $sourceTableName,
    private array $joinKeys,
    private array $sourceFields,
    private bool $autoDelete = TRUE,
  ) {}

  /**
   * Gets the name of the source table.
   *
   * @return string
   *   Table name.
   */
  public function getSourceTableName(): string {
    return $this->sourceTableName;
  }

  /**
   * Map of column names to build the join condition.
   *
   * @return string[]
   *   Source column names by local column names.
   */
  public function getJoinKeys(): array {
    return $this->joinKeys;
  }

  /**
   * Column names from the source table to add to result records.
   *
   * @return string[]
   *   Column names.
   */
  public function getSourceFields(): array {
    return $this->sourceFields;
  }

  /**
   * Tells whether orphaned dependent remote objects are automatically deleted.
   *
   * @return bool
   *   TRUE if Nextcloud will delete dependent objects when the source object is
   *     deleted.
   *   FALSE, if Nextcloud will leave orphaned dependent objects, or if the
   *     source object is not truly deleted but just disassociated with Drupal.
   */
  public function isAutoDelete(): bool {
    return $this->autoDelete;
  }

}
