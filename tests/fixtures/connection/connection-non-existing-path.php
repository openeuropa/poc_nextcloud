<?php

/**
 * @file
 * Tests error handling for requests to non-existent paths.
 */

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\Exception\ResponseInvalidJsonException;
use PHPUnit\Framework\Assert;

return function (ApiConnectionInterface $connection): void {
  try {
    $connection->requestOcs('GET', 'non-existing-path');
    Assert::fail('Expected an exception.');
  }
  catch (ResponseInvalidJsonException) {
    // Pass.
    Assert::assertTrue(TRUE);
  }
};
