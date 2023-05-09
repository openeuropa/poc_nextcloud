<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Commands;

use Drupal\poc_nextcloud\Job\ProgressiveJobInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush command file for POC Nextcloud.
 */
class PocNextcloudCommands extends DrushCommands {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Job\ProgressiveJobInterface $job
   *   Combined job to run.
   */
  public function __construct(
    private ProgressiveJobInterface $job,
  ) {
    parent::__construct();
  }

  /**
   * Command to run the combined job.
   *
   * @command poc-nextcloud:run-job
   *
   * @throws \Exception
   */
  public function runJob(): void {
    $total = $this->job->getPendingWorkloadSize();
    print "Pending: $total.\n";
    $remaining = $total;
    foreach ($this->job->run() as $delta) {
      $remaining -= $delta;
    }
    print "Remaining: $remaining.\n";
  }

}
