<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_workspace\Service;

use Drupal\group\Entity\GroupInterface;

/**
 * Helper to interact with the field that references a workspace.
 */
class GroupWorkspaceFieldHelper {

  /**
   * Determines if the group should and can have a workspace.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group.
   *
   * @return bool
   *   TRUE if a workspace can and should be attached to the group.
   */
  public function groupShouldHaveWorkspace(GroupInterface $group): bool {
    $field_name = $this->findFieldName($group);
    // @todo More advanced conditions? E.g. a checkbox in the field?
    return $field_name !== NULL;
  }

  /**
   * Gets a Nextcloud workspace id for a Drupal group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Drupal group.
   *
   * @return int|null
   *   The workspace id, or NULL if none referenced.
   */
  public function groupGetWorkspaceId(GroupInterface $group): ?int {
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
   * Sets the workspace id for a given group.
   *
   * This only sets the value in the group object, it does not call ->save().
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Drupal group.
   * @param int|null $workspace_id
   *   The workspace id, or NULL to remove it.
   */
  public function groupSetWorkspaceId(GroupInterface $group, ?int $workspace_id): void {
    $field_name = $this->findFieldName($group);
    if ($field_name === NULL) {
      throw new \RuntimeException('Cannot set the workspace id on this group. No field is present. This is likely a programming error.');
    }
    $group->$field_name->value = $workspace_id;
  }

  /**
   * Finds the field name that is used to reference a workspace.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Drupal group.
   *
   * @return string|null
   *   Field name, or NULL if no such field exists.
   */
  private function findFieldName(GroupInterface $group): ?string {
    foreach ($group->getFieldDefinitions() as $field_name => $definition) {
      if ($definition->getType() === 'poc_nextcloud_workspace') {
        return $field_name;
      }
    }
    return NULL;
  }

}
