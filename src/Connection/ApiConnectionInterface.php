<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Connection;

use Drupal\poc_nextcloud\Response\OcsResponse;

/**
 * Connection to the Nextcloud API.
 */
interface ApiConnectionInterface {

  /**
   * Makes a request to the API.
   *
   * @param string $method
   *   One of 'GET' or 'POST'.
   * @param string $path
   *   Path relative to the API base url.
   *   E.g. 'ocs/v1.php/cloud/users' to create a Nextcloud user.
   * @param array $params
   *   Request parameters.
   *
   * @return \Drupal\poc_nextcloud\Response\OcsResponse
   *   Response data (parsed json).
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Request failed.
   */
  public function request(string $method, string $path, array $params = []): OcsResponse;

}
