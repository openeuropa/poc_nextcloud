<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Tracking;

use Drupal\Core\Database\Connection;

/**
 * Factory for instances of TrackingTable.
 *
 * Reasons for this dedicated factory class:
 * - It encapsulates the database connection dependency.
 * - Unlike the core db connection, this class can be autowired.
 */
class TrackingTableFactory {

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   */
  public function __construct(
    private Connection $connection,
  ) {}

  /**
   * Creates a TrackingTable instance.
   *
   * @param string $table_name
   *   Table name.
   *
   * @return \Drupal\poc_nextcloud\Tracking\TrackingTable
   *   Newly created tracking table.
   */
  public function create(
    string $table_name,
  ): TrackingTable {
    return new TrackingTable(
      $this->connection,
      $table_name,
    );
  }

}
