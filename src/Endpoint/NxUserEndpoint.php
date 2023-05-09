<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Endpoint;

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\Exception\NextcloudApiException;
use Drupal\poc_nextcloud\NxEntity\NxUser;
use Drupal\poc_nextcloud\Response\OcsResponse;

/**
 * Endpoint for Nextcloud users.
 *
 * @see https://docs.nextcloud.com/server/latest/admin_manual/configuration_user/instruction_set_for_users.html
 */
class NxUserEndpoint {

  /**
   * Connection instance with url for the users API.
   *
   * @var \Drupal\poc_nextcloud\Connection\ApiConnectionInterface
   */
  private ApiConnectionInterface $connection;

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Connection\ApiConnectionInterface $connection
   *   API connection.
   */
  public function __construct(ApiConnectionInterface $connection) {
    $this->connection = $connection->withPath('ocs/v1.php/cloud/users');
  }

  /**
   * Creates a new user account with password.
   *
   * @param string $name
   *   User id.
   * @param string $pass
   *   Password.
   *
   * @return string
   *   New user id.
   *   This should be identical to the $user_id parameter.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function insertWithPassword(string $name, string $pass): string {
    return $this->insertValues([
      'userid' => $name,
      'password' => $pass,
    ]);
  }

  /**
   * Creates a new user with email.
   *
   * @param string $userid
   *   User id.
   * @param string $email
   *   Email address.
   * @param string|null $displayName
   *   Display name, or NULL to leave it empty.
   *
   * @return string
   *   New user id.
   *   This should be identical to the $user_id parameter.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function insertWithEmail(
    string $userid,
    string $email,
    string $displayName = NULL,
  ): string {
    return $this->insertValues(compact(
      'userid',
      'email',
      'displayName',
    ));
  }

  /**
   * Creates a Nextcloud entity from an array of values.
   *
   * @param array $values
   *   Values for the new entity.
   *
   * @return string
   *   New user id.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Failed to create the new entity.
   *
   * @internal
   */
  public function insertValues(array $values): string {
    if (empty($values['userid'])) {
      throw new NextcloudApiException('User id is required when creating a new user.');
    }
    $new_id = $this->connection->requestOcs(
      'POST',
      '',
      array_diff($values, [NULL]),
    )
      ->throwIfFailure()
      ->getData()['id'];
    if ($new_id !== $values['userid']) {
      throw new NextcloudApiException(sprintf(
        'Expected new id %s, found %s.',
        $values['userid'],
        $new_id,
      ));
    }
    return $new_id;
  }

  /**
   * Deletes a user, if the user exists.
   *
   * @param string $user_id
   *   User id.
   *
   * @return bool
   *   TRUE if user was deleted, FALSE if user did not exist.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Something went wrong.
   */
  public function deleteIfExists(string $user_id): bool {
    $response = $this->doDelete($user_id)
      ->nullIfStatusCode(998)
      ?->throwIfFailure();
    return $response !== NULL;
  }

  /**
   * Deletes a user, or fails if the user does not exist.
   *
   * @param string $user_id
   *   User id.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   User does not exist, or something else went wrong.
   */
  public function delete(string $user_id): void {
    $this->doDelete($user_id)->throwIfFailure();
  }

  /**
   * Deletes a user, and gets the response object.
   *
   * @param string $user_id
   *   User id.
   *
   * @return \Drupal\poc_nextcloud\Response\OcsResponse
   *   Response object.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  protected function doDelete(string $user_id): OcsResponse {
    return $this->userPath($user_id)
      ->requestOcs('DELETE');
  }

  /**
   * Loads user ids.
   *
   * @return string[]
   *   User ids.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function loadIds(): array {
    return $this->connection->requestOcs('GET')
      ->throwIfFailure()
      ->getData()['users'];
  }

  /**
   * Loads a user from the API.
   *
   * @param string $user_id
   *   User id.
   *
   * @return \Drupal\poc_nextcloud\NxEntity\NxUser|null
   *   User object, or NULL if not found.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function load(string $user_id): NxUser|null {
    $response = $this->userPath($user_id)
      ->requestOcs('GET');
    if ($response->getStatusCode() === 404) {
      return NULL;
    }
    // Check if something else went wrong.
    $response->throwIfFailure();
    $user = $this->createUserFromData($response->getData());
    if ($user->getId() !== $user_id) {
      throw new NextcloudApiException(sprintf('Loaded id is %s, while expected id is %s.', $user->getId(), $user_id));
    }
    return $user;
  }

  /**
   * Sets the user email.
   *
   * @param string $user_id
   *   User id.
   * @param string|null $email
   *   Email, or NULL to remove the email.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function setUserEmail(string $user_id, ?string $email): void {
    $this->setUserField($user_id, 'email', $email);
  }

  /**
   * Sets the user display name.
   *
   * @param string $user_id
   *   User id.
   * @param string|null $display_name
   *   Display name, or NULL to unset.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function setUserDisplayName(string $user_id, ?string $display_name): void {
    $this->setUserField($user_id, 'displayname', $display_name);
  }

  /**
   * Sets a field of the user account.
   *
   * @param string $user_id
   *   User id.
   * @param string $field
   *   Field name.
   *   The user API provides a wide range of fields, that cannot be documented
   *   here. If the field does not exist, the response will have code 103.
   * @param mixed $value
   *   Value to set for the field.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Field does not exist, or something else went wrong.
   */
  public function setUserField(string $user_id, string $field, mixed $value): void {
    $this->userPath($user_id)
      ->requestOcs('PUT', '', [
        'key' => $field,
        'value' => $value,
      ])
      ->throwIfFailure();
  }

  /**
   * Gets the user's group ids.
   *
   * @param string $user_id
   *   User id.
   *
   * @return string[]
   *   Group ids.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function getGroupIds(string $user_id): array {
    return $this->userGroupsPath($user_id)
      ->requestOcs('GET')
      ->throwIfFailure()
      ->getData()['groups'];
  }

  /**
   * Adds a user to a group.
   *
   * @param string $user_id
   *   User id.
   * @param string $group_id
   *   Group id.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function joinGroup(string $user_id, string $group_id): void {
    $this->userGroupsPath($user_id, $group_id)
      ->requestOcs('POST')
      ->throwIfFailure();
  }

  /**
   * Removes a user from a group.
   *
   * @param string $user_id
   *   User id.
   * @param string $group_id
   *   Group id.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function leaveGroup(string $user_id, string $group_id): void {
    $this->userGroupsPath($user_id, $group_id)
      ->requestOcs('DELETE')
      ->throwIfFailure();
  }

  /**
   * Gets group ids where the user is a subadmin.
   *
   * @param string $user_id
   *   User id.
   *
   * @return string[]
   *   Group ids.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function getSubadminGroupIds(string $user_id): array {
    return $this->userSubadminsPath($user_id)
      ->requestOcs('GET')
      ->throwIfFailure()
      ->getData();
  }

  /**
   * Adds a user to a group as subadmin.
   *
   * @param string $user_id
   *   User id.
   * @param string $group_id
   *   Group id.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function joinSubadminGroup(string $user_id, string $group_id): void {
    $this->userSubadminsPath($user_id, $group_id)
      ->requestOcs('POST')
      ->throwIfFailure();
  }

  /**
   * Removes a user from a group as subadmin.
   *
   * @param string $user_id
   *   User id.
   * @param string $group_id
   *   Group id.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function leaveSubadminGroup(string $user_id, string $group_id): void {
    $this->userSubadminsPath($user_id, $group_id)
      ->requestOcs('DELETE')
      ->throwIfFailure();
  }

  /**
   * Creates a new user object from response data.
   *
   * @param array $data
   *   Response data from a "get user" request.
   *
   * @return \Drupal\poc_nextcloud\NxEntity\NxUser
   *   New instance.
   */
  private function createUserFromData(array $data): NxUser {
    return new NxUser(
      $data['id'],
      $data['enabled'],
      $data['displayname'],
      $data['email'],
      $data['groups'],
    );
  }

  /**
   * Gets a connection with url to manage groups of the user.
   *
   * @param string $user_id
   *   User id.
   * @param string|null $group_id
   *   Group id, or NULL.
   *   If provided, this is added as a query parameter, as needed for some of
   *   the user group routes.
   *
   * @return \Drupal\poc_nextcloud\Connection\ApiConnectionInterface
   *   Connection object with adjusted url.
   */
  private function userGroupsPath(string $user_id, string $group_id = NULL): ApiConnectionInterface {
    $ret = $this->userPath($user_id, '/groups');
    if ($group_id !== NULL) {
      $ret = $ret->withFormValues(['groupid' => $group_id]);
    }
    return $ret;
  }

  /**
   * Gets a new connection object with url to manage user subadmins.
   *
   * @param string $user_id
   *   User id.
   * @param string|null $group_id
   *   Group id, or NULL.
   *   If provided, this is added as a query parameter, as needed for some of
   *   the user group routes.
   *
   * @return \Drupal\poc_nextcloud\Connection\ApiConnectionInterface
   *   Connection object with adjusted url.
   */
  private function userSubadminsPath(string $user_id, string $group_id = NULL): ApiConnectionInterface {
    $ret = $this->userPath($user_id, '/subadmins');
    if ($group_id !== NULL) {
      $ret = $ret->withFormValues(['groupid' => $group_id]);
    }
    return $ret;
  }

  /**
   * Gets a new connection object with url for a specific user.
   *
   * @param string $user_id
   *   User id.
   * @param string $path
   *   Suffix to append to the path.
   *
   * @return \Drupal\poc_nextcloud\Connection\ApiConnectionInterface
   *   Connection object with adjusted url.
   */
  private function userPath(string $user_id, string $path = ''): ApiConnectionInterface {
    return $this->connection->withPath(rawurlencode($user_id) . $path);
  }

}
