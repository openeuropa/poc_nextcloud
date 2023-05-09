<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\Plugin\Field\FieldFormatter;

/**
 * Field formatter for a Nextcloud group folder reference.
 *
 * Note that the field types are only available by enabling one of the
 * submodules.
 *
 * @FieldFormatter(
 *   id = "poc_nextcloud_group_folder_info",
 *   label = @Translation("Info for group folder in Nextcloud"),
 *   field_types = {
 *     "poc_nextcloud_group_folder",
 *   }
 * )
 */
class NextcloudGroupFolderInfoFormatter extends NextcloudGroupFolderLinkFormatter {

}
