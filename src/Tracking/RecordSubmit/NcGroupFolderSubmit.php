<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Tracking\RecordSubmit;

use Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint;
use Drupal\poc_nextcloud\Tracking\Op;

/**
 * Writes pending group folders to Nextcloud.
 */
class NcGroupFolderSubmit implements TrackingRecordSubmitInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint $groupFolderEndpoint
   *   Group folder endpoint.
   */
  public function __construct(
    private NxGroupFolderEndpoint $groupFolderEndpoint,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function submitTrackingRecord(array &$record, int $op): void {
    [
      'nc_mount_point' => $mount_point,
    ] = $record;
    $group_folder_id = (int) $record['nc_group_folder_id'] ?: NULL;

    switch ($op) {
      case Op::UPDATE:
        if ($group_folder_id === NULL) {
          throw new \Exception('Tracking record is missing the group folder id.');
        }
        $this->groupFolderEndpoint->setMountPoint($group_folder_id, $mount_point);
        break;

      case Op::INSERT:
        if ($group_folder_id !== NULL) {
          throw new \Exception('Tracking record is marked for insert already has a group folder id.');
        }
        $record['nc_group_folder_id'] = $this->groupFolderEndpoint->insertWithMountPoint($mount_point);
        break;

      case Op::DELETE:
        if ($group_folder_id === NULL) {
          throw new \Exception('Tracking record is missing the group folder id.');
        }
        $this->groupFolderEndpoint->deleteIfExists($group_folder_id);
        break;

      default:
        throw new \RuntimeException('Unexpected operation.');
    }
  }

}
