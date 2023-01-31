<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\EntityHook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud\Exception\NextcloudApiException;
use Drupal\poc_nextcloud\NxEntity\NxUser;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Callback for user hooks.
 */
class UserSync {

  use StringTranslationTrait;

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $userEndpoint
   *   Nextcloud API endpoint for user accounts.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   */
  public function __construct(
    private NxUserEndpoint $userEndpoint,
    private LoggerInterface $logger,
  ) {}

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
   */
  public function __invoke(UserInterface $user, string $op): void {
    $should_have_nextcloud_account = ($op !== 'delete')
      && $this->shouldHaveNextcloudAccount($user);
    $name = $user->getAccountName();
    if (!is_string($name) || $name === '') {
      // Something is wrong with the name.
      $this->logger->warning('No Nextcloud account can be associated with user @uid, because the username is not valid.', ['@uid' => $user->id()]);
      return;
    }
    // Check if the user exists.
    $nextcloud_user = $this->userEndpoint->load($name);
    if (!$should_have_nextcloud_account) {
      if ($nextcloud_user !== NULL) {
        $this->userEndpoint->delete($name);
      }
      return;
    }
    if ($nextcloud_user === NULL) {
      $nextcloud_user = $this->createNextcloudAccountFor($user);
      if ($nextcloud_user === NULL) {
        // Give up.
        return;
      }
    }
    $this->updateNextcloudUserEmail($user, $nextcloud_user);
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
  private function updateNextcloudUserEmail(UserInterface $user, NxUser $nextcloud_user): void {
    $name = $nextcloud_user->getId();
    $email = $nextcloud_user->getEmail();

    if ($nextcloud_user->getEmail() === $email || !$email) {
      return;
    }

    // Email needs updating.
    $this->userEndpoint->setUserEmail($name, $user->getEmail());

    $this->logger->info("The Nextcloud email address for '@name' was updated from @old to @new.", [
      '@name' => $name,
      '@old' => $nextcloud_user->getEmail(),
      '@new' => $user->getEmail(),
    ]);
  }

  /**
   * Creates a Nextcloud account, and logs the event.
   *
   * @param \Drupal\user\UserInterface $user
   *   Drupal user.
   *
   * @return \Drupal\poc_nextcloud\NxEntity\NxUser|null
   *   Nextcloud user.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function createNextcloudAccountFor(UserInterface $user): NxUser|null {
    $name = $user->getAccountName();
    $email = $user->getEmail();
    if (!$email) {
      $this->logger->warning("User @uid / @name does not have an email address, so no Nextcloud account can be created.", [
        '@uid' => $user->id(),
        '@name' => $name,
      ]);
      // Give up.
      return NULL;
    }
    try {
      $this->userEndpoint->insertWithEmail($name, $user->getEmail());
    }
    catch (NextcloudApiException $e) {
      $this->logger->warning("Failed to create Nextcloud account for user @uid / @name: @message", [
        '@uid' => $user->id(),
        '@name' => $name,
        '@message' => $e->getMessage(),
      ]);
      // Give up.
      return NULL;
    }
    $this->logger->info("A nextcloud account named '@name' was created for user @uid", [
      '@name' => $name,
      '@uid' => $user->id(),
    ]);
    $nextcloud_user = $this->userEndpoint->load($name);
    if ($nextcloud_user === NULL) {
      $this->logger->warning("Nextcloud account for user @uid / @name was just created, but now it cannot be loaded.", [
        '@uid' => $user->id(),
        '@name' => $name,
      ]);
      // Give up.
      return NULL;
    }
    return $nextcloud_user;
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
    return TRUE;
  }

}
