<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Database;

/**
 * Object that can provide part of a database schema.
 */
interface SchemaProviderInterface {

  /**
   * Gets a value to be returned by hook_schema().
   *
   * @return array[]
   *   Database table definitions.
   */
  public function getSchema(): array;

}
