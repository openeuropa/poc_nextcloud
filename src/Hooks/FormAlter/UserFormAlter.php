<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Hooks\FormAlter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\hux\Attribute\Alter;
use Drupal\poc_nextcloud\Job\Runner\JobBatchRunner;

/**
 * User form alter hook.
 *
 * @todo Move src/Hux/ to src/Hooks/ once autowire is supported in Hux, see
 *   https://www.drupal.org/project/hux/issues/3363433.
 */
class UserFormAlter {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Job\Runner\JobBatchRunner $jobBatchRunner
   *   Job batch runner.
   */
  public function __construct(
    private JobBatchRunner $jobBatchRunner,
  ) {}

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Alter('form_user_form')]
  public function entityFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['actions']['submit']['#submit'][] = [
      $this->jobBatchRunner,
      'setBatch',
    ];
  }

}
