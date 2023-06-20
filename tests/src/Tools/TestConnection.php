<?php

declare(strict_types = 1);

namespace Drupal\Tests\poc_nextcloud\Tools;

use Drupal\poc_nextcloud\Connection\ApiConnection;
use GuzzleHttp\ClientInterface;

/**
 * Static factories to connect to the dockerized Nextcloud instance.
 */
class TestConnection {

  /**
   * Creates a new connection with capturing client.
   *
   * @param array $traffic
   *   Traffic array with requests and responses.
   *   If UPDATE_TESTS env is enabled, the value will be updated.
   *
   * @psalm-param list<array{request: array, response: array}> $traffic
   *
   * @return \Drupal\poc_nextcloud\Connection\ApiConnection
   *   New API connection.
   */
  public static function fromRecordedTrafficReference(array &$traffic): ApiConnection {
    $client = CapturingClient::create($traffic);
    return self::fromClient($client);
  }

  /**
   * Creates a connection to the dockerized Nextcloud instance.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   Http client.
   *
   * @return \Drupal\poc_nextcloud\Connection\ApiConnection
   *   The new connection object.
   */
  public static function fromClient(ClientInterface $client): ApiConnection {
    // @todo Make this configurable.
    return (new ApiConnection($client, 'http://nextcloud_test:80/'))
      // @todo Make this configurable.
      ->withAuth('admin', 'admin')
      ->withHeader('OCS-APIRequest', 'true')
      ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
      // Always send json header.
      ->withHeader('accept', 'application/json');
  }

}
