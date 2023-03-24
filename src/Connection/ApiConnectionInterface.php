<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Connection;

use Drupal\poc_nextcloud\Response\OcsResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * Connection to the Nextcloud API, or to a specific path within Nextcloud.
 *
 * This is typically a wrapper around a guzzle client.
 */
interface ApiConnectionInterface {

  /**
   * Gets a copied instance with a path suffix appended to the url.
   *
   * @param string $prefix
   *   Path suffix, without leading or trailing slash.
   *
   * @return static
   *   New instance.
   */
  public function withPath(string $prefix): static;

  /**
   * Gets a copied instance with the given form values.
   *
   * @param array $values
   *   Form values.
   *   Any pre-existing values will be replaced.
   *
   * @return static
   *   New instance.
   */
  public function withFormValues(array $values): static;

  /**
   * Gets a copied instance with the given query parameters.
   *
   * @param array $query
   *   Query string parameters.
   *   Any pre-existing parameters will be replaced.
   *
   * @return static
   *   New instance.
   */
  public function withQuery(array $query): static;

  /**
   * Gets a copied instance with added request option.
   *
   * @param string $key
   *   Request option key.
   * @param string $value
   *   Request option value.
   *
   * @return static
   *   New instance.
   */
  public function withRequestOption(string $key, string $value): static;

  /**
   * Gets the user id of the API user.
   *
   * Some endpoints need this to make user-specific requests.
   *
   * @return string|null
   *   User id, e.g. 'admin', or NULL if not set.
   *
   * @todo Rethink if this should really be part of the connection object.
   */
  public function getUserId(): ?string;

  /**
   * Makes a request to the API, and gets an OCS response object.
   *
   * @param string $method
   *   One of 'GET', 'POST', 'PUT', 'DELETE' etc.
   * @param string $path
   *   Path relative to the API base url.
   *   E.g. 'ocs/v1.php/cloud/users' to create a Nextcloud user.
   * @param array $params
   *   Query string parameters for GET, or form values for POST.
   *
   * @return \Drupal\poc_nextcloud\Response\OcsResponse
   *   OCS response object.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Request failed, or the response does not have the structore of an OCS
   *   response object.
   *
   * @todo Consider to split this into a different interface.
   */
  public function requestOcs(string $method, string $path = '', array $params = []): OcsResponse;

  /**
   * Makes a request to the API, and gets a http response object.
   *
   * This is for requests that expect a random array.
   *
   * @param string $method
   *   One of 'GET', 'POST', 'PUT', 'DELETE' etc.
   * @param string $path
   *   Path relative to the API base url.
   *   E.g. 'ocs/v1.php/cloud/users' to create a Nextcloud user.
   * @param array $params
   *   Query string parameters for GET, or form values for POST.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Response.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function request(string $method, string $path = '', array $params = []): ResponseInterface;

}
