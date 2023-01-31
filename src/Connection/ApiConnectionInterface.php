<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Connection;

use Drupal\poc_nextcloud\Response\OcsResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * Connection to the Nextcloud API.
 */
interface ApiConnectionInterface {

  /**
   * Gets a new instance with a path suffix appended to the url.
   *
   * @param string $prefix
   *   Path suffix, without leading or trailing slash.
   *
   * @return static
   *   New instance.
   */
  public function path(string $prefix): static;

  /**
   * Gets a new instance with the given query parameters.
   *
   * @param array $query
   *   Parameters for GET or POST.
   *
   * @return static
   *   New instance.
   */
  public function query(array $query): static;

  /**
   * Makes a request to the API.
   *
   * This is for requests that expect a standardized OCS response array.
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
   *
   * @todo Consider to split this into a different interface.
   */
  public function requestOcs(string $method, string $path = '', array $params = []): OcsResponse;

  /**
   * Makes a request to the API. Gets raw json data.
   *
   * This is for requests that expect a random array.
   *
   * @param string $method
   *   One of 'GET' or 'POST'.
   * @param string $path
   *   Path relative to the API base url.
   *   E.g. 'ocs/v1.php/cloud/users' to create a Nextcloud user.
   * @param array $query
   *   Request parameters.
   *
   * @return \Drupal\poc_nextcloud\Response\OcsResponse
   *   Response data (parsed json).
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Request failed.
   */
  public function requestJson(string $method, string $path = '', array $query = []): mixed;

  /**
   * Makes a request to the API. Gets raw json data.
   *
   * This is for requests that expect a random array.
   *
   * @param string $method
   *   One of 'GET' or 'POST'.
   * @param string $path
   *   Path relative to the API base url.
   *   E.g. 'ocs/v1.php/cloud/users' to create a Nextcloud user.
   * @param array $query
   *   Request parameters.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function request(string $method, string $path = '', array $query = []): ResponseInterface;

}
