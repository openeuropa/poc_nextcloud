<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Tracking\Tracker;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\hux\Attribute\Hook;
use Drupal\poc_nextcloud\Job\Collector\JobCollectorInterface;
use Drupal\poc_nextcloud\Job\Provider\JobProviderInterface;
use Drupal\poc_nextcloud\Tracking\RecordSubmit\TrackingRecordSubmitInterface;
use Drupal\poc_nextcloud\Tracking\TrackingTable;
use Psr\Container\ContainerInterface;

/**
 * Sync job that works with a tracking table.
 *
 * @template RecordType as array
 */
abstract class TrackerBase implements JobProviderInterface {

  /**
   * Constructor.
   *
   * @param class-string $trackingRecordSubmitClass
   *   Class or service id for the service that writes changes to the remote end
   *   (Nextcloud).
   * @param \Drupal\poc_nextcloud\Tracking\TrackingTable $trackingTable
   *   Tracking table.
   */
  protected function __construct(
    private string $trackingRecordSubmitClass,
    protected TrackingTable $trackingTable,
  ) {}

  /**
   * Builds a select query to get current records.
   *
   * @param string $alias
   *   Alias for the tracking table.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   Select query.
   */
  public function selectCurrent(string $alias = 't'): SelectInterface {
    return $this->trackingTable->select($alias)
      ->isNotNull("$alias.remote_hash");
  }

  /**
   * {@inheritdoc}
   */
  public function collectJobs(JobCollectorInterface $collector, ContainerInterface $container): void {
    $submit = $container->get($this->trackingRecordSubmitClass);
    assert($submit instanceof TrackingRecordSubmitInterface);
    $this->trackingTable->collectJobs($collector, $submit);
  }

  /**
   * Implements hook_schema().
   */
  #[Hook('schema')]
  public function getSchema(): array {
    return [$this->trackingTable->getTableName() => $this->trackingTable->getTableSchema()];
  }

}
