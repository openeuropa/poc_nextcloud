<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_workspace\Service;

use Drupal\group\Entity\GroupInterface;
use Drupal\group\GroupMembership;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud\NxEntity\NxUser;
use Drupal\poc_nextcloud\Service\NextcloudGroupHelper;
use Drupal\user\UserInterface;

/**
 * Helper to update workspace members when responding to different events.
 */
class WorkspaceMemberHelper {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud_group_workspace\Service\GroupWorkspaceFieldHelper $groupWorkspaceFieldHelper
   *   Helper to interact with the field that references a workspace.
   * @param \Drupal\group\GroupMembershipLoaderInterface $groupMembershipLoader
   *   Service to load group memberships for a user.
   * @param \Drupal\poc_nextcloud\Service\NextcloudGroupHelper $userGroupHelper
   *   Higher-level methods to manage Nextcloud groups and memberships.
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $userEndpoint
   *   User endpoint.
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint $groupEndpoint
   *   Group endpoint.
   */
  public function __construct(
    private GroupWorkspaceFieldHelper $groupWorkspaceFieldHelper,
    private GroupMembershipLoaderInterface $groupMembershipLoader,
    private NextcloudGroupHelper $userGroupHelper,
    private NxUserEndpoint $userEndpoint,
    private NxGroupEndpoint $groupEndpoint,
  ) {}

  /**
   * Updates Nextcloud group memberships in response to a user-level event.
   *
   * @param \Drupal\user\UserInterface $user
   *   Drupal user.
   * @param \Drupal\poc_nextcloud\NxEntity\NxUser $nextcloud_user
   *   Nextcloud user.
   *   This parameter only exists to make sure that the account does exist.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   One of the API calls failed.
   */
  public function updateNextcloudUserGroups(UserInterface $user, NxUser $nextcloud_user): void {
    $name = $nextcloud_user->getId();
    $memberships = $this->groupMembershipLoader->loadByUser($user);
    $group_id_lists = [];
    foreach ($memberships as $membership) {
      $group_id_lists[] = $this->getExpectedGroupIdsForMembership($membership);
    }
    $group_ids = array_merge(...$group_id_lists);
    $this->userGroupHelper->setUserGroups(
      $name,
      $group_ids,
      '@^SPACE-(?:U|GE)-\d+$@',
    );
  }

  /**
   * Updates Nextcloud group memberships in response to a group-level event.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Drupal group.
   * @param int $workspace_id
   *   Workspace id.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Something went wrong in one of the API calls.
   */
  public function updateNextcloudMembershipsForGroup(GroupInterface $group, int $workspace_id): void {
    $regex = '@^SPACE-(?:U|GE)-' . $workspace_id . '$@';
    $member_names = [];
    foreach ($group->getMembers() as $membership) {
      $user = $membership->getUser();
      $username = $user->getAccountName();
      $nextcloud_user = $this->userEndpoint->load($username);
      if (!$nextcloud_user) {
        continue;
      }
      $group_ids = $this->getExpectedGroupIdsForMembershipAndWorkspace($membership, $workspace_id);
      $this->userGroupHelper->setUserGroups(
        $username,
        $group_ids,
        $regex,
      );
      $member_names[] = $username;
    }
    $nextcloud_group_ids = [
      'SPACE-U-' . $workspace_id,
      'SPACE-GE-' . $workspace_id,
    ];
    $user_ids_to_remove = [];
    foreach ($nextcloud_group_ids as $group_id) {
      $current_nextcloud_user_ids = $this->groupEndpoint->getUserIds($group_id);
      $user_ids_to_remove[] = array_diff($current_nextcloud_user_ids, $member_names);
    }
    $user_ids_to_remove = array_unique(array_merge(...$user_ids_to_remove));
    foreach ($user_ids_to_remove as $user_id) {
      $this->userGroupHelper->setUserGroups(
        $user_id,
        [],
        $regex,
      );
    }
  }

  /**
   * Updates Nextcloud group memberships in response to a membership event.
   *
   * @param \Drupal\group\GroupMembership $membership
   *   Group membership.
   * @param \Drupal\poc_nextcloud\NxEntity\NxUser $nextcloud_user
   *   Nextcloud user.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Something went wrong in one of the API calls.
   */
  public function updateNextcloudUserGroupsForMembership(GroupMembership $membership, NxUser $nextcloud_user): void {
    $group_ids = $this->getExpectedGroupIdsForMembership($membership, $regex);
    if (!$group_ids) {
      return;
    }
    $this->userGroupHelper->setUserGroups(
      $nextcloud_user->getId(),
      $group_ids,
      $regex,
    );
  }

  /**
   * Determines the groups in Nextcloud that the user should be a member of.
   *
   * @param \Drupal\group\GroupMembership $membership
   *   Group membership.
   * @param string|null $regex
   *   This will contain a regular expression after the call.
   *
   * @return string[]
   *   Group ids.
   */
  private function getExpectedGroupIdsForMembership(GroupMembership $membership, string &$regex = NULL): array {
    $group = $membership->getGroup();
    $workspace_id = $this->groupWorkspaceFieldHelper->groupGetWorkspaceId($group);
    if (!$workspace_id) {
      return [];
    }
    $regex = '@^SPACE-(?:U|GE)-' . $workspace_id . '$@';
    return $this->getExpectedGroupIdsForMembershipAndWorkspace($membership, $workspace_id);
  }

  /**
   * Determines the groups in Nextcloud that the user should be a member of.
   *
   * @param \Drupal\group\GroupMembership $membership
   *   Group membership.
   * @param int $workspace_id
   *   Workspace id.
   *
   * @return string[]
   *   Group ids.
   */
  private function getExpectedGroupIdsForMembershipAndWorkspace(GroupMembership $membership, int $workspace_id): array {
    $group_ids = [];
    if ($membership->hasPermission('nextcloud workspace join')) {
      $group_ids[] = 'SPACE-U-' . $workspace_id;
    }
    if ($membership->hasPermission('nextcloud workspace manage')) {
      $group_ids[] = 'SPACE-GE-' . $workspace_id;
    }
    return $group_ids;
  }

}
