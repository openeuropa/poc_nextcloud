<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\Tracker;

use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\hux\Attribute\Hook;
use Drupal\poc_nextcloud\DataUtil;
use Drupal\poc_nextcloud\Tracking\RecordSubmit\NcGroupFolderGroupSubmit;
use Drupal\poc_nextcloud\Tracking\Tracker\TrackerBase;
use Drupal\poc_nextcloud\Tracking\TrackingTableFactory;
use Drupal\poc_nextcloud\Tracking\TrackingTableRelationship;
use Drupal\poc_nextcloud_group_folder\GroupFolderConstants;
use Drupal\poc_nextcloud_group_folder\Service\DrupalGroupLoader;

/**
 * Tracker to control Nextcloud group access to Nextcloud group folders.
 *
 * The access is based on Drupal groups and group roles.
 */
class GroupAndRoleNcGroupFolderGroupTracker extends TrackerBase {

  const TABLE_NAME = 'poc_nextcloud_group_role_nc_group_folder_group';

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Tracking\TrackingTableFactory $trackingTableFactory
   *   Tracking table factory.
   * @param \Drupal\poc_nextcloud_group_folder\Service\DrupalGroupLoader $drupalGroupLoader
   *   Drupal group loader.
   */
  public function __construct(
    TrackingTableFactory $trackingTableFactory,
    private DrupalGroupLoader $drupalGroupLoader,
  ) {
    parent::__construct(
      NcGroupFolderGroupSubmit::class,
      $trackingTableFactory->create(self::TABLE_NAME)
        ->addLocalPrimaryField('gid', [
          'description' => 'Drupal group id',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ])
        ->addLocalPrimaryField('group_role_id', [
          'description' => 'Drupal group role id',
          'type' => 'varchar',
          'length' => 254,
          'not null' => TRUE,
        ])
        ->addDataField('nc_permissions', [
          'description' => 'Permissions bitmask for read, write, share, acl.',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ])
        ->addParentTableRelationship('gf', new TrackingTableRelationship(
          GroupNcGroupFolderTracker::TABLE_NAME,
          ['gid' => 'gid'],
          ['nc_group_folder_id'],
          // @todo Check if auto delete really works in this case.
          TRUE,
        ))
        ->addParentTableRelationship('g', new TrackingTableRelationship(
          GroupAndRoleNcGroupTracker::TABLE_NAME,
          ['gid' => 'gid', 'group_role_id' => 'group_role_id'],
          ['nc_group_id'],
          // @todo Check if auto delete really works in this case.
          TRUE,
        )),
    );
  }

  /**
   * Implements hook_group_delete().
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Drupal group.
   */
  #[Hook('group_delete')]
  public function groupDelete(GroupInterface $group): void {
    // Mark all Nc groups for this Drupal group for deletion.
    $this->trackingTable->queueDelete(['gid' => $group->id()]);
  }

  /**
   * Implements hook_group_insert() and hook_group_update().
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Drupal group.
   */
  #[Hook('group_insert'), Hook('group_update')]
  public function groupWrite(GroupInterface $group): void {
    // @todo Determine if Drupal group should have Nc groups and group
    //   folder.
    foreach ($group->getGroupType()->getRoles() as $group_role) {
      $this->queueWriteGroupAndRole($group, $group_role);
    }
  }

  /**
   * Implements hook_group_role_delete().
   *
   * @param \Drupal\group\Entity\GroupRoleInterface $group_role
   *   Drupal group role.
   */
  #[Hook('group_role_delete')]
  public function groupRoleDelete(GroupRoleInterface $group_role): void {
    // Mark all nc groups for this Drupal group role for deletion.
    $this->trackingTable->queueDelete(['group_role_id' => $group_role->id()]);
  }

  /**
   * Implements hook_group_role_insert() and hook_group_role_update().
   *
   * @param \Drupal\group\Entity\GroupRoleInterface $group_role
   *   Drupal group role.
   */
  #[Hook('group_role_insert'), Hook('group_role_update')]
  public function groupRoleWrite(GroupRoleInterface $group_role): void {
    $group_type = $group_role->getGroupType();
    $groups = $this->drupalGroupLoader->loadGroupsForType($group_type);
    foreach ($groups as $group) {
      // @todo Determine if group should be in Nextcloud.
      $this->queueWriteGroupAndRole($group, $group_role);
    }
  }

  /**
   * Updates the tracking record for a group and group role.
   *
   * @param \Drupal\group\Entity\GroupInterface|null $group
   *   Drupal group, or NULL to process all groups for the group type.
   * @param \Drupal\group\Entity\GroupRoleInterface $group_role
   *   Drupal group role.
   */
  private function queueWriteGroupAndRole(?GroupInterface $group, GroupRoleInterface $group_role): void {
    // Get a permissions bitmask for read, write, share, acl.
    $nc_permissions = DataUtil::bitwiseOr(...array_keys(array_intersect(
      GroupFolderConstants::PERMISSIONS_MAP,
      $group_role->getPermissions(),
    )));
    if (!$nc_permissions) {
      if ($group !== NULL) {
        $this->trackingTable->queueDelete([
          'gid' => $group->id(),
          'group_role_id' => $group_role->id(),
        ]);
      }
      else {
        $this->trackingTable->queueDelete([
          'group_role_id' => $group_role->id(),
        ]);
      }
    }
    else {
      if (($group !== NULL)) {
        $groups = [$group];
      }
      else {
        $groups = $this->drupalGroupLoader->loadGroupsForType(
          $group_role->getGroupType(),
        );
      }
      foreach ($groups as $group) {
        $this->trackingTable->queueWrite([
          'gid' => $group->id(),
          'group_role_id' => $group_role->id(),
          // Filter for read, write, share, acl.
          'nc_permissions' => $nc_permissions,
        ]);
      }
    }
  }

}
