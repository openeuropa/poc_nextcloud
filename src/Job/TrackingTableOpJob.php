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
   * @param string $op
   *   Pending operation to handle in this job.
   *
   * @psalm-param \Drupal\poc_nextcloud\Tracking\TrackingTable<RecordType> $trackingTable
   * @psalm-param \Drupal\poc_nextcloud\Tracking\RecordSubmit\TrackingRecordSubmitInterface<RecordType> $trackingRecordSubmit
   */
  public function __construct(
    private TrackingTable $trackingTable,
    private TrackingRecordSubmitInterface $trackingRecordSubmit,
    private string $op,
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
        $this->trackingTable->reportRemoteValues($record);
      }
      // Report the progress increment.
      yield 1;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function estimate(): float|int|null {
    $q = $this->selectPendingRecords();
    // If no results, this can be skipped.
    return (int) $q->countQuery()->execute()->fetchField() ?: NULL;
  }

  /**
   * Builds a query to get pending records.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   Select query.
   */
  private function selectPendingRecords(): SelectInterface {
    $q = $this->trackingTable->select();
    switch ($this->op) {
      case Op::DELETE:
        $q->isNull('t.pending_hash');
        break;

      case Op::INSERT:
        $q->isNull('t.remote_hash');
        break;

      case Op::UPDATE:
        // This comparison in SQL never passes for NULL values, which is good.
        $q->where('t.remote_hash != t.pending_hash');
    }
    return $q;
  }

}
