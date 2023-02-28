<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Plugin\ExtraField\Display;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;
use Drupal\extra_field\Plugin\ExtraFieldDisplayInterface;
use Drupal\poc_nextcloud\Exception\ServiceNotAvailableException;
use Drupal\poc_nextcloud\Service\NextcloudUrlBuilder;
use Drupal\poc_nextcloud\Service\NextcloudUserMap;
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
   * @param \Drupal\poc_nextcloud\Service\NextcloudUserMap $nextcloudUserMap
   *   Service to get Nextcloud user for Drupal user.
   * @param \Drupal\poc_nextcloud\Service\NextcloudUrlBuilder $nextcloudUrlBuilder
   *   Service to build links to Nextcloud.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    private NextcloudUserMap $nextcloudUserMap,
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
    try {
      return new self(
        $configuration,
        $plugin_id,
        $plugin_definition,
        $container->get(NextcloudUserMap::class),
        $container->get(NextcloudUrlBuilder::class),
      );
    }
    catch (ServiceNotAvailableException) {
      return new EmptyExtraField(
        $configuration,
        $plugin_id,
        $plugin_definition,
      );
    }
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
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *
   * @todo Catch exceptions here.
   */
  public function viewElements(ContentEntityInterface $entity): array {
    // @todo Check permission of current user.
    if (!$entity instanceof UserInterface) {
      return [];
    }
    $nextcloud_user = $this->nextcloudUserMap->getNextcloudUser($entity);
    if ($nextcloud_user === NULL) {
      return [];
    }
    $url = $this->nextcloudUrlBuilder->url('u/' . urlencode($nextcloud_user->getId()));
    return [
      // @todo Set proper cache info.
      '#cache' => ['max-age' => 0],
      '#type' => 'link',
      '#url' => $url,
      '#title' => $nextcloud_user->getId(),
    ];
  }

}
