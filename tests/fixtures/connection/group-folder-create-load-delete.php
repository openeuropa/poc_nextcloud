<?php

/**
 * @file
 * Tests group folder create, load and delete.
 */

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint;
use Drupal\poc_nextcloud\NxEntity\NxGroupFolder;
use PHPUnit\Framework\Assert;

return static function (ApiConnectionInterface $connection) {
  $endpoint = NxGroupFolderEndpoint::fromConnection($connection);

  $stub = NxGroupFolder::createWithMountPoint('example');
  $id = $endpoint->insert($stub);

  try {
    Assert::assertIsInt($id);

    $folder = $endpoint->load($id);

    Assert::assertInstanceOf(NxGroupFolder::class, $folder);
    Assert::assertFalse($folder->isStub());
    Assert::assertSame($id, $folder->getId());
    Assert::assertSame('example', $folder->getMountPoint());

    $endpoint->delete($id);

    Assert::assertNull($endpoint->load($id));
  }
  finally {
    $endpoint->deleteIfExists($id);
  }
};
