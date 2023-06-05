<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Tracking\Tracker;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\poc_nextcloud\Database\SchemaProviderInterface;
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
abstract class TrackerBase implements SchemaProviderInterface, JobProviderInterface, ModuleUninstallValidatorInterface {

  use StringTranslationTrait;

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
   * {@inheritdoc}
   */
  public function getSchema(): array {
    return [$this->trackingTable->getTableName() => $this->trackingTable->getTableSchema()];
  }

  /**
   * {@inheritdoc}
   *
   * Prevents uninstall of the respective module, if there are still remote
   * objects present.
   */
  public function validate($module): array {
    // Get the module name from the class name.
    // For all known cases this is good enough.
    if (!str_starts_with(static::class, 'Drupal\\' . $module . '\\')) {
      return [];
    }
    try {
      $n = $this->trackingTable->countTrackedRemoteObjects();
    }
    catch (\Exception $e) {
      // The tracking table was not properly created in the database.
      return [];
    }
    if ($n <= 0) {
      return [];
    }
    return [
      $this->t('To uninstall @module, all remote objects have to be removed first. There are still @n remote objects in @table', [
        '@module' => $module,
        '@table' => $this->trackingTable->getTableName(),
        '@n' => $n,
      ]),
    ];
  }

}
