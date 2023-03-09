<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\EntityObserver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint;
use Drupal\poc_nextcloud\EntityObserver\EntityObserverInterface;
use Drupal\poc_nextcloud\NxEntity\NxGroupFolder;
use Drupal\poc_nextcloud_group_folder\Service\GroupToGroupFolderMap;

/**
 * Creates Nextcloud group folders for Drupal groups.
 */
class NextcloudGroupFolders implements EntityObserverInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud_group_folder\Service\GroupToGroupFolderMap $groupToGroupFolderMap
   *   Service to map Drupal groups to Nextcloud group folders.
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint $groupFolderEndpoint
   *   Group folder endpoint.
   */
  public function __construct(
    private GroupToGroupFolderMap $groupToGroupFolderMap,
    private NxGroupFolderEndpoint $groupFolderEndpoint,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function entityOp(EntityInterface $entity, string $op): void {
    if ($entity instanceof GroupInterface) {
      $this->groupOp($entity, $op);
    }
  }

  /**
   * Responds to a group entity hook.
   *
   * @param \Drupal\group\Entity\GroupInterface $drupal_group
   *   Drupal group.
   * @param string $op
   *   Verb from the entity hook.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function groupOp(GroupInterface $drupal_group, string $op): void {
    $group_folder_id = $this->groupToGroupFolderMap->groupGetGroupFolderId($drupal_group);
    $group_folder = NULL;
    if ($group_folder_id) {
      $group_folder = $this->groupFolderEndpoint->load($group_folder_id);
    }
    switch ($op) {
      case 'presave':
        $this->groupPresave(
          $drupal_group,
          $group_folder_id,
          $group_folder,
        );
        break;

      case 'delete':
        if ($group_folder !== NULL) {
          $this->groupFolderEndpoint->delete($group_folder->getId());
        }
        break;

      case 'update':
      case 'insert':
        if ($group_folder !== NULL) {
          $mountpoint = $this->createMountPoint($drupal_group);
          if ($group_folder->getMountPoint() !== $mountpoint) {
            $this->groupFolderEndpoint->setMountPoint($group_folder->getId(), $mountpoint);
          }
        }
        break;
    }
  }

  /**
   * Responds to a group presave hook.
   *
   * @param \Drupal\group\Entity\GroupInterface $drupal_group
   *   Drupal group.
   * @param int|null $group_folder_id
   *   Group folder id that is or was associated with the group.
   * @param \Drupal\poc_nextcloud\NxEntity\NxGroupFolder|null $group_folder
   *   Group folder for the id, if exists.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function groupPresave(GroupInterface $drupal_group, ?int $group_folder_id, ?NxGroupFolder $group_folder): void {
    $should_have_nextcloud_group_folder = $this->groupToGroupFolderMap->groupShouldHaveGroupFolder($drupal_group);
    if (!$should_have_nextcloud_group_folder) {
      if ($group_folder_id === NULL) {
        // The group already doesn't have a group folder id.
        return;
      }
      if ($group_folder !== NULL) {
        $this->groupFolderEndpoint->delete($group_folder->getId());
      }
      $this->groupToGroupFolderMap->groupSetGroupFolderId($drupal_group, NULL);
    }
    else {
      if ($group_folder !== NULL) {
        // The group already has an existing group folder.
        return;
      }
      // If the id is not empty, it will be replaced now.
      $mountpoint = $this->createMountPoint($drupal_group);
      $group_folder_id = $this->groupFolderEndpoint->insertWithMountPoint($mountpoint);
      // Store the id.
      $this->groupToGroupFolderMap->groupSetGroupFolderId($drupal_group, $group_folder_id);
    }
  }

  /**
   * Builds a mount point for a group folder.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Drupal group.
   *
   * @return string
   *   Mount point.
   */
  private function createMountPoint(GroupInterface $group): string {
    // @todo Does the name need to be sanitized? Should we add the id?
    return (string) $group->label();
  }

}
