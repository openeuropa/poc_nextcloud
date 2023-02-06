<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder;

use Drupal\poc_nextcloud\NextcloudConstants;

/**
 * Constants related to group folders.
 */
class GroupFolderConstants {

  public const PERMISSIONS_MAP = [
    'nextcloud group folder read' => NextcloudConstants::PERMISSION_READ,
    'nextcloud group folder write' => NextcloudConstants::PERMISSION_WRITE,
    'nextcloud group folder share' => NextcloudConstants::PERMISSION_SHARE,
    'nextcloud group folder delete' => NextcloudConstants::PERMISSION_DELETE,
    'nextcloud group folder manage' => NextcloudConstants::PERMISSION_ADVANCED,
  ];

  public const PERMISSIONS_SHORTCODE_MAP = [
    NextcloudConstants::PERMISSION_READ => 'r',
    NextcloudConstants::PERMISSION_WRITE => 'w',
    NextcloudConstants::PERMISSION_SHARE => 's',
    NextcloudConstants::PERMISSION_DELETE => 'd',
    NextcloudConstants::PERMISSION_ADVANCED => 'x',
  ];

}
