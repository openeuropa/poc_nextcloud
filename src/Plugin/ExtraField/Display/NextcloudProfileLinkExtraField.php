<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Plugin\ExtraField\Display;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;
use Drupal\extra_field\Plugin\ExtraFieldDisplayInterface;
use Drupal\poc_nextcloud\Service\NextcloudUrlBuilder;
use Drupal\poc_nextcloud\Tracking\Tracker\UserNcUserTracker;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extra field to link to Nextcloud account.
 *
 * @ExtraFieldDisplay(
 *   id = "poc_nextcloud_account_link",
 *   label = @Translation("Link to Nextcloud account"),
 *   description = @Translation("Links to the Nextcloud account of the user being viewed."),
 *   bundles = {
 *     "user.*",
 *   }
 * )
 */
class NextcloudProfileLinkExtraField extends ExtraFieldDisplayFormattedBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin id.
   * @param array $plugin_definition
   *   Plugin definition.
   * @param \Drupal\poc_nextcloud\Tracking\Tracker\UserNcUserTracker $userTracker
   *   Nextcloud user tracker.
   * @param \Drupal\poc_nextcloud\Service\NextcloudUrlBuilder $nextcloudUrlBuilder
   *   Service to build links to Nextcloud.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    private UserNcUserTracker $userTracker,
    private NextcloudUrlBuilder $nextcloudUrlBuilder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ): ExtraFieldDisplayInterface {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(UserNcUserTracker::class),
      $container->get(NextcloudUrlBuilder::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): MarkupInterface {
    return $this->t('Nextcloud profile link');
  }

  /**
   * {@inheritdoc}
   */
  public function getLabelDisplay(): string {
    return 'above';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity): array {
    // @todo Check permission of current user.
    if (!$entity instanceof UserInterface) {
      return [];
    }
    $user_record = $this->userTracker->findCurrentUserRecord($entity);
    if ($user_record === NULL) {
      return [];
    }
    $url = $this->nextcloudUrlBuilder->url('u/' . urlencode($user_record['nc_user_id']));
    return [
      // @todo Set proper cache info.
      '#cache' => ['max-age' => 0],
      '#type' => 'link',
      '#url' => $url,
      '#title' => $user_record['nc_user_id'],
    ];
  }

}
