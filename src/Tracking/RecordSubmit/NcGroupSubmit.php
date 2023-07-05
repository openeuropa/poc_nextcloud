<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Tracking\RecordSubmit;

use Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint;
use Drupal\poc_nextcloud\Tracking\Op;

/**
 * Writes pending groups to Nextcloud.
 */
class NcGroupSubmit implements TrackingRecordSubmitInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint $groupEndpoint
   *   Group endpoint.
   */
  public function __construct(
    private NxGroupEndpoint $groupEndpoint,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function submitTrackingRecord(array &$record, string $op): void {
    [
      'nc_group_id' => $group_id,
      'nc_display_name' => $display_name,
    ] = $record;

    switch ($op) {
      case Op::UPDATE:
        try {
          $this->groupEndpoint->setDisplayName($group_id, $display_name);
        }
        catch (\Exception $e) {
          $this->groupEndpoint->insert($group_id, $display_name);
        }
        break;

      case Op::INSERT:
        try {
          $this->groupEndpoint->insert($group_id, $display_name);
        }
        catch (\Exception $e) {
          $this->groupEndpoint->setDisplayName($group_id, $display_name);
        }
        break;

      case Op::DELETE:
        $this->groupEndpoint->delete($group_id);
        break;

      default:
        throw new \RuntimeException('Unexpected operation.');
    }
  }

}
