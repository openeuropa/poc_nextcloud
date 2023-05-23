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
   * @return float|int|null
   *   Size of the total workload.
   *   Zero if the job will be really short, but still needs to be run.
   *   NULL if the job does not need to be run at all.
   *   Note that the result can change after other jobs have run.
   */
  public function getPendingWorkloadSize(): float|int|null;

}
