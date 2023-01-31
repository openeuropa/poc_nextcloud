<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Service;

use Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint;

/**
 * Higher-level methods to manipulate group folders.
 */
class GroupFolderHelper {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint $groupFolderEndpoint
   *   Group folder endpoint.
   */
  public function __construct(
    private NxGroupFolderEndpoint $groupFolderEndpoint,
  ) {}

  /**
   * Sets groups for a group folder.
   *
   * Note that newly added groups always start with full permissions in that
   * group folder.
   *
   * @param int $group_folder_id
   *   Group folder id.
   * @param array $group_ids
   *   Expected group ids.
   * @param string $regex
   *   Regular expression that defines a namespace.
   *   Any groups that match this pattern and are not in the $group_ids argument
   *   will be removed from the group folder.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function setGroups(int $group_folder_id, array $group_ids, string $regex): void {
    assert($group_ids = preg_grep($regex, $group_ids));

    $group_folder = $this->groupFolderEndpoint->load($group_folder_id);
    $existing_group_ids = $group_folder->getGroupIds();
    $existing_group_ids = preg_grep($regex, $existing_group_ids);

    $group_ids_to_remove = array_diff($existing_group_ids, $group_ids);
    foreach ($group_ids_to_remove as $group_id) {
      $this->groupFolderEndpoint->removeGroup($group_folder_id, $group_id);
    }

    $group_ids_to_add = array_diff($group_ids, $existing_group_ids);
    foreach ($group_ids_to_add as $group_id) {
      $this->groupFolderEndpoint->addGroup($group_folder_id, $group_id);
    }
  }

  /**
   * Sets groups with ACL access.
   *
   * @param int $group_folder_id
   *   Group folder id.
   * @param array $group_ids
   *   Expected group ids.
   * @param string $regex
   *   Regular expression that defines a namespace.
   *   Any groups that match this pattern and are not in the $group_ids argument
   *   will be removed from the ACL list for the group folder.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function setManageAclGroups(int $group_folder_id, array $group_ids, string $regex): void {
    $group_folder = $this->groupFolderEndpoint->load($group_folder_id);
    if (!$group_folder) {
      return;
    }
    $current_group_ids = $group_folder->getManageAclGroupIds();
    $current_group_ids = preg_grep($regex, $current_group_ids);
    foreach (array_diff($current_group_ids, $group_ids) as $group_id) {
      $this->groupFolderEndpoint->setManageAclGroup($group_folder_id, $group_id, FALSE);
    }
    foreach (array_diff($group_ids, $current_group_ids) as $group_id) {
      $this->groupFolderEndpoint->setManageAclGroup($group_folder_id, $group_id, TRUE);
    }
  }

  /**
   * Grants ACL for a specific group.
   *
   * @param int $group_folder_id
   *   Group folder id.
   * @param string $group_id
   *   Group id.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function setManageAclGroup(int $group_folder_id, string $group_id): void {
    $group_folder = $this->groupFolderEndpoint->load($group_folder_id);
    if (!$group_folder) {
      return;
    }
    if ($group_folder->hasManageAclGroup($group_id)) {
      return;
    }
    $this->groupFolderEndpoint->setManageAclGroup($group_folder_id, $group_id, TRUE);
  }

}
