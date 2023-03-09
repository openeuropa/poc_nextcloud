<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\Service;

use Drupal\group\Entity\GroupInterface;

/**
 * Service to map Drupal groups to Nextcloud group folders.
 */
class GroupToGroupFolderMap {

  /**
   * Determines if the group should and can have a group folder.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group.
   *
   * @return bool
   *   TRUE if a Drupal group should have a group folder.
   */
  public function groupShouldHaveGroupFolder(GroupInterface $group): bool {
    $field_name = $this->findFieldName($group);
    // @todo More advanced conditions? E.g. a checkbox in the field?
    return $field_name !== NULL;
  }

  /**
   * Gets the Nextcloud group folder id for a Drupal group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Drupal group.
   *
   * @return int|null
   *   Nextcloud group folder id, or NULL if none is referenced.
   */
  public function groupGetGroupFolderId(GroupInterface $group): ?int {
    $field_name = $this->findFieldName($group);
    if ($field_name === NULL) {
      return NULL;
    }
    $value = $group->$field_name->value;
    if ($value && (string) (int) $value === (string) $value) {
      return (int) $value;
    }
    return NULL;
  }

  /**
   * Sets the group folder id for a given group.
   *
   * This only sets the value in the group object, it does not call ->save().
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Drupal group.
   * @param int|null $group_folder_id
   *   The group folder id, or NULL to unset the reference.
   */
  public function groupSetGroupFolderId(GroupInterface $group, ?int $group_folder_id): void {
    $field_name = $this->findFieldName($group);
    if ($field_name === NULL) {
      throw new \RuntimeException('Cannot set the group folder id on this group. No field is present. This is likely a programming error.');
    }
    $group->$field_name->value = $group_folder_id;
  }

  /**
   * Finds the field name referencing a Nextcloud group folder id.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Drupal group.
   *
   * @return string|null
   *   Field referencing the group folder id, or NULL if the group type has no
   *   such field.
   */
  private function findFieldName(GroupInterface $group): ?string {
    foreach ($group->getFieldDefinitions() as $field_name => $definition) {
      if ($definition->getType() === 'poc_nextcloud_group_folder') {
        return $field_name;
      }
    }
    return NULL;
  }

}
