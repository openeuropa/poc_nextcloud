<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Tracking\Tracker;

use Drupal\hux\Attribute\Hook;
use Drupal\poc_nextcloud\Tracking\RecordSubmit\NcUserSubmit;
use Drupal\poc_nextcloud\Tracking\TrackingTableFactory;
use Drupal\user\UserInterface;

/**
 * Queues up user data for write to Nextcloud.
 */
class UserNcUserTracker extends TrackerBase {

  const TABLE_NAME = 'poc_nextcloud_user_nc_user';

  /**
   * Static factory.
   *
   * @param \Drupal\poc_nextcloud\Tracking\TrackingTableFactory $trackingTableFactory
   *   Tracking table factory.
   */
  public function __construct(
    TrackingTableFactory $trackingTableFactory,
  ) {
    parent::__construct(
      NcUserSubmit::class,
      $trackingTableFactory->create(self::TABLE_NAME)
        ->addLocalPrimaryField('uid', [
          'description' => 'Drupal user id',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ])
        ->addRemotePrimaryField('nc_user_id', [
          'description' => 'Nextcloud user id',
          'type' => 'varchar_ascii',
          // User id length as in Nextcloud database.
          'length' => 64,
          'not null' => TRUE,
        ])
        ->addDataField('nc_email', [
          'type' => 'varchar',
          // Email length as in Drupal.
          // In Nextcloud this is stored in a serialized blob with other values.
          'length' => 254,
          'not null' => TRUE,
        ])
        ->addDataField('nc_display_name', [
          'type' => 'varchar',
          // Display name length as in Nextcloud database.
          'length' => 64,
          'not null' => TRUE,
        ]),
    );
  }

  /**
   * Finds a tracking record for a given Drupal user.
   *
   * @param \Drupal\user\UserInterface $user
   *   Drupal user.
   *
   * @return array|null
   *   Tracking record, or NULL if none found.
   *   This only returns records users that actually exist in Nextcloudm, even
   *   those marked for deletion.
   */
  public function findCurrentUserRecord(UserInterface $user): ?array {
    $q = $this->selectCurrent();
    $q->condition('uid', $user->id());
    return $q->execute()->fetchAssoc() ?: NULL;
  }

  /**
   * Implements hook_user_delete().
   *
   * @param \Drupal\user\UserInterface $user
   *   User.
   */
  #[Hook('user_delete')]
  public function userDelete(UserInterface $user): void {
    // Mark for deletion.
    // When this is actually executed, it could happen that the user is just
    // "forgotten" instead of being deleted.
    $this->trackingTable->queueDelete([
      'uid' => $user->id(),
    ]);
  }

  /**
   * Implements hook_user_insert() and hook_user_update().
   *
   * @param \Drupal\user\UserInterface $user
   *   User object.
   */
  #[Hook('user_insert'), Hook('user_update')]
  public function userWrite(UserInterface $user): void {
    // @todo More sophisticated mapping.
    $nextcloud_user_id = $user->getAccountName();
    $email = $user->getEmail();
    if (!$this->shouldHaveNextcloudAccount($user)) {
      // @todo Option to disable automatic deletion.
      $this->trackingTable->queueDelete([
        'uid' => $user->id(),
      ]);
    }
    elseif ($nextcloud_user_id && $email) {
      $this->trackingTable->queueWrite([
        'uid' => $user->id(),
        'nc_user_id' => $nextcloud_user_id,
        'nc_email' => $email,
        // @todo Flexible way to get a display name.
        'nc_display_name' => $user->getDisplayName(),
      ]);
    }
  }

  /**
   * Determines if the user should have a Nextcloud account.
   *
   * @param \Drupal\user\UserInterface $user
   *   Drupal user.
   *
   * @return bool
   *   TRUE if this user should have a Nextcloud account, FALSE if not.
   */
  private function shouldHaveNextcloudAccount(UserInterface $user): bool {
    if (!$user->hasPermission('have nextcloud account')) {
      return FALSE;
    }
    if ($user->isBlocked()) {
      return FALSE;
    }
    if (!$user->getEmail() || !$user->getAccountName()) {
      return FALSE;
    }
    return TRUE;
  }

}
