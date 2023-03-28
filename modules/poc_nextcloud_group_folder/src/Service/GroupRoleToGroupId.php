<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\Service;

use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\group\Entity\GroupTypeInterface;
use Drupal\poc_nextcloud_group_folder\GroupFolderConstants;

/**
 * Service to build Nextcloud group names and namespaces.
 */
class GroupRoleToGroupId {

  /**
   * Gets a regular expression for the group namespace.
   *
   * @param \Drupal\group\Entity\GroupInterface|null $drupal_group
   *   Drupal group.
   * @param \Drupal\group\Entity\GroupRoleInterface|\Drupal\group\Entity\GroupTypeInterface|null $group_role_or_type
   *   Group folder, group role, or NULL for the top-level namespace.
   *
   * @return string
   *   Regular expression to filter the group id.
   *   A group is considered part of the namespace, if the pattern matches.
   */
  public function getGroupNamespaceRegex(
    GroupInterface $drupal_group = NULL,
    GroupRoleInterface|GroupTypeInterface $group_role_or_type = NULL,
  ): string {
    if ($group_role_or_type instanceof GroupRoleInterface) {
      [$bundle, $role_key] = explode('-', $group_role_or_type->id(), 2);
      $lastarg = preg_quote($bundle, '@') . '-' . preg_quote($role_key, '@');
    }
    elseif ($group_role_or_type) {
      $lastarg = preg_quote($group_role_or_type->id(), '@') . '-\w+';
    }
    else {
      $lastarg = '\w+-\w+';
    }
    return sprintf(
      '@^DRUPAL-GROUP-%s-%s$@',
      $drupal_group?->id() ?? '\d+',
      $lastarg,
    );
  }

  /**
   * Builds a Nextcloud group id.
   *
   * @param \Drupal\group\Entity\GroupInterface $drupal_group
   *   Drupal group.
   * @param \Drupal\group\Entity\GroupRoleInterface $drupal_group_role
   *   Drupal group role.
   *
   * @return string|null
   *   Nextcloud group id, or NULL if no group should be created here.
   */
  public function buildGroupId(
    GroupInterface $drupal_group,
    GroupRoleInterface $drupal_group_role,
  ): ?string {
    if (!array_intersect(
      GroupFolderConstants::PERMISSIONS_MAP,
      $drupal_group_role->getPermissions(),
    )) {
      // This role should not have a group in Nextcloud.
      return NULL;
    }
    $drupal_group_id = $drupal_group->id();
    if ($drupal_group_id === NULL) {
      throw new \RuntimeException('Group id builder was called with a group that has no id yet.');
    }
    return sprintf(
      'DRUPAL-GROUP-%s-%s',
      $drupal_group->id(),
      $drupal_group_role->id(),
    );
  }

}
