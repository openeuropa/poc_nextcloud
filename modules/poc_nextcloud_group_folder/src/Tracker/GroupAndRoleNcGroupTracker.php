<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\Tracker;

use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\group\Entity\GroupTypeInterface;
use Drupal\hux\Attribute\Hook;
use Drupal\poc_nextcloud\Tracking\RecordSubmit\NcGroupSubmit;
use Drupal\poc_nextcloud\Tracking\Tracker\TrackerBase;
use Drupal\poc_nextcloud\Tracking\TrackingTableFactory;
use Drupal\poc_nextcloud_group_folder\GroupFolderConstants;
use Drupal\poc_nextcloud_group_folder\Service\DrupalGroupLoader;

/**
 * Queues up user data for write to Nextcloud.
 */
class GroupAndRoleNcGroupTracker extends TrackerBase {

  const TABLE_NAME = 'poc_nextcloud_group_role_nc_group';

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
      NcGroupSubmit::class,
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
        ->addRemotePrimaryField('nc_group_id', [
          'description' => 'Nextcloud group id',
          'type' => 'varchar',
          // Id length as in Nextcloud database.
          'length' => 64,
          'not null' => TRUE,
        ])
        ->addDataField('nc_display_name', [
          'description' => 'Nextcloud group display name',
          'type' => 'varchar',
          // Display name length as in Nextcloud database.
          'length' => 255,
          'not null' => TRUE,
        ]),
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
    // Mark all nc groups for this Drupal group for deletion.
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
    // @todo Determine if group should be in Nextcloud.
    foreach ($group->getGroupType()->getRoles() as $group_role) {
      if (!$this->groupRoleShouldHaveNcGroup($group_role)) {
        // This role should not have a group in Nextcloud.
        // This case is covered by ->groupRoleWrite().
        continue;
      }
      $this->queueWriteGroupAndRole($group, $group_role);
    }
  }

  /**
   * Implements hook_group_type_update().
   *
   * For insert and delete, everything is already done for the group role hook.
   *
   * @param \Drupal\group\Entity\GroupTypeInterface $group_type
   *   Drupal group type.
   */
  #[Hook('group_type_update')]
  public function groupTypeOp(GroupTypeInterface $group_type): void {
    // It could happen that the group type configuration changes whether
    // groups and group roles should get a Nextcloud group or not.
    foreach ($group_type->getRoles() as $group_role) {
      // Pretend this is a group role update, it will do everything we need.
      $this->groupRoleWrite($group_role);
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
    if (!$this->groupRoleShouldHaveNcGroup($group_role)) {
      $this->trackingTable->queueDelete(['group_role_id' => $group_role->id()]);
    }
    else {
      $group_type = $group_role->getGroupType();
      $groups = $this->drupalGroupLoader->loadGroupsForType($group_type);
      foreach ($groups as $group) {
        // @todo Determine if group should be in Nextcloud.
        $this->queueWriteGroupAndRole($group, $group_role);
      }
    }
  }

  /**
   * Queues a Nextcloud group for a Drupal group and group role.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Drupal group.
   * @param \Drupal\group\Entity\GroupRoleInterface $group_role
   *   Drupal group role.
   */
  private function queueWriteGroupAndRole(GroupInterface $group, GroupRoleInterface $group_role): void {
    $nc_group_id = sprintf(
      'DRUPAL-GROUP-%s-%s',
      $group->id(),
      $group_role->id(),
    );
    $nc_display_name = $group->label() . ': ' . $group_role->label();
    $this->trackingTable->queueWrite([
      'gid' => $group->id(),
      'group_role_id' => $group_role->id(),
      'nc_group_id' => $nc_group_id,
      'nc_display_name' => $nc_display_name,
    ]);
  }

  /**
   * Determines if Nextcloud groups should be created for a Drupal group role.
   *
   * @param \Drupal\group\Entity\GroupRoleInterface $group_role
   *   Group role.
   *
   * @return bool
   *   TRUE if a group should be created in Nextcloud specific to this group
   *   role for every Drupal group that has this role.
   */
  private function groupRoleShouldHaveNcGroup(GroupRoleInterface $group_role): bool {
    return (bool) array_intersect(
      GroupFolderConstants::PERMISSIONS_MAP,
      $group_role->getPermissions(),
    );
  }

}
