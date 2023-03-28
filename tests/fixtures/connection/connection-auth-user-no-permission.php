<?php

/**
 * @file
 * Tests error handling when using a non-existing auth user.
 */

use Drupal\poc_nextcloud\Connection\ApiConnection;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use PHPUnit\Framework\Assert;

return function (ApiConnection $connection): void {
  $endpoint = new NxUserEndpoint($connection);
  $username = 'testuser';
  $pass = 'tes012fe552r008pw';
  try {
    $endpoint->insertWithPassword($username, $pass);
    $response = $connection
      ->withAuth($username, $pass)
      ->requestOcs('GET', 'ocs/v1.php/cloud/users');
    Assert::assertTrue($response->isFailure());
    Assert::assertSame(403, $response->getStatusCode());
    Assert::assertSame('Logged in user must be at least a sub admin', $response->getMessage());
    Assert::assertSame([], $response->getData());
  }
  finally {
    $endpoint->deleteIfExists('testuser');
  }
};
