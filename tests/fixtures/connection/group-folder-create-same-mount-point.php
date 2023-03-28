<?php

/**
 * @file
 * Tests creating multiple group folders with the same mount point.
 *
 * When run with a real Nextcloud instance, a failing test would indicate that
 * our assumptions about the Nextcloud API are no longer up to date, possibly
 * due to a new version.
 */

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint;
use PHPUnit\Framework\Assert;

return static function (ApiConnectionInterface $connection): void {
  $endpoint = new NxGroupFolderEndpoint($connection);

  $ids = [];
  try {
    $ids[] = $first_id = $endpoint->insertWithMountPoint('example');
    $ids[] = $second_id = $endpoint->insertWithMountPoint('example');
    Assert::assertNotSame($first_id, $second_id);
  }
  finally {
    foreach ($ids as $id) {
      $endpoint->deleteIfExists($id);
    }
  }
};
