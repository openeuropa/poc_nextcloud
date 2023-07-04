<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Endpoint;

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\NxEntity\NxGroup;

/**
 * Endpoint for Nextcloud groups.
 *
 * @see https://docs.nextcloud.com/server/latest/admin_manual/configuration_user/instruction_set_for_groups.html
 */
class NxGroupEndpoint {

  /**
   * Connection with url for the endpoint.
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
    $this->connection = $connection->withPath('ocs/v1.php/cloud/groups');
  }

  /**
   * Creates a new group.
   *
   * @param string $group_id
   *   Group id.
   * @param string|null $display_name
   *   Display name.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function insert(string $group_id, string $display_name = NULL): void {
    $this->connection
      ->requestOcs('POST', '', array_diff([
        'groupid' => $group_id,
        'displayname' => $display_name,
      ], [NULL]))
      ->throwIfFailure();
  }

  /**
   * Sets the group display name.
   *
   * @param string $group_id
   *   Group id.
   * @param string $display_name
   *   Display name.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function setDisplayName(string $group_id, string $display_name): void {
    $this->updateField($group_id, 'displayname', $display_name);
  }

  /**
   * Updates a specific field in the group.
   *
   * The only supported field is 'displayname', so therefore it is not useful to
   * expose this as a public method.
   *
   * @param string $group_id
   *   Group id.
   * @param string $key
   *   Field name.
   * @param string $value
   *   New field value.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  protected function updateField(string $group_id, string $key, string $value): void {
    $this->groupPath($group_id)
      ->requestOcs('PUT', '', [
        'key' => $key,
        'value' => $value,
      ])
      ->throwIfFailure();
  }

  /**
   * Deletes a group, if it exists.
   *
   * @param string $group_id
   *   Group id.
   *
   * @return bool
   *   TRUE if a group was deleted, FALSE if it did not exist.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   The API did not behave as expected.
   */
  public function delete(string $group_id): bool {
    return NULL !== $this->groupPath($group_id)
      ->requestOcs('DELETE')
      ->nullIfStatusCode(101)
      ?->throwIfFailure();
  }

  /**
   * Checks if a group id exists.
   *
   * This is a convenience method, because the API lacks a dedicated route for
   * this purpose.
   *
   * @param string $group_id
   *   The group id to check.
   *
   * @return bool
   *   TRUE if it exists, FALSE if not.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function idExists(string $group_id): bool {
    $ids = $this->loadIds($group_id);
    return in_array($group_id, $ids);
  }

  /**
   * Gets all (matching) groups ids.
   *
   * @param string|null $search
   *   Search query, or NULL to not filter the result.
   * @param int|null $limit
   *   Limit, or NULL to not limit the result.
   * @param int|null $offset
   *   Offset, or NULL to start at the beginning.
   *
   * @return string[]
   *   Group ids.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function loadIds(string $search = NULL, int $limit = NULL, int $offset = NULL): array {
    return $this->connection
      ->requestOcs('GET', '', array_diff([
        'search' => $search,
        'limit' => $limit,
        'offset' => $offset,
      ], [NULL]))
      ->throwIfFailure()
      ->getData()['groups'];
  }

  /**
   * Loads a single group.
   *
   * This is a convenience method, to compensate for a lack of a dedicated API
   * route.
   *
   * @param string $group_id
   *   The group id.
   *
   * @return \Drupal\poc_nextcloud\NxEntity\NxGroup|null
   *   The group, or NULL if not found.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function load(string $group_id): NxGroup|null {
    // Use the group id as a search filter.
    $ids = $this->loadIds($group_id);
    $index = array_keys($ids, $group_id)[0] ?? NULL;
    if ($index === NULL) {
      return NULL;
    }
    $group = $this->loadGroups($group_id, 1, $index)[0] ?? NULL;
    if ($group !== NULL && $group->getId() === $group_id) {
      return $group;
    }
    // The above didn't work, try the more expensive way.
    $groups = $this->loadGroups($group_id);
    foreach ($groups as $group) {
      if ($group->getId() === $group_id) {
        return $group;
      }
    }
    return NULL;
  }

  /**
   * Gets all (matching) groups.
   *
   * @param string|null $search
   *   Search query, or NULL to not filter the result.
   * @param int|null $limit
   *   Limit, or NULL to not limit the result.
   * @param int|null $offset
   *   Offset, or NULL to start at the beginning.
   *
   * @return \Drupal\poc_nextcloud\NxEntity\NxGroup[]
   *   Group objects.
   *   These contain additional information that is not part of the group
   *   itself.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function loadGroups(string $search = NULL, int $limit = NULL, int $offset = NULL): array {
    $records = $this->connection
      ->requestOcs('GET', '/details', array_diff([
        'search' => $search,
        'limit' => $limit,
        'offset' => $offset,
      ], [NULL]))
      ->throwIfFailure()
      ->getData()['groups'];
    return array_map(static fn (array $record) => new NxGroup(
      $record['id'],
      $record['displayname'],
      $record['usercount'],
      $record['disabled'],
      $record['canAdd'],
      $record['canRemove'],
    ), $records);
  }

  /**
   * Gets users in the given group.
   *
   * @param string $group_id
   *   Group id.
   *
   * @return string[]
   *   User ids.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function getUserIds(string $group_id): array {
    return $this->groupPath($group_id)
      ->requestOcs('GET')
      ->throwIfFailure()
      ->getData()['users'];
  }

  /**
   * Gets a connection with path for a specific group.
   *
   * @param string $group_id
   *   Group id.
   *
   * @return \Drupal\poc_nextcloud\Connection\ApiConnectionInterface
   *   New connection object.
   */
  private function groupPath(string $group_id): ApiConnectionInterface {
    return $this->connection
      ->withPath(rawurlencode($group_id));
  }

}
