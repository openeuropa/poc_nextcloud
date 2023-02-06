<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\EntityObserver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\group\Entity\GroupTypeInterface;
use Drupal\poc_nextcloud\DataUtil;
use Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint;
use Drupal\poc_nextcloud\EntityObserver\EntityObserverInterface;
use Drupal\poc_nextcloud\WritableImage\GroupFoldersGroupsImage;
use Drupal\poc_nextcloud_group_folder\GroupFolderConstants;
use Drupal\poc_nextcloud_group_folder\Service\DrupalGroupLoader;
use Drupal\poc_nextcloud_group_folder\Service\GroupRoleToGroupId;
use Drupal\poc_nextcloud_group_folder\Service\GroupToGroupFolderMap;

/**
 * Adds Nextcloud groups to Nextcloud group folders.
 */
class NextcloudGroupFolderGroupPerms implements EntityObserverInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud_group_folder\Service\GroupToGroupFolderMap $groupToGroupFolderMap
   *   Service to map Drupal groups to Nextcloud group folders.
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint $groupFolderEndpoint
   *   Nextcloud API endpoint for user accounts.
   * @param \Drupal\poc_nextcloud_group_folder\Service\GroupRoleToGroupId $groupIdBuilder
   *   Group id builder.
   * @param \Drupal\poc_nextcloud_group_folder\Service\DrupalGroupLoader $drupalGroupLoader
   *   Service to load groups and group types.
   */
  public function __construct(
    private GroupToGroupFolderMap $groupToGroupFolderMap,
    private NxGroupFolderEndpoint $groupFolderEndpoint,
    private GroupRoleToGroupId $groupIdBuilder,
    private DrupalGroupLoader $drupalGroupLoader,
  ) {}

  /**
   * Updates group permissions on all group folders.
   *
   * Currently this is not used, but we keep it.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function rebuildAll(): void {
    $this->updateScope(NULL, NULL, NULL, NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function entityOp(EntityInterface $entity, string $op): void {
    if (!in_array($op, ['update', 'insert', 'delete'])) {
      return;
    }
    if ($entity instanceof GroupInterface) {
      $group_folder_id = $this->groupToGroupFolderMap->groupGetGroupFolderId($entity);
      if ($group_folder_id === NULL) {
        if ($op !== 'update') {
          return;
        }
        $original = $entity->original ?? NULL;
        if (!$original) {
          return;
        }
        $group_folder_id = $this->groupToGroupFolderMap->groupGetGroupFolderId($original);
        if ($group_folder_id === NULL) {
          return;
        }
      }
      $this->updateScope(
        $entity->getGroupType(),
        NULL,
        $entity,
        $group_folder_id,
      );
    }
    elseif ($entity instanceof GroupTypeInterface) {
      $this->updateScope(
        $entity,
        NULL,
        NULL,
        NULL,
      );
    }
    elseif ($entity instanceof GroupRoleInterface) {
      $this->updateScope(
        $entity->getGroupType(),
        $entity,
        NULL,
        NULL,
      );
    }
  }

  /**
   * Updates groups for a range of group folders.
   *
   * @param \Drupal\group\Entity\GroupTypeInterface|null $drupal_group_type
   *   A Drupal group type, or NULL for all group types.
   * @param \Drupal\group\Entity\GroupRoleInterface|null $drupal_group_role
   *   A Drupal group role, or NULL for all roles for the given type(s).
   *   If $drupal_group_type is NULL, this will also be NULL.
   * @param \Drupal\group\Entity\GroupInterface|null $drupal_group
   *   A Drupal group, or NULL to update all groups for the given type(s).
   *   If $drupal_group_type is NULL, this will also be NULL.
   * @param int|null $group_folder_id
   *   Group folder id that is or was associated with the group.
   *   If $drupal_group is NULL, this will also be NULL.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function updateScope(
    ?GroupTypeInterface $drupal_group_type,
    ?GroupRoleInterface $drupal_group_role,
    ?GroupInterface $drupal_group,
    ?int $group_folder_id,
  ): void {
    $image = new GroupFoldersGroupsImage(
      $this->groupFolderEndpoint,
      $this->groupIdBuilder->getGroupNamespaceRegex(
        // @todo Consider to always use NULL here.
        //   This way, when updating a single Drupal group, any other groups
        //   would be removed from that group folder.
        //   The only problem would be if two Drupal groups reference the same
        //   Nextcloud group folder.
        $drupal_group,
        $drupal_group_role ?? $drupal_group_type,
      ),
      $group_folder_id === NULL,
    );
    if ($drupal_group !== NULL) {
      if ($group_folder_id) {
        $this->processGroup(
          $image,
          $drupal_group_type,
          $drupal_group_role,
          $drupal_group,
          $group_folder_id,
        );
      }
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
   * @param \Drupal\poc_nextcloud\WritableImage\GroupFoldersGroupsImage $image
   *   Image to fill with groups and permissions.
   * @param \Drupal\group\Entity\GroupTypeInterface $drupal_group_type
   *   Group type.
   * @param \Drupal\group\Entity\GroupRoleInterface|null $drupal_group_role
   *   Group role.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function processGroupType(
    GroupFoldersGroupsImage $image,
    GroupTypeInterface $drupal_group_type,
    ?GroupRoleInterface $drupal_group_role,
  ): void {
    $drupal_groups = $this->drupalGroupLoader->loadGroupsForType($drupal_group_type);
    foreach ($drupal_groups as $drupal_group) {
      $group_folder_id = $this->groupToGroupFolderMap
        ->groupGetGroupFolderId($drupal_group);
      if (!$group_folder_id) {
        return;
      }
      $this->processGroup(
        $image,
        $drupal_group_type,
        $drupal_group_role,
        $drupal_group,
        $group_folder_id,
      );
    }
  }

  /**
   * Processes a group.
   *
   * @param \Drupal\poc_nextcloud\WritableImage\GroupFoldersGroupsImage $image
   *   Image to fill with groups and permissions.
   * @param \Drupal\group\Entity\GroupTypeInterface $drupal_group_type
   *   Group type.
   * @param \Drupal\group\Entity\GroupRoleInterface|null $drupal_group_role
   *   Group role.
   * @param \Drupal\group\Entity\GroupInterface $drupal_group
   *   Group.
   * @param int $group_folder_id
   *   Group folder id.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function processGroup(
    GroupFoldersGroupsImage $image,
    GroupTypeInterface $drupal_group_type,
    ?GroupRoleInterface $drupal_group_role,
    GroupInterface $drupal_group,
    int $group_folder_id,
  ): void {
    $drupal_group_roles = $drupal_group_role
      ? [$drupal_group_role]
      : $drupal_group_type->getRoles();
    $group_folder = $this->groupFolderEndpoint->load($group_folder_id);
    if (!$group_folder) {
      return;
    }
    foreach ($drupal_group_roles as $a_drupal_group_role) {
      $nextcloud_perms = DataUtil::bitwiseOr(...array_keys(array_intersect(
        GroupFolderConstants::PERMISSIONS_MAP,
        $a_drupal_group_role->getPermissions(),
      )));
      if (!$nextcloud_perms) {
        continue;
      }
      $group_id = $this->groupIdBuilder->buildGroupId(
        $drupal_group,
        $a_drupal_group_role,
      );
      if (!$group_id) {
        continue;
      }
      $image->groupFolderAddGroup(
        $group_folder_id,
        $group_id,
        $nextcloud_perms,
      );
    }
  }

}
