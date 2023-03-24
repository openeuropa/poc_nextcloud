<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Endpoint;

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\Exception\NextcloudApiException;
use GuzzleHttp\RequestOptions;

/**
 * Endpoint for Nextcloud WebDAV.
 *
 * For now this contains only a small subset of the WebDAV API.
 *
 * @see https://docs.nextcloud.com/server/latest/developer_manual/client_apis/WebDAV/
 */
class NxWebdavEndpoint {

  /**
   * Connection instance with url for the users API.
   *
   * @var \Drupal\poc_nextcloud\Connection\ApiConnectionInterface
   */
  private ApiConnectionInterface $connection;

  /**
   * User id for which to target the files.
   *
   * @var string|null
   */
  private ?string $userId;

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Connection\ApiConnectionInterface $connection
   *   API connection.
   */
  public function __construct(ApiConnectionInterface $connection) {
    $this->connection = $connection->withPath('remote.php/dav');
  }

  /**
   * Gets a new instance with changed user id.
   *
   * @param string $user_id
   *   User id.
   *
   * @return static
   *   New instance.
   */
  public function withUserId(string $user_id): static {
    $clone = clone $this;
    $clone->userId = $user_id;
    return $clone;
  }

  /**
   * Writes a file in the current user's directory.
   *
   * @param string $path
   *   File path within the user directory.
   * @param string $content
   *   File content to write.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function writeFile(string $path, string $content): void {
    $this->userPath('files')
      ->withRequestOption(RequestOptions::BODY, $content)
      ->request('PUT', $path);
  }

  /**
   * Gets a new connection object with url for the current user.
   *
   * @param string $prefix
   *   Path part to insert before the user id.
   *   E.g. 'files'.
   *
   * @return \Drupal\poc_nextcloud\Connection\ApiConnectionInterface
   *   Connection object with adjusted url.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  protected function userPath(string $prefix): ApiConnectionInterface {
    $user_id = $this->userId ?? $this->connection->getUserId();
    if ($user_id === NULL) {
      throw new NextcloudApiException('Cannot build a path without a user id.');
    }
    return $this->connection->withPath($prefix . '/' . rawurlencode($user_id));
  }

}
