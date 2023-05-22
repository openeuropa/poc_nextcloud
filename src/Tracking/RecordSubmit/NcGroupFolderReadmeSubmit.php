<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Tracking\RecordSubmit;

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint;
use Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud\Endpoint\NxWebdavEndpoint;
use Drupal\poc_nextcloud\Exception\NextcloudApiException;
use Drupal\poc_nextcloud\Tracking\Op;

/**
 * Writes pending group folders to Nextcloud.
 */
class NcGroupFolderReadmeSubmit implements TrackingRecordSubmitInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Connection\ApiConnectionInterface $connection
   *   Nextcloud API connection.
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint $groupEndpoint
   *   Group endpoint.
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $userEndpoint
   *   User endpoint.
   * @param \Drupal\poc_nextcloud\Endpoint\NxWebdavEndpoint $webdavEndpoint
   *   Webdav endpoint.
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint $groupFolderEndpoint
   *   Group folder endpoint.
   */
  public function __construct(
    private ApiConnectionInterface $connection,
    private NxGroupEndpoint $groupEndpoint,
    private NxUserEndpoint $userEndpoint,
    private NxWebdavEndpoint $webdavEndpoint,
    private NxGroupFolderEndpoint $groupFolderEndpoint,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function submitTrackingRecord(array &$record, string $op): void {
    [
      'nc_group_folder_id' => $group_folder_id,
      'nc_mount_point' => $mount_point,
      'nc_readme_content' => $readme_content,
    ] = $record;

    switch ($op) {
      case Op::UPDATE:
      case Op::INSERT:
        $cancel_tmp_access = $this->giveTemporaryAccess((int) $group_folder_id);
        try {
          $this->webdavEndpoint->writeFile($mount_point . '/README.md', $readme_content);
        }
        finally {
          $cancel_tmp_access();
        }
        break;

      case Op::DELETE:
        // The readme will be deleted automatically with the group folder.
        // Nothing to do.
        break;

      default:
        throw new \RuntimeException('Unexpected operation.');
    }
  }

  /**
   * Temporarily gives access to a Nextcloud group folder.
   *
   * Doing this minimizes the risk that Nextcloud chooses a different mount
   * point for the group folder due to name clashes.
   *
   * @param int $group_folder_id
   *   Group folder id.
   *
   * @return callable
   *   Callback to cancel the access and clean up.´
   */
  private function giveTemporaryAccess(int $group_folder_id): callable {
    $tmp_group_id = 'tmp_group_folder_manager';
    $nextcloud_user_id = $this->connection->getUserId();
    try {
      $this->groupEndpoint->insert($tmp_group_id);
    }
    catch (NextcloudApiException) {
      // Likely the group already exists.
      // Ignore and move on.
    }
    try {
      $this->groupFolderEndpoint->addGroup($group_folder_id, $tmp_group_id);
    }
    catch (NextcloudApiException) {
      // Likely the group is already attached to the group folder.
      // Ignore and move on.
    }
    try {
      $this->userEndpoint->joinGroup($nextcloud_user_id, $tmp_group_id);
    }
    catch (NextcloudApiException) {
      // Likely the user is already member of the group.
      // Ignore and move on.
    }
    return function () use ($tmp_group_id) {
      $this->groupEndpoint->delete($tmp_group_id);
    };
  }

}
