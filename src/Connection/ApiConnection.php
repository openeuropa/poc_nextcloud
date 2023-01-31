<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Connection;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\poc_nextcloud\Exception\NextcloudApiException;
use Drupal\poc_nextcloud\Exception\ResponseInvalidJsonException;
use Drupal\poc_nextcloud\Exception\ServiceNotAvailableException;
use Drupal\poc_nextcloud\Response\OcsResponse;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

/**
 * Default implementation.
 */
class ApiConnection implements ApiConnectionInterface {

  /**
   * Additional options to merge into a request.
   *
   * @var array
   */
  private array $options = [
    RequestOptions::DEBUG => FALSE,
  ];

  /**
   * Constructor.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   Http client.
   * @param string $baseUrl
   *   Url of Nextcloud instance.
   */
  public function __construct(
    private ClientInterface $client,
    private string $baseUrl,
  ) {}

  /**
   * Creates a new instance from config values.
   *
   * This factory is used for the service.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   Http client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Drupal configuration factory.
   *
   * @return self
   *   New connection.
   *
   * @throws \Exception
   *   Configuration is incomplete or invalid.
   */
  public static function fromConfig(
    ClientInterface $client,
    ConfigFactoryInterface $config_factory,
  ): self {
    $settings = $config_factory->get('poc_nextcloud.settings');
    $values = [];
    foreach (['nextcloud_url', 'nextcloud_user', 'nextcloud_pass'] as $key) {
      $values[$key] = $settings->get($key) ?? '';
    }
    if (in_array('', $values)) {
      throw new ServiceNotAvailableException(
        sprintf(
          'Missing or empty configuration keys: %s keys.',
          implode(', ', array_keys($values, '', TRUE)),
        ));
    }
    [$url, $username, $pass] = array_values($values);
    $url = rtrim($url, '/') . '/';
    return self::fromValues($client, $url, $username, $pass);
  }

  /**
   * Creates a new connection with provided explicit values.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   Http client.
   * @param string $url
   *   Url of the Nextcloud instance, with trailing slash.
   * @param string $username
   *   Username of API user.
   * @param string $pass
   *   Password of API user.
   *
   * @return self
   *   New connection.
   *
   * @throws \Exception
   *   Values were invalid.
   */
  public static function fromValues(ClientInterface $client, string $url, string $username, string $pass): self {
    if ($url === '' || $username === '' || $pass === '') {
      throw new ServiceNotAvailableException('Nextcloud configuration is incomplete.');
    }
    if (!preg_match('@^(?:http|https)://\w+(?:\.\w+)*/(?:\w+/)*$@', $url)) {
      throw new ServiceNotAvailableException('Nextcloud url does not have the expected format.');
    }
    return (new ApiConnection($client, $url))
      ->withAuth($username, $pass)
      ->withHeader('OCS-APIRequest', 'true')
      ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
      // Always send json header.
      ->withHeader('accept', 'application/json');
  }

  /**
   * {@inheritdoc}
   */
  public function path(string $prefix): static {
    if ($prefix === '') {
      return $this;
    }
    $clone = clone $this;
    // Prevent missing or duplicate slash between path parts.
    $clone->baseUrl = rtrim($this->baseUrl, '/')
      . '/' . ltrim($prefix, '/');
    return $clone;
  }

  /**
   * {@inheritdoc}
   */
  public function query(array $query): static {
    if ($query === ($this->options[RequestOptions::FORM_PARAMS] ?? [])) {
      return $this;
    }
    $clone = clone $this;
    $clone->options[RequestOptions::FORM_PARAMS] = $query;
    return $clone;
  }

  /**
   * Immutable setter. Sets credentials for the API user.
   *
   * @param string $user
   *   Username for Nextcloud API user.
   * @param string $pass
   *   Password for Nextcloud API user.
   *
   * @return \Drupal\poc_nextcloud\Connection\ApiConnection
   *   Copied instance with updated credentials.
   */
  public function withAuth(string $user, string $pass) {
    $clone = clone $this;
    $clone->options[RequestOptions::AUTH] = [$user, $pass];
    return $clone;
  }

  /**
   * Immutable setter. Adds a http header.
   *
   * If a header with the given name already exists, it will be overwritten.
   *
   * @param string $name
   *   Header name.
   * @param string $value
   *   Header value.
   *
   * @return static
   *   Copied instance with added header.
   */
  public function withHeader(string $name, string $value): static {
    $clone = clone $this;
    $clone->options[RequestOptions::HEADERS][$name] = $value;
    return $clone;
  }

  /**
   * {@inheritdoc}
   */
  public function requestOcs(string $method, string $path = '', array $params = []): OcsResponse {
    $data = $this->requestJson($method, $path, $params);
    return OcsResponse::fromResponseData($data);
  }

  /**
   * {@inheritdoc}
   */
  public function requestJson(string $method, string $path = '', array $query = []): array {
    $body = $this->requestBody($method, $path, $query);
    try {
      $data = json_decode($body, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new ResponseInvalidJsonException(sprintf(
        "Invalid json returned for %s request to %s with parameters %s.",
        $method,
        $path,
        \GuzzleHttp\json_encode($query),
      ), 0, $e);
    }
    return $data;
  }

  /**
   * Makes a requests and gets the response body.
   *
   * This also catches and wraps the guzzle exception.
   *
   * @param string $method
   *   Method, e.g. 'POST'.
   * @param string $path
   *   Path.
   * @param array $query
   *   Query parameters.
   *
   * @return string
   *   Response body.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *
   * @todo Consider to keep the GuzzleException.
   */
  protected function requestBody(string $method, string $path = '', array $query = []): string {
    try {
      $response = $this->request($method, $path, $query);
    }
    catch (GuzzleException $e) {
      throw new NextcloudApiException(sprintf(
        'Failed %s request to %s with %s: %s',
        $method,
        $this->baseUrl . $path,
        // Show parameter names, but not values.
        json_encode(
          array_map(fn () => '*', $query),
        ),
        $e->getMessage(),
      ), 0, $e);
    }
    return (string) $response->getBody();
  }

  /**
   * {@inheritdoc}
   */
  public function request(string $method, string $path = '', array $query = []): ResponseInterface {
    $url = $this->baseUrl . $path;
    $options = $this->options;
    if ($query) {
      $options[RequestOptions::FORM_PARAMS] = $query;
    }
    return $this->client->request($method, $url, $options);
  }

}
