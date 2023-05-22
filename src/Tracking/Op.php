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

  public const UNCHANGED = 0;

  public const UPDATE = 1;

  public const INSERT = 2;

  public const DELETE = 4;

  /**
   * Special operation to read data from Nextcloud.
   *
   * This value does never exist in the database, but some methods accept it as
   * a parameter value.
   */
  public const READ = 8;

}
