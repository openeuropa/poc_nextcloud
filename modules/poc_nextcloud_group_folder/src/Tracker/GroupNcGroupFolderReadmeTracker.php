<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\Tracker;

use Drupal\group\Entity\GroupInterface;
use Drupal\hux\Attribute\Hook;
use Drupal\poc_nextcloud\Tracking\RecordSubmit\NcGroupFolderReadmeSubmit;
use Drupal\poc_nextcloud\Tracking\Tracker\TrackerBase;
use Drupal\poc_nextcloud\Tracking\TrackingTableFactory;
use Drupal\poc_nextcloud\Tracking\TrackingTableRelationship;

/**
 * Tracks the README.md file for group folders.
 */
class GroupNcGroupFolderReadmeTracker extends TrackerBase {

  const TABLE_NAME = 'poc_nextcloud_group_nc_group_folder_readme';

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
      NcGroupFolderReadmeSubmit::class,
      $trackingTableFactory->create(
        self::TABLE_NAME,
        ['gid'],
      ),
      [
        'g' => new TrackingTableRelationship(
          GroupNcGroupFolderTracker::TABLE_NAME,
          ['gid' => 'gid'],
          ['nc_group_folder_id', 'nc_mount_point'],
          TRUE,
        ),
      ],
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
   *
   * @throws \Exception
   *   Data integrity problem.
   */
  #[Hook('group_insert'), Hook('group_update')]
  public function groupWrite(GroupInterface $group): void {
    // Queue the README.md even if the group folder won't be created.
    // The JOIN will make sure that the README.md is never created in that case.
    // Doing it this way makes for a better separation of code.
    $this->trackingTable->queueWrite([
      'gid' => $group->id(),
      'nc_readme_content' => sprintf(
        'Drupal group: [%s](%s)',
        // @todo Sanitize the text and url for Markdown.
        $group->label(),
        $group->toUrl()->setAbsolute()->toString(),
      ),
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
      'nc_readme_content' => [
        'description' => 'Content of a README.md for a group folder.',
        'type' => 'text',
        'size' => 'normal',
        'not null' => FALSE,
      ],
    ];
  }

}
