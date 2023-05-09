<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\Plugin\ExtraField\Display;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;
use Drupal\extra_field\Plugin\ExtraFieldDisplayInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\poc_nextcloud\Service\NextcloudUrlBuilder;
use Drupal\poc_nextcloud_group_folder\Tracker\GroupNcGroupFolderTracker;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extra field to link to Nextcloud account.
 *
 * @ExtraFieldDisplay(
 *   id = "poc_nextcloud_group_folder_link",
 *   label = @Translation("Link to Nextcloud group folder"),
 *   description = @Translation("Links to the Nextcloud group folder for the group being viewed."),
 *   bundles = {
 *     "group.*",
 *   }
 * )
 */
class NextcloudGroupFolderLinkExtraField extends ExtraFieldDisplayFormattedBase implements ContainerFactoryPluginInterface {

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
   * @param \Drupal\poc_nextcloud_group_folder\Tracker\GroupNcGroupFolderTracker $groupFolderTracker
   *   Group folder tracker.
   * @param \Drupal\poc_nextcloud\Service\NextcloudUrlBuilder $nextcloudUrlBuilder
   *   Service to build links to Nextcloud.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    private GroupNcGroupFolderTracker $groupFolderTracker,
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
      $container->get(GroupNcGroupFolderTracker::class),
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
    if (!$entity instanceof GroupInterface) {
      return [];
    }
    $group_folder_record = $this->groupFolderTracker->selectCurrent()
      ->condition('t.gid', $entity->id())
      ->execute()->fetchAssoc() ?: NULL;
    if (!$group_folder_record) {
      return [];
    }
    // @todo Get the user and membership records, and check access.
    $url = $this->nextcloudUrlBuilder->url('apps/files', [
      'dir' => $group_folder_record['nc_mount_point'],
    ]);
    return [
      // @todo Set proper cache info.
      '#cache' => ['max-age' => 0],
      '#type' => 'link',
      '#url' => $url,
      '#title' => $this->t('Group documents (Nextcloud)'),
    ];
  }

}
