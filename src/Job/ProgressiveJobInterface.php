<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Job;

/**
 * Operation that can be executed in chunks.
 */
interface ProgressiveJobInterface {

  /**
   * Executes queued tasks that remove items.
   *
   * For multiple interdependent queues, this method of a dependent queue has to
   * run before that of its dependee.
   *
   * @return \Iterator
   *   Iterator with numbers indicating the progress made in that iteration.
   *   The simplest implementations will return 1 on every step.
   *
   * @psalm-return \Iterator<float|int>
   *
   * @throws \Exception
   *   Something went wrong.
   */
  public function run(): \Iterator;

  /**
   * Gets the size of the total pending workload.
   *
   * This should be equal to the sum of all yielded progress increments from
   * the two other methods.
   *
   * @return float|int
   *   Size of the total workload. Zero if nothing to do.
   */
  public function getPendingWorkloadSize(): float|int;

}
