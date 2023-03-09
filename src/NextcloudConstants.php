<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud;

/**
 * Class with some constants.
 */
class NextcloudConstants {

  public const PERMISSION_READ = 1;
  public const PERMISSION_UPDATE = 2;
  public const PERMISSION_CREATE = 4;
  public const PERMISSION_WRITE = self::PERMISSION_UPDATE | self::PERMISSION_CREATE;
  public const PERMISSION_DELETE = 8;
  public const PERMISSION_SHARE = 16;
  public const PERMISSION_ALL = 31;

  public const PERMISSION_ADVANCED = 128;

}
