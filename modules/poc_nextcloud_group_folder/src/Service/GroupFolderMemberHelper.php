<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\Service;

use Drupal\group\Entity\GroupInterface;
use Drupal\group\GroupMembership;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud\NxEntity\NxGroupFolder;
use Drupal\poc_nextcloud\NxEntity\NxUser;
use Drupal\poc_nextcloud\Service\NextcloudGroupHelper;
use Drupal\user\UserInterface;

/**
 * Helper to update group folder members when responding to different events.
 */
class GroupFolderMemberHelper {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud_group_folder\Service\GroupFolderFieldHelper $groupFolderFieldHelper
   *   Service to identify the field referencing a group folder.
   * @param \Drupal\group\GroupMembershipLoaderInterface $groupMembershipLoader
   *   Service to load group memberships for a user.
   * @param \Drupal\poc_nextcloud\Service\NextcloudGroupHelper $userGroupHelper
   *   Helper to add or remove Nextcloud users from groups.
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $userEndpoint
   *   User endpoint.
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint $groupEndpoint
   *   Group endpoint.
   */
  public function __construct(
    private GroupFolderFieldHelper $groupFolderFieldHelper,
    private GroupMembershipLoaderInterface $groupMembershipLoader,
    private NextcloudGroupHelper $userGroupHelper,
    private NxUserEndpoint $userEndpoint,
    private NxGroupEndpoint $groupEndpoint,
  ) {}

  /**
   * Updates group memberships in Nextcloud for a given user.
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
      '@^GROUPFOLDER-\d+-\w+-\w+$@',
    );
  }

  /**
   * Updates memberships in response to a group-level event.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Drupal group.
   * @param \Drupal\poc_nextcloud\NxEntity\NxGroupFolder $group_folder
   *   Group folder.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Something went wrong in one of the API calls.
   */
  public function updateNextcloudMembershipsForGroup(GroupInterface $group, NxGroupFolder $group_folder): void {
    $group_folder_id = $group_folder->getId();
    $expected_user_ids_by_group_id = [];
    foreach ($group_folder->getGroupIds() as $group_id) {
      $expected_user_ids_by_group_id[$group_id] = [];
    }
    foreach ($group->getMembers() as $membership) {
      $user = $membership->getUser();
      $username = $user->getAccountName();
      $nextcloud_user = $this->userEndpoint->load($username);
      if (!$nextcloud_user) {
        continue;
      }
      $group_ids = $this->getExpectedGroupIdsForMembershipAndGroupFolder($membership, $group_folder_id);
      foreach ($group_ids as $group_id) {
        $expected_user_ids_by_group_id[$group_id][] = $username;
      }
    }
    foreach ($expected_user_ids_by_group_id as $group_id => $user_ids) {
      $this->userGroupHelper->setGroupUsers($group_id, $user_ids);
    }
  }

  /**
   * Determines the groups in Nextcloud that the user should be a member of.
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
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Something went wrong in one of the API calls.
   */
  private function getExpectedGroupIdsForMembership(GroupMembership $membership, string &$regex = NULL): array {
    $group = $membership->getGroup();
    $group_folder_id = $this->groupFolderFieldHelper->groupGetGroupFolderId($group);
    if (!$group_folder_id) {
      return [];
    }
    $regex = sprintf('@^GROUPFOLDER-%s-\w+-\w+$@', $group_folder_id);
    return $this->getExpectedGroupIdsForMembershipAndGroupFolder($membership, $group_folder_id);
  }

  /**
   * Determines the groups in Nextcloud that the user should be a member of.
   *
   * @param \Drupal\group\GroupMembership $membership
   *   Group membership.
   * @param int $group_folder_id
   *   Group folder id.
   *
   * @return string[]
   *   Group ids.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Something went wrong in one of the API calls.
   */
  private function getExpectedGroupIdsForMembershipAndGroupFolder(GroupMembership $membership, int $group_folder_id): array {
    $group_ids = [];
    foreach ($membership->getRoles() as $role) {
      $group_id = 'GROUPFOLDER-' . $group_folder_id . '-' . $role->id();
      $nextcloud_group = $this->groupEndpoint->load($group_id);
      if (!$nextcloud_group) {
        // Cannot add user to a non-existent group.
        continue;
      }
      $group_ids[] = $group_id;
    }
    return $group_ids;
  }

}
