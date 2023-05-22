<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Tracking\RecordSubmit;

use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud\Tracking\Op;

/**
 * Writes queued user data to Nextcloud.
 */
class NcUserGroupSubmit implements TrackingRecordSubmitInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $userEndpoint
   *   User endpoint.
   */
  public function __construct(
    private NxUserEndpoint $userEndpoint,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function submitTrackingRecord(array &$record, string $op): void {
    [
      'nc_user_id' => $username,
      'nc_group_id' => $group_id,
    ] = $record;

    // Only support insert and delete. Update is meaningless in this case.
    switch ($op) {
      case Op::INSERT:
        $this->userEndpoint->joinGroup($username, $group_id);
        break;

      case Op::DELETE:
        $this->userEndpoint->leaveGroup($username, $group_id);
        break;
    }
  }

}
