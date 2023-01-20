<?php

/**
 * @file
 * Tests loading of user ids from the API.
 */

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud\NxEntity\NxUser;
use PHPUnit\Framework\Assert;

return function (ApiConnectionInterface $connection): void {
  $endpoint = NxUserEndpoint::fromConnection($connection);

  $ids_before = $endpoint->loadIds();
  Assert::assertIsArray($ids_before);
  Assert::assertTrue(array_is_list($ids_before));
  array_map([Assert::class, 'assertIsString'], $ids_before);
  sort($ids_before);

  $names = ['Fabio', 'Mercedes', 'Bianca'];

  Assert::assertEmpty(array_intersect($names, $ids_before));

  try {
    foreach ($names as $name) {
      $stub_user = NxUser::createStubWithEmail(
        $name,
        $name . '@example.com',
      );
      $insert_id = $endpoint->insert($stub_user);
      Assert::assertSame($name, $insert_id);
    }

    $ids_new_expected = [...$ids_before, ...$names];
    sort($ids_new_expected);

    $ids_new = $endpoint->loadIds();
    Assert::assertIsArray($ids_new);
    Assert::assertTrue(array_is_list($ids_new));
    array_map([Assert::class, 'assertIsString'], $ids_new);
    sort($ids_new);

    Assert::assertSame($ids_new_expected, $ids_new);
  }
  finally {
    // Clean up after.
    foreach ($names as $name) {
      $endpoint->deleteIfExists($name);
    }

    $ids_after = $endpoint->loadIds();
    sort($ids_after);

    Assert::assertSame($ids_before, $ids_after);
  }
};
