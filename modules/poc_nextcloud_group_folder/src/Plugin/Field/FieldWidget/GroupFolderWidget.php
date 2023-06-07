<?php

namespace Drupal\poc_nextcloud_group_folder\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\poc_nextcloud_group_folder\Hooks\EntityBaseFieldInfo\GroupBaseField;

/**
 * Field widget for the base field.
 *
 * @FieldWidget(
 *   id = "nextcloud_group_folder",
 *   label = @Translation("Nextcloud group folder"),
 *   field_types = {
 *     "boolean"
 *   }
 * )
 */
class GroupFolderWidget extends WidgetBase {

  public const ID = 'nextcloud_group_folder';

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return GroupBaseField::recognizeSelf($field_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $enabled = !empty($items[0]->value);
    $element['value'] = [
      '#field_parents' => $element['#field_parents'],
      '#required' => $element['#required'],
      '#type' => 'checkbox',
      '#default_value' => $enabled,
      '#title' => $this->t('This group should have a group folder in Nextcloud.'),
    ];
    $confirm_title = $enabled
      ? $this->t('Please confirm: Delete attached Nextcloud group folder, and all documents it contains. This action is not reversible.')
      : $this->t('Please confirm: Create a new Nextcloud group folder for this group, if none exists.');
    $element['confirm'] = [
      '#type' => 'checkbox',
      '#default_value' => FALSE,
      '#title' => $confirm_title,
    ];
    $element['#process'][] = [static::class, 'processElement'];
    $element['#element_validate'][] = [static::class, 'validateElement'];
    return $element;
  }

  /**
   * Callback for '#process'.
   *
   * @param array $element
   *   Field widget element.
   *
   * @return array
   *   Element with modifications.
   */
  public static function processElement(array $element): array {
    $enabled = $element['value']['#default_value'];
    $selector = ':input#' . $element['#id'] . '-value';
    $condition = [$selector => ['checked' => !$enabled]];
    $element['confirm']['#states'] = [
      'visible' => $condition,
      'required' => $condition,
      'unchecked' => $condition,
    ];
    return $element;
  }

  /**
   * Callback for '#element_validate'.
   *
   * @param array $element
   *   Element to validate.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public static function validateElement(array $element, FormStateInterface $form_state): void {
    $value = (bool) $element['value']['#value'];
    $default = (bool) $element['value']['#default_value'];
    $confirm = (bool) $element['confirm']['#value'];
    if ($value !== $default && !$confirm) {
      $form_state->setError($element['confirm'], $element['confirm']['#title']);
    }
  }

}
