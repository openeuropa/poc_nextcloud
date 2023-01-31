<?php

/**
 * @file
 * Tests error handling when attempting to create a user that already exists.
 */

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud\Exception\FailureResponseException;
use PHPUnit\Framework\Assert;

return function (ApiConnectionInterface $connection): void {
  $endpoint = new NxUserEndpoint($connection);

  $name = 'Fabio';
  $email = $name . '@example.com';

  // Make sure user does not already exist.
  Assert::assertNull($endpoint->load($name));

  try {
    Assert::assertSame(
      $name,
      $endpoint->insertWithEmail($name, $email),
    );

    try {
      $endpoint->insertWithEmail($name, $email);
      Assert::fail('Creating an already existing user should result in exception.');
    }
    catch (FailureResponseException $e) {
      Assert::assertSame(102, $e->getResponseStatusCode());
    }
  }
  finally {
    // Clean up after.
    $endpoint->delete($name);
  }
};
