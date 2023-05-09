<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Job\Provider;

use Drupal\poc_nextcloud\Job\Collector\JobCollectorInterface;
use Psr\Container\ContainerInterface;

/**
 * Object that has a sync job.
 *
 * This is used for separation of concerns, so that the actual implementation
 * for SyncJob* can be done in a distinct class.
 */
interface JobProviderInterface {

  /**
   * Registers jobs into a collector.
   *
   * @param \Drupal\poc_nextcloud\Job\Collector\JobCollectorInterface $collector
   *   Job collector.
   * @param \Psr\Container\ContainerInterface $container
   *   Container to pull additional services.
   *   This avoids having these services instantiated when the job provider is
   *   constructed.
   */
  public function collectJobs(JobCollectorInterface $collector, ContainerInterface $container): void;

}
