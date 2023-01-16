<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\NxEntity;

use Drupal\poc_nextcloud\Endpoint\EntityEndpoint;

/**
 * Value object for a group folder loaded from the API.
 */
class NxGroupFolder implements NxEntityInterface {

  /**
   * Constructor.
   *
   * @param array $data
   *   Data from the response.
   */
  private function __construct(
    protected array $data,
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
    return new self($data);
  }

  /**
   * Creates a stub object with a given mount point.
   *
   * @param string $mountpoint
   *   Mount point.
   *
   * @return self
   *   New instance.
   */
  public static function createStubWithMountpoint(string $mountpoint): self {
    return new self(['mount_point' => $mountpoint]);
  }

  /**
   * {@inheritdoc}
   */
  public function save(EntityEndpoint $endpoint): string|int {
    return $endpoint->create(array_filter([
      'mountpoint' => $this->data['mount_point'],
    ], fn ($value) => isset($value)));
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string|int {
    return $this->data['id'];
  }

  /**
   * Gets the mount point of the group folder.
   *
   * @return string
   *   The mount point.
   */
  public function getMountPoint(): string {
    return $this->data['mount_point'];
  }

  /**
   * Gets the groups.
   *
   * @return array
   *   Groups.
   *
   * @todo Add more information, or remove.
   */
  public function getGroups(): array {
    return $this->data['groups'] ?? [];
  }

  /**
   * Gets the quota.
   *
   * @return int|null
   *   Quota.
   *
   * @todo Add more information, or remove.
   */
  public function getQuota(): ?int {
    return $this->parseIntIfPossible($this->data['quota'] ?? NULL);
  }

  /**
   * Gets the size.
   *
   * @return int|null
   *   Size, or NULL if not provided.
   *
   * @todo Add more information, or remove.
   */
  public function getSize(): ?int {
    return $this->parseIntIfPossible($this->data['size'] ?? NULL);
  }

  /**
   * Gets the access control setting.
   *
   * @return bool|null
   *   Access control setting.
   *
   * @todo Add more information, or remove.
   */
  public function getAcl(): ?bool {
    return $this->data['acl'] ?? NULL;
  }

  /**
   * Gets the 'manage' setting.
   *
   * @return array
   *   Setting for 'manage'.
   *
   * @todo Add more information, or remove.
   */
  public function getManage(): array {
    return $this->data['manage'] ?? [];
  }

  /**
   * Converts stringified integers to true integers.
   *
   * @param mixed $value
   *   A value which could be a stringified integer, e.g. "6".
   *
   * @return mixed
   *   The converted value, e.g. 6 for "6".
   *   If the value was not a stringified integer, the original value is
   *   returned.
   */
  private function parseIntIfPossible(mixed $value): mixed {
    if (is_string($value)) {
      if ($value === '') {
        return NULL;
      }
      if ((string) (int) $value === $value) {
        return (int) $value;
      }
    }
    return $value;
  }

}
