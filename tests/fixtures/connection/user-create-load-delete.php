<?php

/**
 * @file
 * Tests user create, load and delete.
 */

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud\NxEntity\NxUser;
use PHPUnit\Framework\Assert;

return function (ApiConnectionInterface $connection): void {
  $endpoint = new NxUserEndpoint($connection);

  $name = 'Aurelie';
  $email = 'Aurelie@example.com';
  // The email is stored in lowercase.
  $email_expected = 'aurelie@example.com';

  // Make sure user does not already exist.
  Assert::assertNull($endpoint->load($name));

  try {
    $insert_id = $endpoint->insertWithEmail($name, $email);
    Assert::assertSame($name, $insert_id);

    $user = $endpoint->load($name);

    Assert::assertInstanceOf(NxUser::class, $user);
    Assert::assertSame($name, $user->getId());
    Assert::assertSame(TRUE, $user->isEnabled());
    Assert::assertSame($name, $user->getDisplayName());
    Assert::assertSame($email_expected, $user->getEmail());

    // Delete.
    // Do not rely on the ->deleteIfExists() in the finally clause.
    // This is the only place where we test the actual ->delete() method, which
    // fails if the entity does not exist.
    $endpoint->delete($name);
    // Make sure user is now deleted.
    Assert::assertNull($endpoint->load($name));
  }
  finally {
    // Clean up if it broke somewhere.
    $endpoint->deleteIfExists($name);
  }
};
