<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\WritableImage;

use Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint;
use Drupal\poc_nextcloud\NextcloudConstants;
use Drupal\poc_nextcloud\NxEntity\NxGroupFolder;

/**
 * Operation to connect groups to group folders.
 *
 * After the operation, group folders will have exactly the groups as defined in
 * the image, except for groups outside the namespace.
 *
 * If the purge option was enabled, then groups in the namespace will be
 * connected to exactly the group folders as defined in the image.
 */
class GroupFoldersGroupsImage {

  /**
   * Group permissions by groupfolder id and group id.
   *
   * @var int[][]
   */
  private array $groupsPermsByGroupfolderId = [];

  /**
   * Group ids for ACL by group folder id.
   *
   * @var string[][]
   */
  private array $aclGroupsByGroupFolderId = [];

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint $groupFolderEndpoint
   *   API endpoint for group folders.
   * @param string $groupIdRegex
   *   Regular expression to define the group id namespace.
   * @param bool $purgeOtherGroupFolders
   *   TRUE, to remove groups from other group folders, if the group id matches
   *   the regular expression.
   */
  public function __construct(
    private NxGroupFolderEndpoint $groupFolderEndpoint,
    private string $groupIdRegex,
    private bool $purgeOtherGroupFolders,
  ) {}

  /**
   * Adds a group to a group folder.
   *
   * @param int $group_folder_id
   *   Group folder id.
   * @param string $group_id
   *   Group id.
   * @param int $perms
   *   Permissions bitmask, including ACL.
   */
  public function groupFolderAddGroup(int $group_folder_id, string $group_id, int $perms): void {
    $basic_perms = $perms & NextcloudConstants::PERMISSION_ALL;
    if ($basic_perms) {
      $this->groupsPermsByGroupfolderId[$group_folder_id][$group_id] = $perms;
    }
    if ($perms & NextcloudConstants::PERMISSION_ADVANCED) {
      $this->aclGroupsByGroupFolderId[$group_folder_id][] = $group_id;
    }
  }

  /**
   * Writes the image to Nextcloud.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function write(): void {
    $this->writeGroupsPerms();
    $this->writeAclGroups();
  }

  /**
   * Writes group permissions.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function writeGroupsPerms(): void {
    if ($this->purgeOtherGroupFolders) {
      foreach ($this->groupFolderEndpoint->loadAll() as $group_folder) {
        $this->writeGroupFolderGroupsPerms(
          $group_folder,
          $this->groupsPermsByGroupfolderId[$group_folder->getId()] ?? [],
        );
      }
    }
    else {
      foreach ($this->groupsPermsByGroupfolderId as $group_folder_id => $groups_perms) {
        $group_folder = $this->groupFolderEndpoint->load($group_folder_id);
        if (!$group_folder) {
          continue;
        }
        $this->writeGroupFolderGroupsPerms($group_folder, $groups_perms);
      }
    }
  }

  /**
   * Writes group permissions for a single group folder.
   *
   * @param \Drupal\poc_nextcloud\NxEntity\NxGroupFolder $group_folder
   *   Group folder.
   * @param int[] $groups_perms
   *   Permissions bitmasks by group id.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function writeGroupFolderGroupsPerms(NxGroupFolder $group_folder, array $groups_perms): void {
    $group_folder_id = $group_folder->getId();
    $group_ids = array_keys($groups_perms);
    $existing_group_ids = $group_folder->getGroupIds();
    $existing_group_ids = preg_grep($this->groupIdRegex, $existing_group_ids);

    if (!$groups_perms) {
      // Shortcut: Remove all, and skip the rest.
      foreach ($existing_group_ids as $group_id) {
        $this->groupFolderEndpoint->removeGroup($group_folder_id, $group_id);
      }
      return;
    }

    $group_ids_to_remove = array_diff($existing_group_ids, $group_ids);
    foreach ($group_ids_to_remove as $group_id) {
      $this->groupFolderEndpoint->removeGroup($group_folder_id, $group_id);
    }

    $group_ids_to_add = array_diff($group_ids, $existing_group_ids);
    foreach ($group_ids_to_add as $group_id) {
      $this->groupFolderEndpoint->addGroup($group_folder_id, $group_id);
    }

    foreach ($groups_perms as $group_id => $perms) {
      $this->groupFolderEndpoint->setGroupPermissions($group_folder_id, $group_id, $perms);
    }
  }

  /**
   * Writes ACL groups for all group folders in the image.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function writeAclGroups(): void {
    if ($this->purgeOtherGroupFolders) {
      foreach ($this->groupFolderEndpoint->loadAll() as $group_folder) {
        $this->writeGroupFolderAclGroups(
          $group_folder,
          $this->aclGroupsByGroupFolderId[$group_folder->getId()] ?? [],
        );
      }
    }
    else {
      foreach ($this->aclGroupsByGroupFolderId as $group_folder_id => $group_ids) {
        $group_folder = $this->groupFolderEndpoint->load($group_folder_id);
        if (!$group_folder) {
          continue;
        }
        $this->writeGroupFolderAclGroups($group_folder, $group_ids);
      }
    }
  }

  /**
   * Writes ACL groups for a single group folder.
   *
   * @param \Drupal\poc_nextcloud\NxEntity\NxGroupFolder $group_folder
   *   Group folder.
   * @param array $group_ids
   *   Group ids with ACL access.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function writeGroupFolderAclGroups(NxGroupFolder $group_folder, array $group_ids): void {
    $group_folder_id = $group_folder->getId();
    $current_group_ids = $group_folder->getAclManagerGroupIds();
    $current_group_ids = preg_grep(
      $this->groupIdRegex,
      $current_group_ids,
    );
    $group_ids_to_remove = array_diff($current_group_ids, $group_ids);
    $group_ids_to_add = array_diff($group_ids, $current_group_ids);
    foreach ($group_ids_to_remove as $group_id) {
      $this->groupFolderEndpoint->setManageAclGroup($group_folder_id, $group_id, FALSE);
    }
    foreach ($group_ids_to_add as $group_id) {
      $this->groupFolderEndpoint->setManageAclGroup($group_folder_id, $group_id, TRUE);
    }
    // Enable or disable ACL, depending on whether any managers are left.
    $enable_acl = $group_ids
      || $group_folder->getAclManagerUserIds()
      || array_diff($group_folder->getAclManagerGroupIds(), $group_ids_to_remove);
    $this->groupFolderEndpoint->setAcl($group_folder_id, $enable_acl);
  }

}
