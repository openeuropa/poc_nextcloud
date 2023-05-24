<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Job\Runner;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\poc_nextcloud\Job\ProgressiveJobInterface;

/**
 * Service to register a job object as a batch.
 */
class JobBatchRunner {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Job\ProgressiveJobInterface $job
   *   The job to perform.
   */
  public function __construct(
    private ProgressiveJobInterface $job,
  ) {}

  /**
   * Registers a batch operation with the current job.
   */
  public function setBatch(): void {
    $estimate = $this->job?->estimate();

    if ($estimate === NULL) {
      // Nothing to do.
      return;
    }

    $batch_definition['operations'][] = [[$this, 'processBatch'], []];

    // If called inside of Drush, we want to start the batch immediately.
    // However, we first need to determine whether there already is one running,
    // since we don't want to start a second one – our new batch will
    // automatically be appended to the currently running batch operation.
    $batch = batch_get();
    $run_drush_batch = function_exists('drush_backend_batch_process')
      && empty($batch['running']);

    // Schedule the batch.
    batch_set($batch_definition);

    // Now run the Drush batch, if applicable.
    if ($run_drush_batch) {
      $result = drush_backend_batch_process();
      // Drush performs batch processing in a separate PHP request. When the
      // last batch is processed the batch list is cleared, but this only takes
      // effect in the other request. Take the same action here to ensure that
      // we are not requeueing stale batches when there are multiple tasks being
      // handled in a single request.
      // (Drush 9.6 changed the structure of $result, so check for both variants
      // as long as we support earlier Drush versions, too.)
      if (!empty($result['context']['drush_batch_process_finished'])
        || !empty($result['drush_batch_process_finished'])
      ) {
        $batch = &batch_get();
        $batch = NULL;
        unset($batch);
      }
    }
  }

  /**
   * Processes a single pending task as part of a batch operation.
   *
   * @param array|\ArrayAccess $context
   *   The context of the current batch.
   */
  public function processBatch(array|\ArrayAccess &$context) {
    $total_ref =& $context['sandbox']['total'];
    $processed_ref =& $context['sandbox']['processed'];
    $finished_ref =& $context['finished'];

    if ($total_ref === NULL) {
      // This is the first iteration.
      $total_ref = $this->job->estimate();
      if ($total_ref === NULL) {
        $finished_ref = 1;
        return;
      }
    }

    // Limit duration to 1 second.
    $t_limit = microtime(TRUE) + .5;
    for ($it = $this->job->run();; $it->next()) {
      if (!$it->valid()) {
        $finished_ref = 1;
        return;
      }
      $processed_ref += $it->current();
      if (microtime(TRUE) > $t_limit) {
        $pending = $this->job->estimate();
        if ($pending > .1) {
          // Too much time passed, and there is still work to do.
          // Stop here, continue with another batch request.
          break;
        }
      }
    }

    $total_now = $processed_ref + $pending;
    $finished_ref = $processed_ref / $total_now;

    if ($total_now === $total_ref) {
      $context['message'] = sprintf('%d / %d', $processed_ref, $total_now);
    }
    else {
      // The initial estimate was wrong.
      $context['message'] = sprintf('%d / %d (%d)', $processed_ref, $total_now, $total_ref);
    }
  }

}
