<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Tracking\RecordSubmit;

use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud\Exception\FailureResponseException;
use Drupal\poc_nextcloud\Tracking\Tracker;

/**
 * Writes queued user data to Nextcloud.
 */
class NcUserSubmit implements TrackingRecordSubmitInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $userEndpoint
   *   User endpoint.
   * @param bool $keepNcUsers
   *   TRUE to keep Nextcloud user accounts, when the Drupal account is deleted.
   *     When a new user is created in Drupal, and a Nextcloud user already
   *     exists with that name, the existing Nextcloud account will be linked to
   *     the new Drupal user.
   *   FALSE to delete Nextcloud user accounts when a Drupal account is deleted.
   *     When a new user is created in Drupal, and a Nextcloud user already
   *     exists with that name, a conflict occurs.
   */
  public function __construct(
    private NxUserEndpoint $userEndpoint,
    private bool $keepNcUsers = TRUE,
  ) {}

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function submitTrackingRecord(array &$record, int $op): void {
    [
      'nc_user_id' => $user_id,
      'nc_email' => &$email,
      'nc_display_name' => &$display_name,
    ] = $record;

    switch ($op) {
      case Tracker::OP_UPDATE:
        $this->userEndpoint->setUserEmail($user_id, $email);
        $this->userEndpoint->setUserDisplayName($user_id, $display_name);
        return;

      case Tracker::OP_INSERT:
        try {
          $this->userEndpoint->insertWithEmail(
            $user_id,
            $email,
            $display_name,
          );
        }
        catch (FailureResponseException $e) {
          if ($e->getResponseStatusCode() !== 102) {
            // Something else went wrong.
            throw $e;
          }
          // User already exists.
          if (!$this->keepNcUsers) {
            // Existing Nextcloud users with same name are treated as
            // conflicting.
            throw new \Exception('User already exists.');
          }
          // Take over the existing account and update it.
          // @todo Add configuration option to not update these values.
          $this->userEndpoint->setUserEmail($user_id, $email);
          $this->userEndpoint->setUserDisplayName($user_id, $display_name);
        }
        return;

      case Tracker::OP_DELETE:
        // Delete nc user, if exists.
        if ($this->keepNcUsers) {
          // Do not delete, but still report success.
          return;
        }
        // @todo Assume it exists, throw exception if not?
        $this->userEndpoint->deleteIfExists($user_id);
        return;

      case Tracker::OP_READ:
        // Read from Nextcloud instead of writing.
        // @todo Determine if this should be in a separate method or the same.
        $nc_user = $this->userEndpoint->load($user_id);
        if ($nc_user === NULL) {
          $record = NULL;
        }
        else {
          $email = $nc_user->getEmail();
          $display_name = $nc_user->getDisplayName();
        }
        return;

      default:
        throw new \InvalidArgumentException(sprintf('Unexpected operation %d.', $op));
    }
  }

}
