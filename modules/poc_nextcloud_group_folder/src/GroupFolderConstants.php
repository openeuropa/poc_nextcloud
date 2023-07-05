<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder;

use Drupal\poc_nextcloud\NextcloudConstants;

/**
 * Constants related to group folders.
 */
class GroupFolderConstants {

  public const PERMISSIONS_MAP = [
    NextcloudConstants::PERMISSION_READ => 'nextcloud group folder file read',
    NextcloudConstants::PERMISSION_WRITE => 'nextcloud group folder file write',
    NextcloudConstants::PERMISSION_SHARE => 'nextcloud group folder file share',
    NextcloudConstants::PERMISSION_DELETE => 'nextcloud group folder file delete',
    NextcloudConstants::PERMISSION_ADVANCED => 'nextcloud group folder file acl',
  ];

}
