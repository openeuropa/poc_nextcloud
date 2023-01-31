<?php

/**
 * @file
 * Tests loading of group folder ids.
 */

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint;
use PHPUnit\Framework\Assert;

return static function (ApiConnectionInterface $connection): void {
  $endpoint = new NxGroupFolderEndpoint($connection);

  $ids = [];
  try {
    $ids_before = $endpoint->loadIds();
    $ids[] = $endpoint->insertWithMountPoint('example');
    $ids[] = $endpoint->insertWithMountPoint('example_1');
    $ids[] = $endpoint->insertWithMountPoint('example_2');
    $ids_expected = [...$ids_before, ...$ids];
    $ids_after = $endpoint->loadIds();
    sort($ids_expected);
    sort($ids_after);
    Assert::assertSame($ids_expected, $ids_after);
  }
  finally {
    // Clean up.
    foreach ($ids as $id) {
      $endpoint->deleteIfExists($id);
    }
  }
};
