<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Connection;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\State\StateInterface;
use Drupal\poc_nextcloud\CookieJar\ValueStoreCookieJar;
use Drupal\poc_nextcloud\Crypt\OpenSSLCryptor;
use Drupal\poc_nextcloud\Exception\NextcloudApiException;
use Drupal\poc_nextcloud\Exception\ResponseInvalidJsonException;
use Drupal\poc_nextcloud\Exception\ServiceNotAvailableException;
use Drupal\poc_nextcloud\Response\OcsResponse;
use Drupal\poc_nextcloud\ValueStore\CryptValueStore;
use Drupal\poc_nextcloud\ValueStore\StateValueStore;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\CookieJarInterface;
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
   * @param \Drupal\Core\State\StateInterface $state
   *   Drupal state, used to persist cookies between Drupal requests.
   *   Without this, the API would need to verify the login credentials on every
   *   request, which can make these requests 10x slower, e.g. 200ms with auth,
   *   but 20ms with session cookie.
   *
   * @return self
   *   New connection.
   *
   * @throws \Drupal\poc_nextcloud\Exception\ServiceNotAvailableException
   *
   * @todo Consider token-based authentication.
   */
  public static function fromConfig(
    ClientInterface $client,
    ConfigFactoryInterface $config_factory,
    StateInterface $state,
  ): self {
    $settings = $config_factory->get('poc_nextcloud.settings');
    $values = [];
    foreach (['nextcloud_url', 'nextcloud_user', 'nextcloud_pass'] as $key) {
      $values[$key] = $settings->get($key) ?? '';
    }
    if (in_array('', $values)) {
      throw new ServiceNotAvailableException(sprintf(
        'Missing or empty configuration keys: %s keys.',
        implode(', ', array_keys($values, '', TRUE)),
      ));
    }
    [$url, $username, $pass] = array_values($values);
    $url = rtrim($url, '/') . '/';

    $instance = self::fromValues(
      $client,
      $url,
      $username,
      $pass,
    );

    $cookie_jar = self::createCookieJar($state, $settings);

    return $instance->withCookieJar($cookie_jar);
  }

  /**
   * Creates a cookie jar.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   Drupal state storage.
   * @param \Drupal\Core\Config\ImmutableConfig $settings
   *   Settings for this module.
   *
   * @return \GuzzleHttp\Cookie\CookieJar
   *   Cookie jar.
   */
  private static function createCookieJar(
    StateInterface $state,
    ImmutableConfig $settings,
  ): CookieJar {
    $crypt_secret = $settings->get('storage_encryption_key');
    if (!$crypt_secret) {
      // Create a cookie jar that only lasts for a single Drupal request.
      return new CookieJar();
    }
    // Create a cookie jar that persists between Drupal requests.
    $cryptor = new OpenSSLCryptor($crypt_secret);
    $cookie_store = new StateValueStore($state, 'poc_nextcloud.connection_cookies');
    $cookie_store = new CryptValueStore($cookie_store, $cryptor);
    return new ValueStoreCookieJar(
      $cookie_store,
      TRUE,
      TRUE,
    );
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
   * @throws \Drupal\poc_nextcloud\Exception\ServiceNotAvailableException
   */
  public static function fromValues(
    ClientInterface $client,
    string $url,
    string $username,
    #[\SensitiveParameter]
    string $pass,
  ): self {
    if ($url === '' || $username === '' || $pass === '') {
      throw new ServiceNotAvailableException('Nextcloud configuration is incomplete.');
    }
    if (!preg_match('@^(?:http|https)://\w+(?:\.\w+)*/(?:\w+/)*$@', $url)) {
      throw new ServiceNotAvailableException('Nextcloud url does not have the expected format.');
    }
    return (new self($client, $url))
      ->withAuth($username, $pass)
      ->withHeader('OCS-APIRequest', 'true')
      // @todo Do all API requests need the same headers?
      ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
      // Always send json header.
      ->withHeader('accept', 'application/json');
  }

  /**
   * Immutable setter. Sets a cookie jar.
   *
   * @param \GuzzleHttp\Cookie\CookieJarInterface $cookieJar
   *   Cookie jar.
   *
   * @return static
   *   Modified instance.
   */
  public function withCookieJar(CookieJarInterface $cookieJar): static {
    $clone = clone $this;
    $clone->options['cookies'] = $cookieJar;
    return $clone;
  }

  /**
   * {@inheritdoc}
   */
  public function withPath(string $prefix): static {
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
  public function withFormValues(array $values): static {
    return $this->withRequestOption(RequestOptions::FORM_PARAMS, $values, []);
  }

  /**
   * {@inheritdoc}
   */
  public function withQuery(array $query): static {
    return $this->withRequestOption(RequestOptions::QUERY, $query, []);
  }

  /**
   * {@inheritdoc}
   */
  public function withRequestOption(string $key, mixed $value, mixed $default = NULL): static {
    if ($value === ($this->options[$key] ?? $default)) {
      return $this;
    }
    $clone = clone $this;
    $clone->options[$key] = $value;
    return $clone;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserId(): ?string {
    return $this->options[RequestOptions::AUTH][0] ?? NULL;
  }

  /**
   * Immutable setter. Sets credentials for the API user.
   *
   * This is meant to be called in the same code that constructs the instance,
   * therefore it is not part of the interface.
   *
   * @param string $user
   *   Username for Nextcloud API user.
   * @param string $pass
   *   Password for Nextcloud API user.
   *
   * @return static
   *   Copied instance with updated credentials.
   */
  public function withAuth(string $user, string $pass): static {
    $clone = clone $this;
    $clone->options[RequestOptions::AUTH] = [$user, $pass];
    return $clone;
  }

  /**
   * Immutable setter. Adds a http header.
   *
   * If a header with the given name already exists, it will be overwritten.
   *
   * This is meant to be called in the same code that constructs the instance,
   * therefore it is not part of the interface.
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
  public function requestJson(string $method, string $path = '', array $params = []): array {
    $body = $this->requestBody($method, $path, $params);
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
   * @param array $params
   *   Query string parameters for GET, or form values for POST.
   *
   * @return string
   *   Response body.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *
   * @todo Consider to not wrap the GuzzleException.
   *   This would make the throws contract more verbose, but it would allow more
   *   targeted catch statements later.
   */
  protected function requestBody(string $method, string $path = '', array $params = []): string {
    try {
      $response = $this->request($method, $path, $params);
    }
    catch (GuzzleException $e) {
      throw new NextcloudApiException(sprintf(
        'Failed %s request to %s with %s: %s',
        $method,
        $this->buildUrl($path),
        // Show parameter names, but not values.
        json_encode(array_map(
          static fn () => '*',
          $params,
        )),
        $e->getMessage(),
      ), 0, $e);
    }
    return (string) $response->getBody();
  }

  /**
   * {@inheritdoc}
   */
  public function request(string $method, string $path = '', array $params = []): ResponseInterface {
    $url = $this->buildUrl($path);
    $options = $this->options;
    if ($params) {
      if ($method === 'GET') {
        $options[RequestOptions::QUERY] = $params;
      }
      else {
        $options[RequestOptions::FORM_PARAMS] = $params;
      }
    }
    return $this->client->request($method, $url, $options);
  }

  /**
   * Appends a path to the base url, separated by slash.
   *
   * This is needed because the base url may or may not have a trailing slash.
   *
   * @param string $path
   *   Path without leading slash.
   *
   * @return string
   *   Url from base url and path.
   */
  private function buildUrl(string $path = ''): string {
    if ($path === '') {
      return $this->baseUrl;
    }
    return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
  }

}
