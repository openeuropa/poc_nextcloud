<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_workspace\EntityHook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud_group_workspace\Service\WorkspaceMemberHelper;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Callback for user hooks.
 */
class UserSync {

  use StringTranslationTrait;

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $userEndpoint
   *   Nextcloud API endpoint for user accounts.
   * @param \Drupal\poc_nextcloud_group_workspace\Service\WorkspaceMemberHelper $workspaceMemberHelper
   *   Helper to update workspace members when responding to different events.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   */
  public function __construct(
    private NxUserEndpoint $userEndpoint,
    private WorkspaceMemberHelper $workspaceMemberHelper,
    private LoggerInterface $logger,
  ) {}

  /**
   * Creates a Nextcloud account for a Drupal user, if applicable.
   *
   * This should be called from hook_user_insert() and hook_user_update().
   *
   * @param \Drupal\user\UserInterface $user
   *   The user that was created or updated.
   * @param string $op
   *   Verb from the hook name, e.g. 'presave'.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function __invoke(UserInterface $user, string $op): void {
    $name = $user->getAccountName();
    if (!is_string($name) || $name === '') {
      return;
    }
    // Check if the user has a Nextcloud account.
    $nextcloud_user = $this->userEndpoint->load($name);
    if (!$nextcloud_user) {
      return;
    }
    $this->workspaceMemberHelper->updateNextcloudUserGroups($user, $nextcloud_user);
  }

}
