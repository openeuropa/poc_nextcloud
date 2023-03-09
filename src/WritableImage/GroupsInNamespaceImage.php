<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\WritableImage;

use Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint;

/**
 * Writable image to create groups in a namespace.
 *
 * The namespace is defined by a regular expression.
 *
 * After the operation, the namespace will contain exactly the groups that were
 * added to the image. Other groups are removed.
 */
class GroupsInNamespaceImage {

  /**
   * Group ids with display names.
   *
   * @var string[]
   */
  private array $groupDisplayNames = [];

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint $groupEndpoint
   *   Group endpoint.
   * @param string $groupIdRegex
   *   Groups namespace.
   */
  public function __construct(
    private NxGroupEndpoint $groupEndpoint,
    private string $groupIdRegex,
  ) {}

  /**
   * Adds a group that should exist after the image is written.
   *
   * @param string $group_id
   *   Group id.
   * @param string $display_name
   *   Group display name.
   */
  public function addGroup(string $group_id, string $display_name): void {
    $this->groupDisplayNames[$group_id] = $display_name;
  }

  /**
   * Writes the image to Nextcloud.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function write(): void {
    $existing_ids = $this->groupEndpoint->loadIds();
    $existing_ids = preg_grep($this->groupIdRegex, $existing_ids);
    $expected_ids = array_keys($this->groupDisplayNames);

    $group_ids_to_delete = array_diff($existing_ids, $expected_ids);
    foreach ($group_ids_to_delete as $group_id) {
      $this->groupEndpoint->delete($group_id);
    }

    $group_ids_to_create = array_diff($expected_ids, $existing_ids);
    foreach ($group_ids_to_create as $group_id) {
      $this->groupEndpoint->insert($group_id, $this->groupDisplayNames[$group_id]);
    }

    // Update the already-existing groups.
    foreach (array_intersect($expected_ids, $existing_ids) as $group_id) {
      $this->groupEndpoint->setDisplayName($group_id, $this->groupDisplayNames[$group_id]);
    }
  }

}
