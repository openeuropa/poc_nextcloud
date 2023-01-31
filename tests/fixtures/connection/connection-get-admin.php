<?php

/**
 * @file
 * Tests a direct connection request to get an admin.
 */

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use PHPUnit\Framework\Assert;

return function (ApiConnectionInterface $connection): void {
  $response = $connection->requestOcs('GET', 'ocs/v1.php/cloud/users/admin');
  Assert::assertSame(100, $response->getStatusCode());
  Assert::assertSame('OK', $response->getMessage());
  Assert::assertNull($response->getItemsperpage());
  Assert::assertNull($response->getTotalitems());
  Assert::assertFalse($response->isFailure());
  Assert::assertIsArray($response->getData());
  Assert::assertSame('admin', $response->getData()['id'] ?? NULL);
};
