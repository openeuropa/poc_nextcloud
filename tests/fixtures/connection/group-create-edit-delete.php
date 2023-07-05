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
use Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint;
use Drupal\poc_nextcloud\Exception\FailureResponseException;
use PHPUnit\Framework\Assert;

return static function (ApiConnectionInterface $connection): void {
  $group_endpoint = new NxGroupEndpoint($connection);

  $group_id = 'test_group';
  $group_label = 'Test group';

  // Make sure user does not already exist.
  Assert::assertNull($group_endpoint->load($group_id));

  try {
    $group_endpoint->insert($group_id, $group_label);

    $group = $group_endpoint->load($group_id);

    Assert::assertSame($group_id, $group->getId());
    Assert::assertSame($group_label, $group->getDisplayName());
    Assert::assertSame(0, $group->getDisabledCount());
    Assert::assertSame(0, $group->getUserCount());
    Assert::assertSame(TRUE, $group->isCanAddUser());
    Assert::assertSame(TRUE, $group->isCanRemoveUser());

    try {
      $group_endpoint->insert($group_id, $group_label);
    }
    catch (FailureResponseException $e) {
      Assert::assertSame(102, $e->getResponseStatusCode());
      Assert::assertSame('102: group exists', $e->getMessage());
    }

  }
  finally {
    // Clean up if it broke somewhere.
    $group_endpoint->delete($group_id);

    // Make sure user does not already exist.
    Assert::assertNull($group_endpoint->load($group_id));
  }
};
