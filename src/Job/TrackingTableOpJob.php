<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Job;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\poc_nextcloud\Tracking\Op;
use Drupal\poc_nextcloud\Tracking\RecordSubmit\TrackingRecordSubmitInterface;
use Drupal\poc_nextcloud\Tracking\TrackingTable;

/**
 * Sync job that works with a tracking table.
 *
 * @template RecordType as array
 */
class TrackingTableOpJob implements ProgressiveJobInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Tracking\TrackingTable $trackingTable
   *   Tracking table.
   * @param \Drupal\poc_nextcloud\Tracking\RecordSubmit\TrackingRecordSubmitInterface $trackingRecordSubmit
   *   Submit handler that writes changes to Nextcloud.
   * @param int $op
   *   Pending operation to handle in this job.
   *
   * @psalm-param \Drupal\poc_nextcloud\Tracking\TrackingTable<RecordType> $trackingTable
   * @psalm-param \Drupal\poc_nextcloud\Tracking\RecordSubmit\TrackingRecordSubmitInterface<RecordType> $trackingRecordSubmit
   */
  public function __construct(
    private TrackingTable $trackingTable,
    private TrackingRecordSubmitInterface $trackingRecordSubmit,
    private int $op,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function run(): \Iterator {
    $q = $this->selectPendingRecords();
    $stmt = $q->execute();
    while ($record = $stmt->fetchAssoc()) {
      $record_orig = $record;
      $this->trackingRecordSubmit->submitTrackingRecord($record, $this->op);
      if ($this->op === Op::DELETE) {
        $this->trackingTable->reportRemoteAbsence($record_orig);
      }
      else {
        $this->trackingTable->reportRemoteValues($record_orig, $record);
      }
      // Report the progress increment.
      yield 1;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPendingWorkloadSize(): float|int {
    $q = $this->selectPendingRecords();
    return (int) $q->countQuery()->execute()->fetchField();
  }

  /**
   * Builds a query to get pending records.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   Select query.
   */
  private function selectPendingRecords(): SelectInterface {
    $q = $this->trackingTable->select();

    $q->condition('t.pending_operation', $this->op);
    return $q;
  }

}
