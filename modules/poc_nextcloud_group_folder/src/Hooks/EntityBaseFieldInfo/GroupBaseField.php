<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\Hooks\EntityBaseFieldInfo;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Access\GroupAccessResult;
use Drupal\group\Entity\GroupInterface;
use Drupal\hux\Attribute\Hook;

/**
 * Hook implementation to declare a base field.
 */
class GroupBaseField {

  const FIELD_NAME = 'nextcloud_group_folder';

  const PERMISSIONS = [
    'disable' => 'nextcloud group folder disable',
    'enable' => 'nextcloud group folder enable',
  ];

  /**
   * Checks if a field definition is this base field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Field definition to check.
   *
   * @return bool
   *   TRUE if the definition is for this base field.
   */
  public static function recognizeSelf(FieldDefinitionInterface $field_definition): bool {
    if ($field_definition->getTargetEntityTypeId() !== 'group') {
      return FALSE;
    }
    if ($field_definition->getName() !== self::FIELD_NAME) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Evaluates this field for a given group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group to evaluate.
   *
   * @return bool
   *   Field value.
   */
  public static function evaluate(GroupInterface $group): bool {
    return (bool) $group->get(self::FIELD_NAME)->__get('value');
  }

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

  /**
   * Implements hook_entity_field_access().
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess(
    string $operation,
    FieldDefinitionInterface $field_definition,
    AccountInterface $account,
    FieldItemListInterface $items = NULL,
  ): AccessResultInterface {
    if (!self::recognizeSelf($field_definition)) {
      return AccessResult::neutral();
    }

    $group = $items->getEntity();
    assert($group instanceof GroupInterface);

    if ($operation === 'view') {
      return GroupAccessResult::allowedIfHasGroupPermission($group, $account, 'nextcloud group folder file read');
    }

    if ($operation === 'edit') {
      $enabled = (bool) $items->__get('value');
      $group_perm_name = $enabled
        ? self::PERMISSIONS['disable']
        : self::PERMISSIONS['enable'];
      $result = GroupAccessResult::allowedIfHasGroupPermission($group, $account, $group_perm_name);
      // The access result depends on the field value.
      $result->addCacheableDependency($group);
      return $result;
    }

    return AccessResult::neutral("Unexpected operation '$operation'.");
  }

}
