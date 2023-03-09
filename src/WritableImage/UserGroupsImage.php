<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\WritableImage;

use Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;

/**
 * Image to let a user join groups.
 *
 * After the image is written, the user's group ids, filtered by the namespace,
 * will be exactly as defined in the image.
 */
class UserGroupsImage {

  /**
   * Map of group ids.
   *
   * @var true[]
   */
  private array $groupIdsMap = [];

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $userEndpoint
   *   User endpoint.
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint $groupEndpoint
   *   Group endpoint.
   * @param string $userId
   *   User id.
   * @param string $groupIdRegex
   *   Group id namespace.
   */
  public function __construct(
    private NxUserEndpoint $userEndpoint,
    private NxGroupEndpoint $groupEndpoint,
    private string $userId,
    private string $groupIdRegex,
  ) {}

  /**
   * Adds a group.
   *
   * @param string $group_id
   *   Nextcloud group id.
   */
  public function addGroup(string $group_id): void {
    if (!preg_match($this->groupIdRegex, $group_id)) {
      throw new \InvalidArgumentException(sprintf(
        "Group id '%s' does not match namespace pattern '%s'.",
        $group_id,
        $this->groupIdRegex,
      ));
    }
    $this->groupIdsMap[$group_id] = TRUE;
  }

  /**
   * Writes the image to Nextcloud.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function write(): void {
    $group_ids = array_keys($this->groupIdsMap);
    $existing_group_ids = $this->groupEndpoint->loadIds();
    $group_ids = array_intersect($group_ids, $existing_group_ids);
    $current_group_ids = $this->userEndpoint->getGroupIds($this->userId);
    $current_group_ids = preg_grep($this->groupIdRegex, $current_group_ids);
    $group_ids_to_leave = array_diff($current_group_ids, $group_ids);
    $group_ids_to_join = array_diff($group_ids, $current_group_ids);

    foreach ($group_ids_to_leave as $group_id) {
      $this->userEndpoint->leaveGroup($this->userId, $group_id);
    }

    foreach ($group_ids_to_join as $group_id) {
      $this->userEndpoint->joinGroup($this->userId, $group_id);
    }
  }

}
