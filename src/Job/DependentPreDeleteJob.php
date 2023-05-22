<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Job;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\poc_nextcloud\Tracking\Op;
use Drupal\poc_nextcloud\Tracking\RecordSubmit\TrackingRecordSubmitInterface;
use Drupal\poc_nextcloud\Tracking\TrackingTable;

/**
 * Job that removes dependent records before their dependency record is gone.
 *
 * This is needed for objects in Nextcloud that are not deleted automatically
 * when a dependency is deleted.
 *
 * @template RecordType as array
 */
class DependentPreDeleteJob implements ProgressiveJobInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Tracking\TrackingTable $trackingTable
   *   Tracking table.
   * @param \Drupal\poc_nextcloud\Tracking\RecordSubmit\TrackingRecordSubmitInterface $trackingRecordSubmit
   *   Submit handler.
   * @param \Drupal\poc_nextcloud\Tracking\TrackingTableRelationship[] $relationships
   *   Other tracking tables this table depends on, by alias.
   * @param string $alias
   *   Alias for the specific dependency table.
   *
   * @psalm-param \Drupal\poc_nextcloud\Tracking\TrackingTable<RecordType> $trackingTable
   * @psalm-param \Drupal\poc_nextcloud\Tracking\RecordSubmit\TrackingRecordSubmitInterface<RecordType> $trackingRecordSubmit
   */
  public function __construct(
    private TrackingTable $trackingTable,
    private TrackingRecordSubmitInterface $trackingRecordSubmit,
    private array $relationships,
    private string $alias,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function run(): \Iterator {
    // Select dependent records that will be orphaned.
    $q = $this->selectObsoleteDependentRecords();
    $stmt = $q->execute();
    while ($record = $stmt->fetchAssoc()) {
      $this->trackingRecordSubmit->submitTrackingRecord($record, Op::DELETE);
      $this->trackingTable->reportRemoteAbsence($record);
      // Report the progress increment.
      yield 1;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPendingWorkloadSize(): float|int {
    $q = $this->selectObsoleteDependentRecords();
    return (int) $q->countQuery()->execute()->fetchField();
  }

  /**
   * Builds a query to select obsolete records.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   Select query.
   */
  private function selectObsoleteDependentRecords(): SelectInterface {
    $q = $this->trackingTable->select('t', $this->relationships);
    // Find records where the source record will be deleted.
    $q->condition("$this->alias.pending_operation", Op::DELETE);
    // Find records that actually exist on the remote side.
    $q->condition("t.pending_operation", Op::INSERT, '!=');
    return $q;
  }

}
