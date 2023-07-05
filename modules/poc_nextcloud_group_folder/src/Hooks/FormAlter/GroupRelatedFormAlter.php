<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\Hooks\FormAlter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\hux\Attribute\Alter;
use Drupal\poc_nextcloud\Job\Runner\JobBatchRunner;

/**
 * Form alter to run the pending jobs.
 */
class GroupRelatedFormAlter {

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
   *
   * When any of these forms is submitted, the queued jobs should run as batch.
   * The batch will always run all the jobs, not just the ones that were queued
   * up in that specific form.
   */
  #[Alter('form_group_form'), Alter('form_group_confirm_form')]
  #[Alter('form_group_relationship_form'), Alter('form_group_content_form')]
  #[Alter('form_group_relationship_confirm_form'), Alter('form_group_content_confirm_form')]
  #[Alter('form_group_role_form'), Alter('form_group_role_confirm_form')]
  #[Alter('form_group_admin_permissions')]
  public function formAlter(array &$form, FormStateInterface $form_state): void {
    $submit_handler = [$this->jobBatchRunner, 'setBatch'];
    if (!empty($form['actions']['submit']['#submit'])) {
      // Submit handlers in this form are part of the submit button element.
      $form['actions']['submit']['#submit'][] = $submit_handler;
    }
    else {
      // Submit handlers are registered on the form itself.
      $form['#submit'][] = $submit_handler;
    }
  }

}
