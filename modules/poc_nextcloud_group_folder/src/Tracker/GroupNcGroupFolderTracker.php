<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\Tracker;

use Drupal\group\Entity\GroupInterface;
use Drupal\hux\Attribute\Hook;
use Drupal\poc_nextcloud\Tracking\RecordSubmit\NcGroupFolderSubmit;
use Drupal\poc_nextcloud\Tracking\Tracker\TrackerBase;
use Drupal\poc_nextcloud\Tracking\TrackingTableFactory;
use Drupal\poc_nextcloud_group_folder\Hooks\EntityBaseFieldInfo\GroupBaseField;

/**
 * Queues up user data for write to Nextcloud.
 */
class GroupNcGroupFolderTracker extends TrackerBase {

  const TABLE_NAME = 'poc_nextcloud_group_nc_group_folder';

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Tracking\TrackingTableFactory $trackingTableFactory
   *   Tracking table factory.
   */
  public function __construct(
    TrackingTableFactory $trackingTableFactory,
  ) {
    parent::__construct(
      NcGroupFolderSubmit::class,
      $trackingTableFactory->create(self::TABLE_NAME)
        ->addLocalPrimaryField('gid', [
          'description' => 'Drupal group id',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ])
        ->addRemoteControlledField('nc_group_folder_id', [
          'description' => 'Nextcloud group folder id, or NULL if not created yet.',
          'type' => 'int',
          'unsigned' => TRUE,
        ])
        ->addDataField('nc_mount_point', [
          'description' => 'Nextcloud group folder mount point',
          'type' => 'varchar',
          // Display name length as in Nextcloud database.
          'length' => 64,
          'not null' => TRUE,
        ]),
    );
  }

  /**
   * Implements hook_group_delete().
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Drupal group.
   *
   * @throws \Exception
   *   Data integrity problem.
   */
  #[Hook('group_delete')]
  public function groupDelete(GroupInterface $group): void {
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
    if (!$this->groupShouldHaveGroupFolder($group)) {
      $this->trackingTable->queueDelete(['gid' => $group->id()]);
      return;
    }
    $label = (string) $group->label();
    // Use a sanitized version of the group label as the mount point.
    // @todo Does the length need to be limited?
    $nc_mount_point = preg_replace('@\W+@', '-', $label);
    $this->trackingTable->queueWrite([
      'gid' => $group->id(),
      'nc_mount_point' => $nc_mount_point,
    ]);
  }

  /**
   * Determines if a group should have a Nextcloud group folder.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The Drupal group.
   *
   * @return bool
   *   TRUE, if a group folder should be created in Nextcloud.
   *   FALSE, if none should be created, and an existing one removed.
   */
  private function groupShouldHaveGroupFolder(GroupInterface $group): bool {
    return GroupBaseField::evaluate($group);
  }

}
