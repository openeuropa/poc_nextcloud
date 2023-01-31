<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\EntityHook;

use Drupal\group\Entity\GroupContentInterface;
use Drupal\group\GroupMembership;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud_group_folder\Service\GroupFolderMemberHelper;

/**
 * Callback for group_content entity hooks.
 */
class GroupContentSync {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $userEndpoint
   *   User endpoint.
   * @param \Drupal\poc_nextcloud_group_folder\Service\GroupFolderMemberHelper $groupFolderMemberHelper
   *   Helper to update group folder members in response to different events.
   */
  public function __construct(
    private NxUserEndpoint $userEndpoint,
    private GroupFolderMemberHelper $groupFolderMemberHelper,
  ) {}

  /**
   * Responds to a group content entity hook.
   *
   * @param \Drupal\group\Entity\GroupContentInterface $group_content
   *   Group content entity.
   * @param string $op
   *   Verb from the entity hook.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function __invoke(GroupContentInterface $group_content, string $op): void {
    try {
      $membership = new GroupMembership($group_content);
    }
    catch (\Exception) {
      // This is not a user membership, but some other group content.
      return;
    }
    $user = $membership->getUser();
    $username = $user->getAccountName();
    $nextcloud_user = $this->userEndpoint->load($username);
    if (!$nextcloud_user) {
      return;
    }
    $this->groupFolderMemberHelper->updateNextcloudUserGroupsForMembership($membership, $nextcloud_user);
  }

}
