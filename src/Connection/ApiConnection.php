<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Connection;

use Drupal\poc_nextcloud\Exception\NextcloudApiException;
use Drupal\poc_nextcloud\Exception\ResponseInvalidJsonException;
use Drupal\poc_nextcloud\Response\OcsResponse;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

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
   * Immutable setter. Sets the path prefix.
   *
   * @param string $prefix
   *   Path prefix to append to the url.
   *
   * @return static
   *   Copied instance with updated path prefix.
   */
  public function withPathPrefix(string $prefix): static {
    $clone = clone $this;
    $clone->baseUrl .= $prefix;
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
  public function request(string $method, string $path, array $params = []): OcsResponse {
    $url = $this->baseUrl . $path;
    $options = $this->options;
    if ($params) {
      $options[RequestOptions::FORM_PARAMS] = $params;
    }
    try {
      $response = $this->client->request($method, $url, $options);
    }
    catch (GuzzleException $e) {
      throw new NextcloudApiException(sprintf(
        'Failed %s request to %s with %s: %s',
        $method,
        $url,
        json_encode(
          // Don't reveal sensitive data.
          array_map(fn () => '*', $params),
          JSON_THROW_ON_ERROR,
        ),
        $e->getMessage(),
      ), 0, $e);
    }
    $body = (string) $response->getBody();
    try {
      $data = json_decode($body, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new ResponseInvalidJsonException(sprintf(
        "Invalid json returned for %s request to %s with parameters %s.",
        $method,
        $path,
        \GuzzleHttp\json_encode($params),
      ), 0, $e);
    }
    return OcsResponse::fromResponseData($data);
  }

}
