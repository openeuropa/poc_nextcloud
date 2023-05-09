<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\Tracker;

use Drupal\group\Entity\GroupInterface;
use Drupal\hux\Attribute\Hook;
use Drupal\poc_nextcloud\Tracking\RecordSubmit\NcGroupFolderSubmit;
use Drupal\poc_nextcloud\Tracking\Tracker\TrackerBase;
use Drupal\poc_nextcloud\Tracking\TrackingTableFactory;

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
      $trackingTableFactory->create(
        self::TABLE_NAME,
        ['gid'],
      ),
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
    // @todo Distinguish if group should have a group folder.
    // @todo Find a better mount point pattern.
    $nc_mount_point = (string) $group->label();
    $this->trackingTable->queueWrite([
      'gid' => $group->id(),
      'nc_mount_point' => $nc_mount_point,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function alterTableSchema(array &$table_schema): void {
    $table_schema['fields'] += [
      'gid' => [
        'description' => 'Drupal group id',
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => TRUE,
      ],
      // The group folder id is filled in once the group folder is created in
      // Nextcloud.
      'nc_group_folder_id' => [
        'description' => 'Nextcloud group folder id, or NULL if not created yet.',
        'type' => 'int',
        'unsigned' => TRUE,
      ],
      'nc_mount_point' => [
        'description' => 'Nextcloud group folder mount point',
        'type' => 'varchar',
        // Display name length as in Nextcloud database.
        'length' => 64,
        'not null' => TRUE,
      ],
    ];
  }

}
