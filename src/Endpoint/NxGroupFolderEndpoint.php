<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Endpoint;

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\DataUtil;
use Drupal\poc_nextcloud\Exception\UnexpectedResponseDataException;
use Drupal\poc_nextcloud\NxEntity\NxGroupFolder;
use Drupal\poc_nextcloud\Response\OcsResponse;

/**
 * Endpoint for Nextcloud group folders.
 */
class NxGroupFolderEndpoint {

  /**
   * Connection with url for this endpoint.
   *
   * @var \Drupal\poc_nextcloud\Connection\ApiConnectionInterface
   */
  private ApiConnectionInterface $connection;

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Connection\ApiConnectionInterface $connection
   *   Connection.
   */
  public function __construct(ApiConnectionInterface $connection) {
    $this->connection = $connection->withPath('apps/groupfolders/folders');
  }

  /**
   * Creates a group folder.
   *
   * @param string $mountpoint
   *   Mount point.
   *
   * @return int
   *   New group folder id.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Unable to create.
   */
  public function insertWithMountPoint(string $mountpoint): int {
    return $this->connection
      ->requestOcs('POST', '', ['mountpoint' => $mountpoint])
      ->throwIfFailure()
      ->getData()['id'];
  }

  /**
   * Loads ids of all existing group folders.
   *
   * This is a convenience function.
   *
   * @return int[]
   *   Ids.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Failed to fetch ids.
   */
  public function loadIds(): array {
    return array_keys($this->loadAll());
  }

  /**
   * Fetches all existing group folders.
   *
   * @return \Drupal\poc_nextcloud\NxEntity\NxGroupFolder[]
   *   Nextcloud group folders.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Failed to fetch group folders.
   */
  public function loadAll(): array {
    $data = $this->connection
      ->requestOcs('GET')
      ->throwIfFailure()
      ->getData();
    return array_map(
      fn (array $record) => $this->createGroupFolderFromData($record),
      $data,
    );
  }

  /**
   * Loads a group folder.
   *
   * @param int $group_folder_id
   *   Group folder id.
   *
   * @return \Drupal\poc_nextcloud\NxEntity\NxGroupFolder|null
   *   Group folder, or NULL if not found.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function load(int $group_folder_id): NxGroupFolder|null {
    $response = $this->folderPath($group_folder_id)
      ->requestOcs('GET');
    if ($response->getData() === FALSE) {
      return NULL;
    }
    // Check if something else went wrong.
    $response->throwIfFailure();
    return $this->createGroupFolderFromData($response->getData());
  }

  /**
   * Deletes a group folder, or does nothing if not found.
   *
   * @param int $group_folder_id
   *   Group folder id.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function deleteIfExists(int $group_folder_id): void {
    $this->doDelete($group_folder_id)
      ->nullIfStatusCode(998)
      ?->throwIfFailure();
  }

  /**
   * Deletes a group folder, or fails if not found.
   *
   * @param int $group_folder_id
   *   Group folder id.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function delete(int $group_folder_id): void {
    $this->doDelete($group_folder_id)->throwIfFailure();
  }

  /**
   * Deletes a group folder, and gets the response object.
   *
   * @param int $group_folder_id
   *   Group folder id.
   *
   * @return \Drupal\poc_nextcloud\Response\OcsResponse
   *   Response object.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  protected function doDelete(int $group_folder_id): OcsResponse {
    return $this->folderPath($group_folder_id)
      ->requestOcs('DELETE');
  }

  /**
   * Sets the mount point of a group folder.
   *
   * @param int $group_folder_id
   *   Group folder id.
   * @param string $mountpoint
   *   Mount point.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function setMountPoint(int $group_folder_id, string $mountpoint): void {
    $this->folderPath($group_folder_id)
      ->requestOcs('PUT', '', [
        'mountPoint' => $mountpoint,
      ])
      ->throwIfFailure();
  }

  /**
   * Adds a group to a group folder.
   *
   * @param int $group_folder_id
   *   Group folder id.
   * @param string $group_id
   *   Group id.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function addGroup(int $group_folder_id, string $group_id): void {
    $this->folderPath($group_folder_id)
      ->requestOcs(
        'POST',
        '/groups',
        ['group' => $group_id],
      )
      ->throwIfFailure();
  }

  /**
   * Removes a group from a group folder.
   *
   * @param int $group_folder_id
   *   Group folder id.
   * @param string $group_id
   *   Group id.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function removeGroup(int $group_folder_id, string $group_id): void {
    $this->folderGroupPath($group_folder_id, $group_id)
      ->requestOcs('DELETE')
      ->throwIfFailure();
  }

  /**
   * Sets permissions for a specific group on the group folder.
   *
   * @param int $group_folder_id
   *   Group folder id.
   * @param string $group_id
   *   Group id.
   * @param int $permissions
   *   Permissions bitmask.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *
   * @see \Drupal\poc_nextcloud\NextcloudConstants
   */
  public function setGroupPermissions(int $group_folder_id, string $group_id, int $permissions): void {
    $this->folderGroupPath($group_folder_id, $group_id)
      ->requestOcs('POST', '', [
        'permissions' => $permissions,
      ])
      ->throwIfFailure();
  }

  /**
   * Toggles advanced permissions for a group folder.
   *
   * @param int $group_folder_id
   *   Group folder id.
   * @param bool $acl
   *   TRUE to enable, FALSE to disable.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function setAcl(int $group_folder_id, bool $acl): void {
    $this->folderPath($group_folder_id)
      ->requestOcs(
        'POST',
        '/acl',
        ['acl' => $acl],
      )
      ->throwIfFailure();
  }

  /**
   * Sets per-file access management permission for a group.
   *
   * This is a shortcut for the method below.
   *
   * @param int $group_folder_id
   *   Group folder id.
   * @param string $group_id
   *   Group id.
   * @param bool $manage_acl
   *   TRUE to enable, FALSE to disable.
   *   Calling this with TRUE results in exception, if already enabled.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *
   * @todo Should this be part of the endpoint class?
   */
  public function setManageAclGroup(int $group_folder_id, string $group_id, bool $manage_acl): void {
    $this->setManageAcl($group_folder_id, 'group', $group_id, $manage_acl);
  }

  /**
   * Sets per-file access management permission for a user.
   *
   * This is a shortcut for the method below.
   *
   * @param int $group_folder_id
   *   Group folder id.
   * @param string $user_id
   *   User id.
   * @param bool $manage_acl
   *   TRUE to enable, FALSE to disable.
   *   Calling this with TRUE results in exception, if already enabled.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *
   * @todo Should this be part of the endpoint class?
   */
  public function setManageAclUser(int $group_folder_id, string $user_id, bool $manage_acl): void {
    $this->setManageAcl($group_folder_id, 'user', $user_id, $manage_acl);
  }

  /**
   * Sets per-file access management permission for a group or user.
   *
   * @param int $group_folder_id
   *   Group folder id.
   * @param string $mapping_type
   *   One of 'user' or 'group'.
   * @param string $mapping_id
   *   Group id or user id, depending on mapping type.
   * @param bool $manage_acl
   *   TRUE to enable, FALSE to disable.
   *   Calling this with TRUE results in exception, if already enabled.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function setManageAcl(int $group_folder_id, string $mapping_type, string $mapping_id, bool $manage_acl): void {
    $this->folderPath($group_folder_id)
      ->requestOcs('POST', '/manageACL', [
        'mappingType' => $mapping_type,
        'mappingId' => $mapping_id,
        'manageAcl' => $manage_acl,
      ])
      ->throwIfFailure();
  }

  /**
   * Creates a new group folder object from response data.
   *
   * @param array $data
   *   Response data.
   *
   * @return \Drupal\poc_nextcloud\NxEntity\NxGroupFolder
   *   New group folder object
   *
   * @throws \Drupal\poc_nextcloud\Exception\UnexpectedResponseDataException
   */
  private function createGroupFolderFromData(array $data): NxGroupFolder {
    if (!isset($data['id'])) {
      throw new UnexpectedResponseDataException(sprintf(
        'Missing group folder id in response data. Existing keys: %s.',
        implode(array_keys($data)),
      ));
    }
    if (!isset($data['mount_point'])) {
      throw new UnexpectedResponseDataException('Missing group folder mount point in response data.');
    }
    $manage_acl = [];
    foreach ($data['manage'] as $record) {
      if (!$record) {
        // The Nextcloud database includes orphan records.
        // See https://github.com/nextcloud/groupfolders/issues/2261.
        continue;
      }
      $manage_acl[$record['type']][$record['id']] = $record['displayname'];
    }
    try {
      return new NxGroupFolder(
        $data['id'],
        $data['mount_point'],
        $data['groups'] ?? [],
        DataUtil::toIntIfPossible($data['quota']) ?? 0,
        DataUtil::toIntIfPossible($data['size']) ?? 0,
        $data['acl'] ?? FALSE,
        $manage_acl,
      );
    }
    catch (\TypeError $e) {
      throw new UnexpectedResponseDataException($e->getMessage(), 0, $e);
    }
  }

  /**
   * Gets a connection with url to target a specific group id on a group folder.
   *
   * @param int $group_folder_id
   *   Group folder id.
   * @param string $group_id
   *   Group id.
   *
   * @return \Drupal\poc_nextcloud\Connection\ApiConnectionInterface
   *   New connection instance.
   */
  private function folderGroupPath(int $group_folder_id, string $group_id): ApiConnectionInterface {
    return $this->folderPath($group_folder_id, '/groups/' . urlencode($group_id));
  }

  /**
   * Gets a connection with url to target a specific group folder.
   *
   * @param int $group_folder_id
   *   Group folder id.
   * @param string $sub_path
   *   Additional path part to append.
   *
   * @return \Drupal\poc_nextcloud\Connection\ApiConnectionInterface
   *   New connection instance.
   */
  private function folderPath(int $group_folder_id, string $sub_path = ''): ApiConnectionInterface {
    return $this->connection->withPath($group_folder_id . $sub_path);
  }

}
