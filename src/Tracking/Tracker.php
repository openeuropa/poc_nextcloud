<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Tracking;

/**
 * Constants for pending operations.
 *
 * @todo Use enum once 8.1 is required.
 */
class Tracker {

  public const OP_UNCHANGED = 0;

  public const OP_UPDATE = 1;

  public const OP_INSERT = 2;

  public const OP_DELETE = 4;

  /**
   * Special operation to read data from Nextcloud.
   *
   * This value does never exist in the database, but some methods accept it as
   * a parameter value.
   */
  public const OP_READ = 8;

}
