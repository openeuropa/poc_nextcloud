<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Hooks\Cron;

use Drupal\hux\Attribute\Hook;
use Drupal\poc_nextcloud\Job\ProgressiveJobInterface;
use Psr\Log\LoggerInterface;

/**
 * Hook implementation to run jobs in cron.
 */
class CronRunJobs {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Job\ProgressiveJobInterface $job
   *   The combined job to run at cron.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Logger channel.
   * @param int|float $maxDurationSeconds
   *   Duration at which to abort the jobs and leave the rest for the next cron.
   */
  public function __construct(
    private ProgressiveJobInterface $job,
    private LoggerInterface $logger,
    private int|float $maxDurationSeconds = 3,
  ) {}

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    try {
      $estimate_before = $this->job->estimate();
    }
    catch (\Exception $e) {
      watchdog_exception('poc_nextcloud', $e);
      $this->logger->error('Failed to estimate jobs during cron. Message: @messages', [
        '@message' => $e->getMessage(),
      ]);
      return;
    }
    if ($estimate_before === NULL) {
      return;
    }
    $t0 = microtime(TRUE);
    $progress = 0;
    $estimate_after = NULL;
    $time_elapsed = 0;
    try {
      $i = 0;
      foreach ($this->job->run() as $progress_increment) {
        ++$i;
        $progress += $progress_increment;
        $time_elapsed = microtime(TRUE) - $t0;
        if ($time_elapsed >= $this->maxDurationSeconds) {
          $estimate_after = $this->job->estimate();
          break;
        }
      }
    }
    catch (\Exception $e) {
      $time_error = microtime(TRUE) - $t0 - $time_elapsed;
      watchdog_exception('poc_nextcloud', $e);
      $this->logger->error('Error trying to run sync job step @n during cron. Spent @dt_success for successful steps, and @dt_failure for the failed step. Message: @message', [
        '@n' => $i,
        '@dt_success' => $time_elapsed,
        '@dt_failure' => $time_error,
        '@message' => $e->getMessage(),
      ]);
      return;
    }
    if ($estimate_after === NULL) {
      // Complete.
      $this->logger->info('Sync jobs completed. Initial estimate: @before, progress: @progress, duration: @duration seconds.', [
        '@before' => $estimate_before,
        '@progress' => $progress,
        '@duration' => $time_elapsed,
      ]);
    }
    else {
      // Incomplete.
      $this->logger->info('Sync jobs did not complete in the available time. Initial estimate: @before, remaining estimate: @after, progress: @progress, duration: @duration seconds.', [
        '@before' => $estimate_before,
        '@after' => $estimate_after,
        '@progress' => $progress,
        '@duration' => $time_elapsed,
      ]);
    }
  }

}
