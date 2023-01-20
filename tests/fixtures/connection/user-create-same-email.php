<?php

/**
 * @file
 * Tests how the API behaves when creating two users with the same email.
 *
 * When run with a real Nextcloud instance, a failing test would indicate that
 * our assumptions about the Nextcloud API are no longer up to date, possibly
 * due to a new version.
 */

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud\NxEntity\NxUser;
use PHPUnit\Framework\Assert;

return function (ApiConnectionInterface $connection): void {
  $endpoint = NxUserEndpoint::fromConnection($connection);

  $name = 'Fabio';
  $name_1 = 'Fabio_1';
  $email = $name . '@example.com';

  // Make sure users do not already exist.
  Assert::assertNull($endpoint->load($name));
  Assert::assertNull($endpoint->load($name_1));

  try {
    $stub_user = NxUser::createStubWithEmail(
      $name,
      $email,
    );
    $insert_id = $endpoint->insert($stub_user);
    Assert::assertSame($name, $insert_id);

    $stub_user_1 = NxUser::createStubWithEmail(
      $name_1,
      $email,
    );
    $insert_id_1 = $endpoint->insert($stub_user_1);
    Assert::assertSame($name_1, $insert_id_1);
  }
  finally {
    // Clean up after.
    $endpoint->deleteIfExists($name);
    $endpoint->deleteIfExists($name_1);
  }
};
