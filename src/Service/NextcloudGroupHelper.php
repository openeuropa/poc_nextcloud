<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Service;

use Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;

/**
 * Helper methods to add or remove Nextcloud users from groups.
 */
class NextcloudGroupHelper {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $userEndpoint
   *   User endpoint.
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint $groupEndpoint
   *   Group endpoint.
   */
  public function __construct(
    private NxUserEndpoint $userEndpoint,
    private NxGroupEndpoint $groupEndpoint,
  ) {}

  /**
   * Sets the groups that are meant to exist in a given namespace.
   *
   * @param string[] $group_display_names
   *   Expected group display names by group id.
   * @param string $regex
   *   Regular expression that defines the namespace.
   *   Any groups that match this pattern but are not part of the $group_ids
   *   array will be removed.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Something went wrong in one of the API calls.
   */
  public function setGroups(array $group_display_names, string $regex): void {
    $existing_ids = $this->groupEndpoint->loadIds();
    $existing_ids = preg_grep($regex, $existing_ids);
    $expected_ids = array_keys($group_display_names);

    $group_ids_to_delete = array_diff($existing_ids, $expected_ids);
    foreach ($group_ids_to_delete as $group_id) {
      $this->groupEndpoint->delete($group_id);
    }

    $group_ids_to_create = array_diff($expected_ids, $existing_ids);
    foreach ($group_ids_to_create as $group_id) {
      $this->groupEndpoint->insert($group_id, $group_display_names[$group_id]);
    }

    // Update the already-existing groups.
    foreach (array_intersect($expected_ids, $existing_ids) as $group_id) {
      $this->groupEndpoint->setDisplayName($group_id, $group_display_names[$group_id]);
    }
  }

  /**
   * Sets Nextcloud groups for a specific user.
   *
   *   Drupal user account, or NULL if not known for this operation.
   *   This is only used for log messages.
   *
   * @param string $user_id
   *   Nextcloud user id.
   * @param string[] $expected_group_ids
   *   Expected group ids that match the regular expression.
   * @param string $regex
   *   Regular expression to filter groups by.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Something went wrong in one of the API calls.
   */
  public function setUserGroups(string $user_id, array $expected_group_ids, string $regex): void {
    $current_group_ids = $this->userEndpoint->getGroupIds($user_id);
    $current_group_ids = preg_grep($regex, $current_group_ids);
    $group_ids_to_leave = array_diff($current_group_ids, $expected_group_ids);
    $group_ids_to_join = array_diff($expected_group_ids, $current_group_ids);

    foreach ($group_ids_to_leave as $group_id) {
      $this->userEndpoint->leaveGroup($user_id, $group_id);
    }

    foreach ($group_ids_to_join as $group_id) {
      $this->userEndpoint->joinGroup($user_id, $group_id);
    }
  }

  /**
   * Sets Nextcloud users for a specific group.
   *
   * @param string $group_id
   *   Group id.
   * @param string[] $user_ids
   *   Expected user ids that should be in the group.
   *   Any other users will be removed from the group.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Something went wrong in one of the API calls.
   */
  public function setGroupUsers(string $group_id, array $user_ids): void {
    $current_user_ids = $this->groupEndpoint->getUserIds($group_id);
    foreach (array_diff($current_user_ids, $user_ids) as $user_id) {
      $this->userEndpoint->leaveGroup($user_id, $group_id);
    }
    foreach (array_diff($user_ids, $current_user_ids) as $user_id) {
      $this->userEndpoint->joinGroup($user_id, $group_id);
    }
  }

}
