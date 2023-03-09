<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\EntityObserver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud\Exception\NextcloudApiException;
use Drupal\poc_nextcloud\NxEntity\NxUser;
use Drupal\poc_nextcloud\Service\NextcloudUserMap;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Creates Nextcloud users for Drupal users.
 */
class NextcloudUsers implements EntityObserverInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $userEndpoint
   *   Nextcloud API endpoint for user accounts.
   * @param \Drupal\poc_nextcloud\Service\NextcloudUserMap $nextcloudUserMap
   *   Service to get Nextcloud user ids for Drupal users.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   */
  public function __construct(
    private NxUserEndpoint $userEndpoint,
    private NextcloudUserMap $nextcloudUserMap,
    private LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function entityOp(EntityInterface $entity, string $op): void {
    if ($entity instanceof UserInterface) {
      $this->userOp($entity, $op);
    }
  }

  /**
   * Creates a Nextcloud account for a Drupal user, if applicable.
   *
   * This should be called from hook_user_insert() and hook_user_update().
   *
   * @param \Drupal\user\UserInterface $user
   *   The user that was created or updated.
   * @param string $op
   *   Verb from the hook name, e.g. 'presave', 'update', 'insert', 'delete'.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function userOp(UserInterface $user, string $op): void {
    $nextcloud_user_id = $this->nextcloudUserMap->getNextcloudUserId($user);
    $email = $user->getEmail();
    // Check if the user exists in Nextcloud.
    $nextcloud_user = $this->userEndpoint->load($nextcloud_user_id);
    switch ($op) {
      case 'update':
      case 'insert':
        if (!$this->shouldHaveNextcloudAccount($user)) {
          if ($nextcloud_user !== NULL) {
            $this->userEndpoint->delete($nextcloud_user_id);
          }
        }
        elseif ($nextcloud_user_id && $email) {
          if ($nextcloud_user === NULL) {
            $this->createNextcloudAccountFor($user, $nextcloud_user_id);
          }
          else {
            $this->updateNextcloudAccount($user, $nextcloud_user);
          }
        }
        break;

      case 'delete':
        if ($nextcloud_user !== NULL) {
          $this->userEndpoint->delete($nextcloud_user_id);
        }
        break;
    }
  }

  /**
   * Sets the email for a Nextcloud account.
   *
   * @param \Drupal\user\UserInterface $user
   *   Drupal user.
   * @param \Drupal\poc_nextcloud\NxEntity\NxUser $nextcloud_user
   *   Nextcloud user.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Failed to set email.
   */
  private function updateNextcloudAccount(UserInterface $user, NxUser $nextcloud_user): void {
    $user_id = $nextcloud_user->getId();

    $email = $user->getEmail();
    if ($nextcloud_user->getEmail() !== $email && $email) {
      // Email needs updating.
      $this->userEndpoint->setUserEmail($user_id, $email);

      $this->logger->info("The Nextcloud email address for '@name' was updated from @old to @new.", [
        '@name' => $user_id,
        '@old' => $nextcloud_user->getEmail(),
        '@new' => $email,
      ]);
    }

    $display_name = $user->getDisplayName();
    if ($display_name && $user->getDisplayName() !== $display_name) {
      $this->userEndpoint->setUserEmail($user_id, $email);
      $this->userEndpoint->setUserDisplayName($user_id, $email);
    }
  }

  /**
   * Creates a Nextcloud account, and logs the event.
   *
   * @param \Drupal\user\UserInterface $user
   *   Drupal user.
   * @param string $nextcloud_user_id
   *   Nextcloud user id.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function createNextcloudAccountFor(UserInterface $user, string $nextcloud_user_id): void {
    $email = $user->getEmail();
    if (!$email) {
      $this->logger->warning("User @uid / @name does not have an email address, so no Nextcloud account can be created.", [
        '@uid' => $user->id(),
        '@name' => $user->getAccountName(),
      ]);
      // Give up.
      return;
    }
    $this->userEndpoint->insertWithEmail(
      $nextcloud_user_id,
      $user->getEmail(),
      $user->getDisplayName(),
    );
    $this->logger->info("A Nextcloud account named '@name' was created for user @uid", [
      '@name' => $nextcloud_user_id,
      '@uid' => $user->id(),
    ]);
    $nextcloud_user = $this->userEndpoint->load($nextcloud_user_id);
    if ($nextcloud_user === NULL) {
      throw new NextcloudApiException(sprintf(
        "Nextcloud account %s for user %d / %s was just created, but now it cannot be loaded.",
        $nextcloud_user_id,
        $user->id(),
        $user->getAccountName(),
      ));
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
