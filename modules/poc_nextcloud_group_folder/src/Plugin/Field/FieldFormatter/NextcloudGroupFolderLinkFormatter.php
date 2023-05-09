<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Field formatter for a Nextcloud group folder reference.
 *
 * Note that the field types are only available by enabling one of the
 * submodules.
 *
 * @FieldFormatter(
 *   id = "poc_nextcloud_group_folder_link",
 *   label = @Translation("Link to group folder in Nextcloud"),
 *   field_types = {
 *     "poc_nextcloud_group_folder",
 *   }
 * )
 */
class NextcloudGroupFolderLinkFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    return [];
  }

}
