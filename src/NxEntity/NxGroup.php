<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\NxEntity;

/**
 * Value object for a Nextcloud group loaded from the API.
 */
class NxGroup {

  /**
   * Constructor.
   *
   * @param string $id
   *   Id.
   * @param string $displayName
   *   Display name.
   * @param int|false $userCount
   *   User count.
   * @param int|false $disabledCount
   *   Number of disabled users in the group.
   * @param bool $canAddUser
   *   TRUE if users can be added this group.
   * @param bool $canRemoveUser
   *   TRUE if users can be removed from this group.
   *
   * @todo Some of the explanations may need to be verified.
   */
  public function __construct(
    private string $id,
    private string $displayName,
    private int|false $userCount,
    private int|false $disabledCount,
    private bool $canAddUser,
    private bool $canRemoveUser,
  ) {}

  /**
   * Gets the group id.
   *
   * @return string
   *   The group id.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Gets the group display name.
   *
   * @return string
   *   Display name.
   */
  public function getDisplayName(): string {
    return $this->displayName;
  }

  /**
   * Gets the number of users.
   *
   * @return int|false
   *   Number of users, or FALSE if no backends exist that can count users.
   *
   * @todo Clarify this a bit more. E.g. does it include disabled users?
   */
  public function getUserCount(): int|false {
    return $this->userCount;
  }

  /**
   * Gets the number of disabled users.
   *
   * @return int|false
   *   Number of disabled users, or FALSE if no backends exist that can count
   *   disabled users.
   */
  public function getDisabledCount(): int|false {
    return $this->disabledCount;
  }

  /**
   * Tells whether users can be added to the group.
   *
   * @return bool
   *   TRUE if users can be added, FALSE if not.
   *
   * @todo Find out how this works and if it is relevant for us.
   */
  public function isCanAddUser(): bool {
    return $this->canAddUser;
  }

  /**
   * Tells whether users can be removed from the group.
   *
   * @return bool
   *   TRUE if users can be removed, FALSE if not.
   */
  public function isCanRemoveUser(): bool {
    return $this->canRemoveUser;
  }

}
