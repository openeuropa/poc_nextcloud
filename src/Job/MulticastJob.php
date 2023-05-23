<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Job;

use Drupal\poc_nextcloud\Job\Collector\JobCollector;

/**
 * Multicast sync job.
 */
class MulticastJob implements ProgressiveJobInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Job\ProgressiveJobInterface[] $jobs
   *   Jobs.
   */
  public function __construct(
    private array $jobs,
  ) {}

  /**
   * Static factory.
   *
   * @param \Drupal\poc_nextcloud\Job\Collector\JobCollector $collector
   *   Job collector.
   *
   * @return self
   *   New instance.
   */
  public static function fromJobCollector(JobCollector $collector): self {
    return new self($collector->getJobs());
  }

  /**
   * {@inheritdoc}
   */
  public function run(): \Iterator {
    $estimates = [];
    foreach ($this->jobs as $delta => $job) {
      $estimates[$delta] = $job->estimate();
    }
    foreach ($this->jobs as $delta => $job) {
      $original_estimate = $estimates[$delta];
      $current_estimate = $job->estimate();
      if (!$current_estimate || abs($current_estimate) < 0.0001) {
        // Don't divide by zero, not even a zero-ish float.
        $factor = 0;
      }
      else {
        $factor = $original_estimate / $current_estimate;
      }
      if ($current_estimate !== NULL) {
        // The job cannot be skipped.
        foreach ($job->run() as $increment) {
          yield $increment * $factor;
        }
      }
      if (!$factor && $original_estimate) {
        // Make sure the progress bar ends with 100%.
        yield $original_estimate;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function estimate(): float|int|null {
    $sum = 0;
    $skippable = TRUE;
    foreach ($this->jobs as $job) {
      $size = $job->estimate();
      if ($size !== NULL) {
        $sum += $size;
        $skippable = FALSE;
      }
    }
    if ($skippable) {
      return NULL;
    }
    return $sum;
  }

}
