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
use PHPUnit\Framework\Assert;

return function (ApiConnectionInterface $connection): void {
  $endpoint = new NxUserEndpoint($connection);

  $name = 'Fabio';
  $name_1 = 'Fabio_1';
  $email = $name . '@example.com';

  // Make sure users do not already exist.
  Assert::assertNull($endpoint->load($name));
  Assert::assertNull($endpoint->load($name_1));

  try {
    $insert_id = $endpoint->insertWithEmail($name, $email);
    Assert::assertSame($name, $insert_id);

    $insert_id_1 = $endpoint->insertWithEmail($name_1, $email);
    Assert::assertSame($name_1, $insert_id_1);
  }
  finally {
    // Clean up after.
    $endpoint->deleteIfExists($name);
    $endpoint->deleteIfExists($name_1);
  }
};
