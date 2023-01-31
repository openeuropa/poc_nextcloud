<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Plugin\ExtraField\Display;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;
use Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud\Service\NextcloudUrlBuilder;
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
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $userEndpoint
   *   User endpoint.
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint $groupEndpoint
   *   Group endpoint.
   * @param \Drupal\poc_nextcloud\Service\NextcloudUrlBuilder $nextcloudLinkBuilder
   *   Service to build links to Nextcloud.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    private NxUserEndpoint $userEndpoint,
    private NxGroupEndpoint $groupEndpoint,
    private NextcloudUrlBuilder $nextcloudLinkBuilder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(NxUserEndpoint::class),
      $container->get(NxGroupEndpoint::class),
      $container->get(NextcloudUrlBuilder::class),
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
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Something went wrong in one of the API calls.
   *
   * @todo In the future this plugin should catch the exceptions.
   *   For now it is useful to show them, if error reporting is enabled.
   */
  public function viewElements(ContentEntityInterface $entity): array {
    // @todo Check permission of current user.
    if (!$entity instanceof UserInterface) {
      return [];
    }
    $name = $entity->getAccountName();
    $nextcloud_user = $this->userEndpoint->load($name);
    if ($nextcloud_user === NULL) {
      return [];
    }
    $group_labels = [];
    foreach ($this->groupEndpoint->loadGroups() as $group) {
      if (in_array($group->getId(), $nextcloud_user->getGroupIds())) {
        $group_labels[$group->getId()] = $group->getDisplayName();
      }
    }
    return [
      '#type' => 'html_tag',
      '#tag' => 'pre',
      '#value' => Yaml::dump([
        'id' => $nextcloud_user->getId(),
        'display_name' => $nextcloud_user->getDisplayName(),
        'enabled' => $nextcloud_user->isEnabled(),
        'groups' => $group_labels,
      ], 9, 2),
    ];
  }

}
