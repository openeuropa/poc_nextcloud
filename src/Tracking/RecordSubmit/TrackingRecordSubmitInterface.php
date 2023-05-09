<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Tracking\RecordSubmit;

/**
 * Handler to write tracked data to Nextcloud.
 *
 * @template RecordType as array
 */
interface TrackingRecordSubmitInterface {

  /**
   * Submits changes from a tracking record to the Nextcloud API.
   *
   * @param array $record
   *   Record from a tracking table.
   *   This is by-reference, allowing the method to alter some values.
   *   This is necessary for auto-increment ids.
   * @param int $op
   *   Pending operation. Could be insert, update, delete.
   *
   * @psalm-param RecordType $record
   *
   * @throws \Exception
   *   Failed to write to the Nextcloud instance.
   */
  public function submitTrackingRecord(array &$record, int $op): void;

}
