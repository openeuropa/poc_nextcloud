<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Tracking;

/**
 * Constants for pending operations.
 *
 * @todo Use enum once 8.1 is required.
 *
 * @SuppressWarnings(PHPMD.ShortClassName)
 */
class Op {

  public const UPDATE = 'update';

  public const INSERT = 'insert';

  public const DELETE = 'delete';

  /**
   * Special operation to read data from Nextcloud.
   *
   * This value does never exist in the database, but some methods accept it as
   * a parameter value.
   */
  public const READ = 'read';

}
