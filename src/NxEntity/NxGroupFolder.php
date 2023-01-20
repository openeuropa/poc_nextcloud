<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\NxEntity;

use Drupal\poc_nextcloud\Exception\MalformedDataException;

/**
 * Value object for a group folder loaded from the API.
 */
class NxGroupFolder implements NxEntityInterface {

  /**
   * Constructor.
   *
   * @param int|null $id
   *   Id, or NULL if this is a stub group folder.
   * @param string $mountPoint
   *   Mount point.
   * @param array $groups
   *   Groups.
   * @param int|null $quota
   *   Quota.
   * @param int|null $size
   *   Size.
   * @param bool|null $acl
   *   ACL.
   * @param array $manage
   *   Manage.
   */
  private function __construct(
    private ?int $id,
    private string $mountPoint,
    private array $groups = [],
    private ?int $quota = NULL,
    private ?int $size = NULL,
    private ?bool $acl = NULL,
    private array $manage = [],
  ) {}

  /**
   * Creates a new user object from response data.
   *
   * @param array $data
   *   Response data from a request to load a group folder.
   *
   * @return self
   *   New instance.
   *
   * @throw MalformedDataExceptio
   */
  public static function fromResponseData(array $data): self {
    if (!isset($data['id'])) {
      throw new MalformedDataException('Missing group folder id in response data.');
    }
    if (!isset($data['mount_point'])) {
      throw new MalformedDataException('Missing group folder mount point in response data.');
    }
    try {
      return new self(
        $data['id'],
        $data['mount_point'],
        $data['groups'] ?? [],
        self::parseIntIfPossible($data['quota'] ?? NULL),
        self::parseIntIfPossible($data['size'] ?? NULL),
        $data['acl'] ?? NULL,
        $data['manage'] ?? [],
      );
    }
    catch (\TypeError $e) {
      throw new MalformedDataException($e->getMessage(), 0, $e);
    }
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
  public static function createWithMountPoint(string $mountpoint): self {
    return new self(NULL, $mountpoint);
  }

  /**
   * {@inheritdoc}
   */
  public function exportForInsert(): array {
    return array_filter([
      'mountpoint' => $this->mountPoint,
    ], fn ($value) => isset($value));
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string|int|null {
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
   * Gets the groups.
   *
   * @return array
   *   Groups.
   *
   * @todo Add more information, or remove.
   */
  public function getGroups(): array {
    return $this->groups;
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
    return $this->quota;
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
    return $this->size;
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
    return $this->acl;
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
    return $this->manage;
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
  private static function parseIntIfPossible(mixed $value): mixed {
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

  /**
   * {@inheritdoc}
   */
  public function isStub(): bool {
    return $this->id === NULL;
  }

}
