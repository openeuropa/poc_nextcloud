<?php

/**
 * @file
 * Tests error handling when attempting to create a user that already exists.
 */

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud\Exception\FailureResponseException;
use Drupal\poc_nextcloud\NxEntity\NxUser;
use PHPUnit\Framework\Assert;

return function (ApiConnectionInterface $connection): void {
  $endpoint = NxUserEndpoint::fromConnection($connection);

  $name = 'Fabio';
  $email = $name . '@example.com';

  // Make sure user does not already exist.
  Assert::assertNull($endpoint->load($name));

  $stub_user = NxUser::createStubWithEmail(
    $name,
    $email,
  );

  try {
    Assert::assertSame(
      $name,
      $endpoint->insert($stub_user),
    );

    try {
      $endpoint->insert($stub_user);
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
