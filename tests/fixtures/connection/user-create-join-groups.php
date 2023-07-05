<?php

/**
 * @file
 * Tests user create, load and delete.
 */

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use PHPUnit\Framework\Assert;

return static function (ApiConnectionInterface $connection): void {
  $user_endpoint = new NxUserEndpoint($connection);
  $group_endpoint = new NxGroupEndpoint($connection);

  $user_id = 'Aurelie';
  $user_email = 'aurelie@example.com';
  $group_id = 'test_group';
  $group_label = 'Test group';

  // Make sure user does not already exist.
  Assert::assertNull($user_endpoint->load($user_id));
  Assert::assertNull($group_endpoint->load($group_id));

  try {
    $user_endpoint->insertWithEmail($user_id, $user_email);
    $group_endpoint->insert($group_id, $group_label);

    Assert::assertSame([], $user_endpoint->getGroupIds($user_id));

    $user_endpoint->joinGroup($user_id, $group_id);

    Assert::assertSame([$group_id], $user_endpoint->getGroupIds($user_id));

    // Attempt to join again.
    $user_endpoint->joinGroup($user_id, $group_id);

    Assert::assertSame([$group_id], $user_endpoint->getGroupIds($user_id));

    $user_endpoint->leaveGroup($user_id, $group_id);

    Assert::assertSame([], $user_endpoint->getGroupIds($user_id));

    // Attempt to leave again.
    $user_endpoint->leaveGroup($user_id, $group_id);

    Assert::assertSame([], $user_endpoint->getGroupIds($user_id));
  }
  finally {
    // Clean up if it broke somewhere.
    $user_endpoint->deleteIfExists($user_id);
    $group_endpoint->delete($group_id);
  }
};
