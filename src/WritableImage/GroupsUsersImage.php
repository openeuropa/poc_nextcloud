<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\WritableImage;

use Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;

/**
 * Image to let users join groups.
 */
class GroupsUsersImage {

  /**
   * Lists of user ids by group id.
   *
   * @var string[][]
   */
  private array $groupsUsers = [];

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint $groupEndpoint
   *   Group endpoint.
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $userEndpoint
   *   User endpoint.
   * @param string $groupIdRegex
   *   Pattern to define the group id namespace.
   */
  public function __construct(
    private NxGroupEndpoint $groupEndpoint,
    private NxUserEndpoint $userEndpoint,
    private string $groupIdRegex,
  ) {}

  /**
   * Adds a user to a group.
   *
   * @param string $user_id
   *   User id.
   * @param string $group_id
   *   Group id.
   */
  public function addUserGroup(string $user_id, string $group_id): void {
    if (!preg_match($this->groupIdRegex, $group_id)) {
      throw new \RuntimeException(sprintf(
        "Group id '%s' is not in namespace '%s'.",
        $group_id,
        $this->groupIdRegex,
      ));
    }
    $this->groupsUsers[$group_id][$user_id] = $user_id;
  }

  /**
   * Writes the image to Nextcloud.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function write(): void {
    $all_group_ids = $this->groupEndpoint->loadIds();
    $all_group_ids = preg_grep($this->groupIdRegex, $all_group_ids);
    foreach ($all_group_ids as $group_id) {
      $user_ids = $this->groupsUsers[$group_id] ?? [];
      $current_user_ids = $this->groupEndpoint->getUserIds($group_id);
      foreach (array_diff($current_user_ids, $user_ids) as $user_id) {
        $this->userEndpoint->leaveGroup($user_id, $group_id);
      }
      foreach (array_diff($user_ids, $current_user_ids) as $user_id) {
        $this->userEndpoint->joinGroup($user_id, $group_id);
      }
    }
  }

}
