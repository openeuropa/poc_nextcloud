<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\EntityHook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud_group_folder\Service\GroupFolderMemberHelper;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Service to create Nextcloud accounts for Drupal users.
 */
class UserSync {

  use StringTranslationTrait;

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $userEndpoint
   *   Nextcloud API endpoint for user accounts.
   * @param \Drupal\poc_nextcloud_group_folder\Service\GroupFolderMemberHelper $groupFolderMemberHelper
   *   Helper to update group folder members in response to different events.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   */
  public function __construct(
    private NxUserEndpoint $userEndpoint,
    private GroupFolderMemberHelper $groupFolderMemberHelper,
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
   *   Verb from the entity hook, e.g. 'update'.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function __invoke(UserInterface $user, string $op): void {
    $name = $user->getAccountName();
    if (!is_string($name) || $name === '') {
      // Something is wrong with the name.
      $this->logger->warning('No Nextcloud account can be associated with user @uid, because the username is not valid.', ['@uid' => $user->id()]);
      return;
    }
    // Check if the user has a Nextcloud account.
    $nextcloud_user = $this->userEndpoint->load($name);
    if (!$nextcloud_user) {
      return;
    }
    $this->groupFolderMemberHelper->updateNextcloudUserGroups($user, $nextcloud_user);
  }

}
