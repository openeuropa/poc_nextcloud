<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_workspace\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Field type to reference Nextcloud group folders.
 *
 * No widget or formatter exists for this, it has to be updated automatically.
 *
 * @FieldType(
 *   id = "poc_nextcloud_workspace",
 *   label = @Translation("Nextcloud Workspace"),
 *   description = @Translation("References a Nextcloud Workspace."),
 *   category = @Translation("Nextcloud"),
 *   default_formatter = "poc_nextcloud_group_folder_link",
 * )
 */
class NextcloudWorkspaceItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Nextcloud Workspace ID'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'int',
          // Expose the 'unsigned' setting in the field item schema.
          'unsigned' => TRUE,
          // Expose the 'size' setting in the field item schema. For instance,
          // supply 'big' as a value to produce a 'bigint' type.
          'size' => 'big',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // An id of 0 also counts as empty.
    return empty($this->value);
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    return [];
  }

}
