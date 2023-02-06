<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\EntityObserver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\group\Entity\GroupTypeInterface;
use Drupal\poc_nextcloud\DataUtil;
use Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint;
use Drupal\poc_nextcloud\EntityObserver\EntityObserverInterface;
use Drupal\poc_nextcloud\WritableImage\GroupsInNamespaceImage;
use Drupal\poc_nextcloud_group_folder\GroupFolderConstants;
use Drupal\poc_nextcloud_group_folder\Service\DrupalGroupLoader;
use Drupal\poc_nextcloud_group_folder\Service\GroupRoleToGroupId;

/**
 * Creates Nextcloud groups for Drupal group roles.
 */
class NextcloudGroups implements EntityObserverInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint $groupEndpoint
   *   Group endpoint.
   * @param \Drupal\poc_nextcloud_group_folder\Service\GroupRoleToGroupId $groupIdBuilder
   *   Service to build group ids and group id namespaces.
   * @param \Drupal\poc_nextcloud_group_folder\Service\DrupalGroupLoader $drupalGroupLoader
   *   Service to load Drupal groups and group types.
   */
  public function __construct(
    private NxGroupEndpoint $groupEndpoint,
    private GroupRoleToGroupId $groupIdBuilder,
    private DrupalGroupLoader $drupalGroupLoader,
  ) {}

  /**
   * Rebuilds all groups for all group types.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function rebuildAll(): void {
    $this->updateScope(NULL, NULL, NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function entityOp(EntityInterface $entity, string $op): void {
    if ($entity instanceof GroupInterface) {
      $this->updateScope(
        $entity->getGroupType(),
        NULL,
        $entity,
      );
    }
    elseif ($entity instanceof GroupTypeInterface) {
      $this->updateScope(
        $entity,
        NULL,
        NULL,
      );
    }
    elseif ($entity instanceof GroupRoleInterface) {
      $this->updateScope(
        $entity->getGroupType(),
        $entity,
        NULL,
      );
    }
  }

  /**
   * Updates a range of Nextcloud groups.
   *
   * @param \Drupal\group\Entity\GroupTypeInterface|null $drupal_group_type
   *   Drupal group type.
   * @param \Drupal\group\Entity\GroupRoleInterface|null $drupal_group_role
   *   Drupal group role.
   * @param \Drupal\group\Entity\GroupInterface|null $drupal_group
   *   Drupal group.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function updateScope(
    ?GroupTypeInterface $drupal_group_type,
    ?GroupRoleInterface $drupal_group_role,
    ?GroupInterface $drupal_group,
  ): void {
    $image = new GroupsInNamespaceImage(
      $this->groupEndpoint,
      $this->groupIdBuilder->getGroupNamespaceRegex(
        $drupal_group,
        $drupal_group_role ?? $drupal_group_type,
      ),
    );
    if ($drupal_group !== NULL) {
      $this->processGroup(
        $image,
        $drupal_group_type,
        $drupal_group_role,
        $drupal_group,
      );
    }
    elseif ($drupal_group_type !== NULL) {
      $this->processGroupType(
        $image,
        $drupal_group_type,
        $drupal_group_role,
      );
    }
    else {
      foreach ($this->drupalGroupLoader->loadGroupTypes() as $a_group_type) {
        $this->processGroupType(
          $image,
          $a_group_type,
          NULL,
        );
      }
    }
    $image->write();
  }

  /**
   * Processes a group type.
   *
   * @param \Drupal\poc_nextcloud\WritableImage\GroupsInNamespaceImage $image
   *   Image to collect groups.
   * @param \Drupal\group\Entity\GroupTypeInterface $drupal_group_type
   *   Drupal group type.
   * @param \Drupal\group\Entity\GroupRoleInterface|null $drupal_group_role
   *   Drupal group role, or NULL for all group roles of the given type.
   */
  private function processGroupType(
    GroupsInNamespaceImage $image,
    GroupTypeInterface $drupal_group_type,
    ?GroupRoleInterface $drupal_group_role,
  ): void {
    $drupal_groups = $this->drupalGroupLoader->loadGroupsForType($drupal_group_type);
    foreach ($drupal_groups as $drupal_group) {
      $this->processGroup(
        $image,
        $drupal_group_type,
        $drupal_group_role,
        $drupal_group,
      );
    }
  }

  /**
   * Processes a Drupal group.
   *
   * @param \Drupal\poc_nextcloud\WritableImage\GroupsInNamespaceImage $image
   *   Image to collect groups.
   * @param \Drupal\group\Entity\GroupTypeInterface $drupal_group_type
   *   Drupal group type.
   * @param \Drupal\group\Entity\GroupRoleInterface|null $drupal_group_role
   *   Drupal group role, or NULL for all group roles of the given type.
   * @param \Drupal\group\Entity\GroupInterface $drupal_group
   *   Drupal group.
   */
  private function processGroup(
    GroupsInNamespaceImage $image,
    GroupTypeInterface $drupal_group_type,
    ?GroupRoleInterface $drupal_group_role,
    GroupInterface $drupal_group,
  ): void {
    $drupal_group_roles = $drupal_group_role
      ? [$drupal_group_role]
      : $drupal_group_type->getRoles();
    foreach ($drupal_group_roles as $drupal_group_role) {
      $group_id = $this->groupIdBuilder->buildGroupId(
        $drupal_group,
        $drupal_group_role,
      );
      if (!$group_id) {
        // No group should be created for this role.
        continue;
      }
      $image->addGroup(
        $group_id,
        $this->buildGroupLabel(
          $drupal_group,
          $drupal_group_role,
        ),
      );
    }
  }

  /**
   * Builds a display name for a Nextcloud group.
   *
   * @param \Drupal\group\Entity\GroupInterface $drupal_group
   *   Drupal group.
   * @param \Drupal\group\Entity\GroupRoleInterface $role
   *   Drupal group role.
   *
   * @return string
   *   Display name.
   */
  private function buildGroupLabel(GroupInterface $drupal_group, GroupRoleInterface $role): string {
    $role_bitmask = $this->buildGroupPerms($role);
    // @todo Come up with a nicer naming pattern.
    $perm_string = implode('', array_filter(
      GroupFolderConstants::PERMISSIONS_SHORTCODE_MAP,
      static fn (int $perm_bitmask) => $perm_bitmask & $role_bitmask,
      ARRAY_FILTER_USE_KEY,
    ));
    return 'D:G:' . $drupal_group->id() . ':' . $perm_string . ': ' . $drupal_group->label() . ': ' . $role->label();
  }

  /**
   * Builds a permissions bitmask for a group.
   *
   * @param \Drupal\group\Entity\GroupRoleInterface $role
   *   The role that the group was created for.
   *
   * @return int
   *   Bitmask to define permissions of the group on its group folder.
   */
  private function buildGroupPerms(GroupRoleInterface $role): int {
    $role_permissions_by_bitmask = array_intersect(
      GroupFolderConstants::PERMISSIONS_MAP,
      $role->getPermissions(),
    );
    return DataUtil::bitwiseOr(...array_keys($role_permissions_by_bitmask));
  }

}
