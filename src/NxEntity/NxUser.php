<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\NxEntity;

/**
 * Value object for a Nextcloud user loaded from the API.
 *
 * Currently this is an incomplete subset of what the API returns.
 */
class NxUser {

  /**
   * Constructor.
   *
   * @param string $id
   *   User id.
   * @param bool $enabled
   *   Whether the Nextcloud account is "enabled".
   * @param string $displayName
   *   User display name.
   * @param string|null $email
   *   User email, or NULL if none is set.
   * @param string[] $groupIds
   *   List of group ids the user is a member of.
   */
  public function __construct(
    private string $id,
    private bool $enabled,
    private string $displayName,
    private ?string $email,
    private array $groupIds,
  ) {}

  /**
   * Creates a new user object from response data.
   *
   * @param array $data
   *   Response data from a "get user" request.
   *
   * @return self
   *   New instance.
   */
  public static function fromResponseData(array $data): self {
    return new self(
      $data['id'],
      $data['enabled'],
      $data['displayname'],
      $data['email'],
      $data['groups'],
    );
  }

  /**
   * Gets the Nextcloud user id.
   *
   * This is usually a username.
   *
   * @return string
   *   E.g. "Philippe".
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Gets whether the Nextcloud user account is enabled.
   *
   * @return bool
   *   TRUE if enabled.
   */
  public function isEnabled(): bool {
    return $this->enabled;
  }

  /**
   * Gets the display name, which is usually identical with the username.
   *
   * @return string|null
   *   User display name.
   *
   * @todo Remove this, it seems we don't need it.
   */
  public function getDisplayName(): ?string {
    return $this->displayName;
  }

  /**
   * Gets the email address.
   *
   * @return string|null
   *   User email.
   */
  public function getEmail(): ?string {
    return $this->email;
  }

  /**
   * Gets the group ids the user is a member of.
   *
   * @return string[]
   *   List of group ids.
   */
  public function getGroupIds(): array {
    return $this->groupIds;
  }

}
