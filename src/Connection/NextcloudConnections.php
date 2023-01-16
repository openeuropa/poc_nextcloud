<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Connection;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;

/**
 * Static factories to connect to the Nextcloud API.
 */
class NextcloudConnections {

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
   * @return \Drupal\poc_nextcloud\Connection\ApiConnection
   *   New connection.
   *
   * @throws \Exception
   *   Configuration is incomplete or invalid.
   */
  public static function fromConfig(
    ClientInterface $client,
    ConfigFactoryInterface $config_factory,
  ): ApiConnection {
    $settings = $config_factory->get('poc_nextcloud.settings');
    $url = $settings->get('nextcloud_url') ?? '';
    $username = $settings->get('nextcloud_user') ?? '';
    // @todo Can we encrypt the password for storage?
    $pass = $settings->get('nextcloud_pass') ?? '';
    if ($url === '' || $username === '' || $pass === '') {
      throw new \Exception('Nextcloud configuration is incomplete.');
    }
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
   * @return \Drupal\poc_nextcloud\Connection\ApiConnection
   *   New connection.
   *
   * @throws \Exception
   *   Values were invalid.
   */
  public static function fromValues(ClientInterface $client, string $url, string $username, string $pass): ApiConnection {
    if ($url === '' || $username === '' || $pass === '') {
      throw new \Exception('Nextcloud configuration is incomplete.');
    }
    if (!preg_match('@^(?:http|https)://\w+(?:\.\w+)*/(?:\w+/)*$@', $url)) {
      throw new \Exception('Nextcloud url does not have the expected format.');
    }
    return (new ApiConnection($client, $url))
      ->withAuth($username, $pass)
      ->withHeader('OCS-APIRequest', 'true')
      ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
      // Always send json header.
      ->withHeader('accept', 'application/json');
  }

}
