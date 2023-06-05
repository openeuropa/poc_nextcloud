<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\Hooks\EntityBaseFieldInfo;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\hux\Attribute\Hook;

/**
 * Hook implementation to declare a base field.
 */
class GroupBaseField {

  const FIELD_NAME = 'nextcloud_group_folder';

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function baseFieldInfo(EntityTypeInterface $entity_type): array {
    if ($entity_type->id() !== 'group') {
      return [];
    }

    $fields = [];
    $fields[self::FIELD_NAME] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Nextcloud group folder'))
      ->setDescription(t('If enabled, a group folder will be created in Nextcloud for this group.'))
      ->setDefaultValue(FALSE)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setSetting('on_label', 'Create group folder')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
