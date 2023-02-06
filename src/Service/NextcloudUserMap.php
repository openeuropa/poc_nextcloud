<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Service;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud\NxEntity\NxUser;
use Drupal\user\UserInterface;

/**
 * Service to get Nextcloud user for Drupal user.
 */
class NextcloudUserMap {

  use StringTranslationTrait;

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
   * Gets a Nextcloud user id for a Drupal user.
   *
   * @param \Drupal\user\UserInterface $drupal_user
   *   Drupal user.
   *
   * @return string
   *   Nextcloud user id.
   */
  public function getNextcloudUserId(UserInterface $drupal_user): string {
    // @todo More sophisticated mapping.
    return $drupal_user->getAccountName();
  }

  /**
   * Gets a Nextcloud user for a Drupal user.
   *
   * @param \Drupal\user\UserInterface $drupal_user
   *   Drupal user.
   *
   * @return \Drupal\poc_nextcloud\NxEntity\NxUser|null
   *   Nextcloud user, or NULL if not found.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function getNextcloudUser(UserInterface $drupal_user): ?NxUser {
    $nextcloud_user_id = $this->getNextcloudUserId($drupal_user);
    return $this->userEndpoint->load($nextcloud_user_id);
  }

}
