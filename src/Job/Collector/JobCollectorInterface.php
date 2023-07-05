<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Job\Collector;

use Drupal\poc_nextcloud\Job\ProgressiveJobInterface;

/**
 * Object to collect jobs.
 */
interface JobCollectorInterface {

  /**
   * Adds a job.
   *
   * @param bool $phase
   *   FALSE for delete phase, TRUE for insert/update phase.
   * @param int $position
   *   Sorting position.
   * @param \Drupal\poc_nextcloud\Job\ProgressiveJobInterface $job
   *   Job.
   */
  public function addJob(bool $phase, int $position, ProgressiveJobInterface $job): void;

}
