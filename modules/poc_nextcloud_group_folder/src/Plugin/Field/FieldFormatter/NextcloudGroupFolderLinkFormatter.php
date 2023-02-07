<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\Plugin\Field\FieldFormatter;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FormatterInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint;
use Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud\Exception\ServiceNotAvailableException;
use Drupal\poc_nextcloud\NxEntity\NxGroupFolder;
use Drupal\poc_nextcloud\Service\NextcloudUrlBuilder;
use Drupal\poc_nextcloud_group_folder\Plugin\Field\FieldType\NextcloudGroupFolderItem;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field formatter for a Nextcloud group folder reference.
 *
 * Note that the field types are only available by enabling one of the
 * submodules.
 *
 * @FieldFormatter(
 *   id = "poc_nextcloud_group_folder_link",
 *   label = @Translation("Link to group folder in Nextcloud"),
 *   field_types = {
 *     "poc_nextcloud_group_folder",
 *   }
 * )
 */
class NextcloudGroupFolderLinkFormatter extends FormatterBase {

  /**
   * Constructor.
   *
   * @param string $plugin_id
   *   Plugin id.
   * @param array $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Field definition.
   * @param array $settings
   *   Formatter settings.
   * @param string|\Drupal\Component\Render\MarkupInterface $label
   *   Field label.
   * @param string $view_mode
   *   View mode.
   * @param array $third_party_settings
   *   Third party settings.
   * @param \Drupal\poc_nextcloud\Service\NextcloudUrlBuilder $nextcloudLinkBuilder
   *   Service to build links to Nextcloud.
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint $groupFolderEndpoint
   *   Endpoint for Nextcloud group folders.
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $userEndpoint
   *   User endpoint.
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint $groupEndpoint
   *   Group endpoint.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Current user.
   *
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   */
  public function __construct(
    string $plugin_id,
    array $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    string|MarkupInterface $label,
    string $view_mode,
    array $third_party_settings,
    protected NextcloudUrlBuilder $nextcloudLinkBuilder,
    protected NxGroupFolderEndpoint $groupFolderEndpoint,
    protected NxUserEndpoint $userEndpoint,
    protected NxGroupEndpoint $groupEndpoint,
    protected LoggerInterface $logger,
    protected AccountInterface $currentUser,
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $label,
      $view_mode,
      $third_party_settings,
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
  ): FormatterInterface {
    try {
      return new static(
        $plugin_id,
        $plugin_definition,
        $configuration['field_definition'],
        $configuration['settings'],
        $configuration['label'],
        $configuration['view_mode'],
        $configuration['third_party_settings'],
        $container->get(NextcloudUrlBuilder::class),
        $container->get(NxGroupFolderEndpoint::class),
        $container->get(NxUserEndpoint::class),
        $container->get(NxGroupEndpoint::class),
        $container->get('logger.channel.poc_nextcloud'),
        $container->get('current_user'),
      );
    }
    catch (ServiceNotAvailableException) {
      return EmptyFieldFormatter::create(
        $container,
        $configuration,
        $plugin_id,
        $plugin_definition,
      );
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Something went wrong in one of the API calls.
   *
   * @todo In the future we should catch the exceptions. For now it is useful to
   *   have them visible.
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      $elements[$delta] = $this->viewElement($item);
    }
    // Clear out empty elements.
    return array_filter($elements);
  }

  /**
   * Builds the render element for a single field item.
   *
   * @param \Drupal\poc_nextcloud_group_folder\Plugin\Field\FieldType\NextcloudGroupFolderItem $item
   *   Field item.
   *
   * @return array
   *   Render element.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Something went wrong in one of the API calls.
   */
  protected function viewElement(NextcloudGroupFolderItem $item): array {
    $username = $this->currentUser->getAccountName();
    $nextcloud_user = $this->userEndpoint->load($username);
    if (!$nextcloud_user) {
      return [];
    }
    // @todo Check if user has permission to view the group folder.
    $group_folder_id = (int) $item->value;
    if (!$group_folder_id) {
      return [];
    }
    $groupfolder = $this->groupFolderEndpoint->load($group_folder_id);
    if ($groupfolder === NULL) {
      return $this->viewMissingGroupFolder($group_folder_id);
    }
    return $this->viewGroupFolder($groupfolder, $item->getEntity());
  }

  /**
   * Views a group folder id where no group folder was found.
   *
   * @param int $group_folder_id
   *   Group folder id.
   *
   * @return array
   *   Render element.
   *
   * @todo In final version this should be removed.
   */
  protected function viewMissingGroupFolder(int $group_folder_id): array {
    return [
      '#markup' => '#' . $group_folder_id,
    ];
  }

  /**
   * Views Nextcloud group folder.
   *
   * @param \Drupal\poc_nextcloud\NxEntity\NxGroupFolder $groupfolder
   *   Group folder.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Drupal entity being viewed.
   *
   * @return array
   *   Render element.
   */
  protected function viewGroupFolder(NxGroupFolder $groupfolder, EntityInterface $entity): array {
    $url = $this->nextcloudLinkBuilder->url('apps/files', [
      'dir' => $groupfolder->getMountPoint(),
    ]);
    return [
      '#type' => 'link',
      '#url' => $url,
      '#title' => new FormattableMarkup('#@id: @name', [
        '@id' => $groupfolder->getId(),
        '@name' => $groupfolder->getMountPoint(),
      ]),
    ];

  }

}
