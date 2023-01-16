<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud\Exception\NextcloudApiException;
use Drupal\poc_nextcloud\NxEntity\NxUser;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Service to create Nextcloud accounts for Drupal users.
 */
class UserSync {

  use StringTranslationTrait;

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $endpoint
   *   Nextcloud API endpoint for user accounts.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger.
   * @param \Drupal\user\UserInterface $currentUser
   *   Current user.
   * @param string|null $nextcloudUrl
   *   Nextcloud url.
   */
  public function __construct(
    private NxUserEndpoint $endpoint,
    private LoggerInterface $logger,
    private MessengerInterface $messenger,
    private UserInterface $currentUser,
    private ?string $nextcloudUrl,
  ) {}

  /**
   * Static factory.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $endpoint
   *   Nextcloud API endpoint for user accounts.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger channel factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger.
   * @param \Drupal\user\UserInterface $currentUser
   *   Current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   *
   * @return self
   *   New instance.
   */
  public static function create(
    NxUserEndpoint $endpoint,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    MessengerInterface $messenger,
    UserInterface $currentUser,
    ConfigFactoryInterface $configFactory,
  ): self {
    return new self(
      $endpoint,
      $loggerChannelFactory->get('poc_nextcloud'),
      $messenger,
      $currentUser,
      $configFactory->get('poc_nextcloud.settings')->get('nextcloud_url'),
    );
  }

  /**
   * Creates a Nextcloud account for a Drupal user, if applicable.
   *
   * This should be called from hook_user_insert() and hook_user_update().
   *
   * @param \Drupal\user\UserInterface $user
   *   The user that was created or updated.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function syncUser(UserInterface $user): void {
    if (!$user->hasPermission('have nextcloud account')) {
      return;
    }
    if ($this->currentUser->hasPermission('administer poc_nextcloud')
      || $user->id() === $this->currentUser->id()
    ) {
      $messenger = $this->messenger;
    }
    else {
      // Users don't need to know about Nextcloud accounts for others.
      $messenger = NULL;
    }
    $name = $user->getAccountName();
    if (!is_string($name) || $name === '') {
      // Something is wrong with the name.
      $messenger?->addWarning($this->t('No Nextcloud account will be created, due to problems with the username.'));
      $this->logger->warning('No Nextcloud account was created for user @uid, because the username is not valid.', ['@uid' => $user->id()]);
      return;
    }
    // Try multiple times, to handle rare case of race conditions.
    for ($i = 0; $i < 3; ++$i) {
      // Check if the user exists.
      /** @var \Drupal\poc_nextcloud\NxEntity\NxUser|null $nextcloud_user */
      $nextcloud_user = $this->endpoint->load($name);
      if ($nextcloud_user === NULL) {
        // A user must be created.
        $stub = NxUser::createStubWithEmail(
          $name,
          $user->getEmail(),
        );
        try {
          $stub->save($this->endpoint);
        }
        catch (NextcloudApiException $e) {
          $messenger?->addWarning(
            $this->t('We failed to create the Nextcloud account for @uid. Contact your administrator.', [
              '@uid' => $user->id(),
            ])
          );
          $this->logger->warning("Failed to create Nextcloud account for user @uid / @name: @message", [
            '@uid' => $user->id(),
            '@name' => $name,
            '@message' => $e->getMessage(),
          ]);
          // Give up.
          return;
        }
        $messenger?->addStatus(
          $this->t("A Nextcloud account was created for '@name'. Visit @link.", [
            '@name' => $name,
            '@link' => Link::fromTextAndUrl(
              $this->nextcloudUrl,
              Url::fromUri($this->nextcloudUrl),
            ),
          ])
        );
        $this->logger->info("A nextcloud account named '@name' was created for user @uid", [
          '@name' => $name,
          '@uid' => $user->id(),
        ]);
        // Repeat, verify that the user exists.
        continue;
      }
      if ($nextcloud_user->getEmail() !== $user->getEmail()) {
        $this->endpoint->update($name, [
          'email' => $user->getEmail(),
        ]);
        $messenger?->addStatus(
          $this->t("The Nextcloud email address for '@name' was updated.", [
            '@name' => $name,
          ])
        );
        $this->logger->info("The Nextcloud email address for '@name' was updated from @old to @new.", [
          '@name' => $name,
          '@old' => $nextcloud_user->getEmail(),
          '@new' => $user->getEmail(),
        ]);
      }
      // All good!
      return;
    }
  }

}
