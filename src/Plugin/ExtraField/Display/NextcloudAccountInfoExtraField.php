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
use Drupal\poc_nextcloud_group_folder\Tracker\GroupMembershipRoleNcUserGroupTracker;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Extra field to show Nextcloud account info.
 *
 * This is meant for demo purposes only.
 *
 * @ExtraFieldDisplay(
 *   id = "poc_nextcloud_account_info",
 *   label = @Translation("Nextcloud account info"),
 *   description = @Translation("Shows information from the Nextcloud account of the user being viewed."),
 *   bundles = {
 *     "user.*",
 *   }
 * )
 */
class NextcloudAccountInfoExtraField extends ExtraFieldDisplayFormattedBase implements ContainerFactoryPluginInterface {

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
   * @param \Drupal\poc_nextcloud\Service\NextcloudUrlBuilder $nextcloudUrlBuilder
   *   Service to build links to Nextcloud.
   * @param \Drupal\poc_nextcloud\Tracking\Tracker\UserNcUserTracker $userTracker
   *   Nextcloud user tracker.
   * @param \Drupal\poc_nextcloud_group_folder\Tracker\GroupMembershipRoleNcUserGroupTracker $userGroupTracker
   *   Nextcloud group membership tracker.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    private NextcloudUrlBuilder $nextcloudUrlBuilder,
    private UserNcUserTracker $userTracker,
    private GroupMembershipRoleNcUserGroupTracker $userGroupTracker,
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
    $plugin_definition,
  ): ExtraFieldDisplayInterface {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(NextcloudUrlBuilder::class),
      $container->get(UserNcUserTracker::class),
      $container->get(GroupMembershipRoleNcUserGroupTracker::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): MarkupInterface {
    return $this->t('Nextcloud account info');
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
    $q = $this->userGroupTracker->selectCurrent();
    $q->condition('t.uid', $entity->id());
    $q->addField('g', 'nc_display_name');
    $group_labels = $q->execute()->fetchAllKeyed('nc_group_id', 'nc_display_name');
    return [
      '#cache' => ['max-age' => 0],
      '#type' => 'html_tag',
      '#tag' => 'pre',
      '#value' => Yaml::dump([
        'id' => $user_record['nc_user_id'],
        'display_name' => $user_record['nc_display_name'],
        'groups' => $group_labels,
      ], 9, 2),
    ];
  }

}
