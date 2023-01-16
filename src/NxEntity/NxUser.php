<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\NxEntity;

use Drupal\poc_nextcloud\Endpoint\EntityEndpoint;
use Drupal\poc_nextcloud\Exception\MalformedDataException;
use Drupal\poc_nextcloud\Exception\NextcloudApiException;

/**
 * Value object for a user entity.
 */
class NxUser implements NxEntityInterface {

  /**
   * Constructor.
   *
   * @param array $data
   *   Data as from $response['ocs']['data'].
   *
   * @throws \Drupal\poc_nextcloud\Exception\MalformedDataException
   *   Provided data is invalid.
   *
   * @todo Store specific properties instead of the full array.
   */
  private function __construct(
    private array $data,
  ) {
    if (!isset($data['id'])) {
      throw new MalformedDataException('Missing user id.');
    }
    // Verify list of valid characters as reported by the API itself.
    if (!preg_match('#^[a-zA-Z0-9_\.@\-]+$#', $data['id'])) {
      throw new MalformedDataException("Invalid user id '$data[id]'.");
    }
  }

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
    return new self($data);
  }

  /**
   * Creates a stub user to be saved later.
   *
   * @param string $id
   *   User id / username.
   * @param string $pass
   *   Password.
   *
   * @return self
   *   New instance.
   */
  public static function createStubWithPass(string $id, string $pass): self {
    return new self([
      'id' => $id,
      'password' => $pass,
    ]);
  }

  /**
   * Creates a stub user to be saved later.
   *
   * @param string $id
   *   User name.
   * @param string $email
   *   User email.
   *
   * @return self
   *   New instance.
   */
  public static function createStubWithEmail(string $id, string $email): self {
    return new self([
      'id' => $id,
      'email' => $email,
    ]);
  }

  /**
   * Saves a stub user.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\EntityEndpoint $endpoint
   *   Endpoint for users.
   *
   * @return string
   *   User id.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Failed to create the new user.
   */
  public function save(EntityEndpoint $endpoint): string {
    $id = $endpoint->create(array_filter([
      'email' => $this->data['email'] ?? NULL,
      'userid' => $this->data['id'],
      'password' => $this->data['password'] ?? NULL,
      'groups' => ($this->data['groups'] ?? NULL) ?: NULL,
    ], fn ($value) => isset($value)));
    if ($id !== $this->getId()) {
      throw new NextcloudApiException(sprintf(
        'Expected user id %s, but API returned %s.',
        $this->getId(),
        $id,
      ));
    }
    return $id;
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
    return $this->data['id'];
  }

  /**
   * Gets whether the Nextcloud user account is enabled.
   *
   * @return bool
   *   TRUE if enabled.
   */
  public function isEnabled(): bool {
    return !empty($this->data['enabled']);
  }

  /**
   * Gets the display name, which is usually identical with the username.
   *
   * @return string
   *   User display name.
   *
   * @todo Remove this, it seems we don't need it.
   */
  public function getDisplayName(): string {
    return $this->data['displayname'];
  }

  /**
   * Gets the email address.
   *
   * @return string
   *   User email.
   */
  public function getEmail(): string {
    return $this->data['email'];
  }

}
