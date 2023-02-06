<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\NxEntity;

/**
 * Value object for a Nextcloud group folder loaded from the API.
 *
 * This assumes the 'groupfolders' app is installed in Nextcloud.
 */
class NxGroupFolder {

  /**
   * Constructor.
   *
   * @param int $id
   *   Group folder id.
   * @param string $mountPoint
   *   Mount point.
   * @param int[] $groupsPerms
   *   Groups permission masks by group id.
   * @param int $quota
   *   Quota.
   * @param int $size
   *   Size.
   * @param bool $acl
   *   TRUE, if per-file ACL is enabled for this group folder.
   * @param string[][] $manageAcl
   *   Group and users with permission to manage access on individual files.
   *   First key is the type ('group' or 'user'), second key is the id, value is
   *   the display name.
   *   Example: $['group'][$group_id] = $group_display_name.
   *   The format is different than the API response, because it is more useful
   *   this way.
   */
  public function __construct(
    private int $id,
    private string $mountPoint,
    private array $groupsPerms,
    private int $quota,
    private int $size,
    private bool $acl,
    private array $manageAcl,
  ) {}

  /**
   * Gets the group folder id.
   *
   * @return int
   *   Id of the group folder.
   */
  public function getId(): int {
    return $this->id;
  }

  /**
   * Gets the mount point of the group folder.
   *
   * @return string
   *   The mount point.
   */
  public function getMountPoint(): string {
    return $this->mountPoint;
  }

  /**
   * Gets group ids linked to the group folder.
   *
   * @return string[]
   *   Group ids.
   */
  public function getGroupIds(): array {
    return array_keys($this->groupsPerms);
  }

  /**
   * Gets group ids with permissions.
   *
   * @return int[]
   *   Group permission bitmasks by group id.
   */
  public function getGroupsPerms(): array {
    return $this->groupsPerms;
  }

  /**
   * Gets the quota.
   *
   * @return int
   *   Quota as found in filecache table.
   *
   * @todo Add more information, or remove.
   */
  public function getQuota(): int {
    return $this->quota;
  }

  /**
   * Gets the size.
   *
   * @return int
   *   Size as found in filecache table.
   *
   * @todo Add more information, or remove.
   */
  public function getSize(): int {
    return $this->size;
  }

  /**
   * Tells whether advanced ACL is enabled for this group folder.
   *
   * @return bool
   *   TRUE if enabled, FALSE if not.
   */
  public function isAclEnabled(): bool {
    return $this->acl;
  }

  /**
   * Gets groups and users that can manage access per file.
   *
   * @return string[][]
   *   Group and users with permission to manage access on individual files.
   *   First key is the type ('group' or 'user'), second key is the id, value is
   *   the display name.
   *   Example: $['group'][$group_id] = $group_display_name.
   */
  public function getAclManagerIdsByType(): array {
    return $this->manageAcl;
  }

  /**
   * Checks whether a group gives permission to manage access per file.
   *
   * @param string $group_id
   *   Group id.
   *
   * @return bool
   *   TRUE if the group has access.
   */
  public function groupIsAclManager(string $group_id): bool {
    return isset($this->manageAcl['group'][$group_id]);
  }

  /**
   * Checks whether a user has permission to manage access per file.
   *
   * @param string $user_id
   *   User id.
   *
   * @return bool
   *   TRUE if the group has access.
   */
  public function userIsAclManager(string $user_id): bool {
    return isset($this->manageAcl['user'][$user_id]);
  }

  /**
   * Gets groups with access to manage access per file.
   *
   * @return string[]
   *   Group display names by group id.
   */
  public function getAclManagerGroupIds(): array {
    return array_keys($this->manageAcl['group'] ?? []);
  }

  /**
   * Gets groups with access to manage access per file.
   *
   * @return string[]
   *   Group display names by group id.
   */
  public function getAclManagerUserIds(): array {
    return array_keys($this->manageAcl['user'] ?? []);
  }

}
