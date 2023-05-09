<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\poc_nextcloud\Service\NextcloudUrlBuilder;
use Drupal\poc_nextcloud\Tracking\Tracker\UserNcUserTracker;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Block that links to the current user's documents in Nextcloud.
 *
 * @Block(
 *   id = "poc_nextcloud_files_link",
 *   admin_label = @Translation("Link to Nextcloud documents"),
 *   category = @Translation("Nextcloud"),
 * )
 */
class NextcloudFilesLinkBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructor.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin id.
   * @param array $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Current user.
   * @param \Drupal\user\UserStorageInterface $userStorage
   *   User storage.
   * @param \Drupal\poc_nextcloud\Tracking\Tracker\UserNcUserTracker $userNcUserTracker
   *   Nextcloud user tracker.
   * @param \Drupal\poc_nextcloud\Service\NextcloudUrlBuilder $nextcloudUrlBuilder
   *   Nextcloud url builder.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    private AccountInterface $currentUser,
    private UserStorageInterface $userStorage,
    private UserNcUserTracker $userNcUserTracker,
    private NextcloudUrlBuilder $nextcloudUrlBuilder,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): BlockPluginInterface {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get(UserNcUserTracker::class),
      $container->get(NextcloudUrlBuilder::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    // @todo Use ->blockAccess() method, and set proper cache info.
    $uid = $this->currentUser->id();
    if (!$uid) {
      // Don't show for anonymous user.
      return [];
    }
    $drupal_user = $this->userStorage->load($uid);
    if (!$drupal_user instanceof UserInterface) {
      return [];
    }
    $user_record = $this->userNcUserTracker->findCurrentUserRecord($drupal_user);
    if ($user_record === NULL) {
      // The user has no account in Nextcloud.
      return [];
    }
    $url = $this->nextcloudUrlBuilder->url('apps/files');
    return [
      // @todo Set proper cache info.
      '#cache' => ['max-age' => 0],
      '#type' => 'link',
      '#title' => $this->t('My documents in Nextcloud'),
      '#url' => $url,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    // Suppress the cache, for now.
    // @todo Implement proper cache handling.
    return 0;
  }

}
