<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Job;

use Drupal\poc_nextcloud\Tracking\TrackingTable;
use Drupal\poc_nextcloud\Tracking\TrackingTableRelationship;

/**
 * Job that updates the status of orphaned dependent records.
 *
 * This is used for object types that are automatically deleted in Nextcloud
 * when the dependency object is deleted. After this happens, the status of the
 * tracking record needs to be updated to reflect this automatic deletion.
 *
 * @template RecordType as array
 */
class DependentPostDeleteJob implements ProgressiveJobInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Tracking\TrackingTable $trackingTable
   *   Tracking table.
   * @param \Drupal\poc_nextcloud\Tracking\TrackingTableRelationship $relationship
   *   Relationship that would leave records orphaned.
   */
  public function __construct(
    private TrackingTable $trackingTable,
    private TrackingTableRelationship $relationship,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function run(): \Iterator {
    // Select dependent records that are now orphaned.
    $q = $this->trackingTable->selectOrphanedDependentKeyCombos($this->relationship);
    $stmt = $q->execute();
    while ($condition = $stmt->fetchAssoc()) {
      $this->trackingTable->reportRangeDeleted($condition);
      // Report the progress increment.
      // @todo Rethink progress calculation for this one.
      yield 1;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPendingWorkloadSize(): float|int {
    // @todo This calculation changes after other jobs have run.
    $q = $this->trackingTable->selectOrphanedDependentKeyCombos($this->relationship);
    return (int) $q->countQuery()->execute()->fetchField();
  }

}
