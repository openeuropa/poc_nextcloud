<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Plugin\Field\FieldFormatter;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Error;
use Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud\Endpoint\NxWorkspaceEndpoint;
use Drupal\poc_nextcloud\Exception\NextcloudApiException;
use Drupal\poc_nextcloud\Service\NextcloudUrlBuilder;
use Drupal\poc_nextcloud_group_folder\Plugin\Field\FieldType\NextcloudGroupFolderItem;
use Drupal\poc_nextcloud_group_workspace\Plugin\Field\FieldType\NextcloudWorkspaceItem;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field formatter for Nextcloud workspace or group folder reference.
 *
 * Note that the field types are only available by enabling one of the
 * submodules.
 *
 * @FieldFormatter(
 *   id = "poc_nextcloud_group_folder_link",
 *   label = @Translation("Link to group folder in Nextcloudd"),
 *   field_types = {
 *     "poc_nextcloud_workspace",
 *     "poc_nextcloud_group_folder",
 *   }
 * )
 */
class NextcloudGroupFolderLinkFormatter extends FormatterBase {

  use StringTranslationTrait;

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
   * @param \Drupal\poc_nextcloud\Endpoint\NxWorkspaceEndpoint $workspaceEndpoint
   *   Endpoint for Nextcloud workspaces.
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint $groupFolderEndpoint
   *   Endpoint for Nextcloud group folders.
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $userEndpoint
   *   User endpoint.
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
    private NextcloudUrlBuilder $nextcloudLinkBuilder,
    private NxWorkspaceEndpoint $workspaceEndpoint,
    private NxGroupFolderEndpoint $groupFolderEndpoint,
    private NxUserEndpoint $userEndpoint,
    private LoggerInterface $logger,
    private AccountInterface $currentUser,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): self {
    return new self(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get(NextcloudUrlBuilder::class),
      $container->get(NxWorkspaceEndpoint::class),
      $container->get(NxGroupFolderEndpoint::class),
      $container->get(NxUserEndpoint::class),
      $container->get('logger.channel.poc_nextcloud'),
      $container->get('current_user'),
    );
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
  public function viewElements(FieldItemListInterface $items, $langcode) {
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
   * @param \Drupal\poc_nextcloud_group_workspace\Plugin\Field\FieldType\NextcloudWorkspaceItem|\Drupal\poc_nextcloud_group_folder\Plugin\Field\FieldType\NextcloudGroupFolderItem $item
   *   Field item.
   *
   * @return array
   *   Render element.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Something went wrong in one of the API calls.
   */
  protected function viewElement(NextcloudWorkspaceItem|NextcloudGroupFolderItem $item): array {
    $username = $this->currentUser->getAccountName();
    $nextcloud_user = $this->userEndpoint->load($username);
    if (!$nextcloud_user) {
      return [];
    }
    // @todo Check if user has permission to view the group folder.
    if ($item instanceof NextcloudGroupFolderItem) {
      $workspace_id = NULL;
      $group_folder_id = (int) $item->value;
    }
    else {
      $workspace_id = (int) $item->value;
      if (!$workspace_id) {
        return [];
      }
      try {
        $workspace = $this->workspaceEndpoint->load($workspace_id);
        $group_folder_id = $workspace->getGroupFolderId();
      }
      catch (NextcloudApiException) {
        // The workspace does not exist, or something else went wrong.
        // Error reporting from this API is not very useful, so no need to log
        // the exception message.
        // For a proof of concept print the workspace id.
        return ['#markup' => '#' . $workspace_id];
      }
    }
    if (!$group_folder_id) {
      return [];
    }
    try {
      $groupfolder = $this->groupFolderEndpoint->load($group_folder_id);
    }
    catch (NextcloudApiException $e) {
      $this->logger->warning('Error when loading group folder @group_folder_id for group @group_id:\n@exception', [
        '@group_folder_id' => $group_folder_id,
        '@group_id' => $item->getEntity()->id(),
        /* @see watchdog_exception() */
        '@exception' => new FormattableMarkup(
          Error::DEFAULT_ERROR_MESSAGE,
          Error::decodeException($e),
        ),
      ]);
      $groupfolder = NULL;
    }
    if ($groupfolder === NULL) {
      if ($workspace_id !== NULL) {
        return [
          '#markup' => sprintf('#%d / #%d', $workspace_id, $group_folder_id),
        ];
      }
      else {
        return [
          '#markup' => '#' . $group_folder_id,
        ];
      }
    }
    $url = $this->nextcloudLinkBuilder->url('apps/files', [
      'dir' => $groupfolder->getMountPoint(),
    ]);
    return [
      '#type' => 'link',
      '#url' => $url,
      '#title' => new FormattableMarkup('#@id: @name', [
        '@id' => $group_folder_id,
        '@name' => $groupfolder->getMountPoint(),
      ]),
    ];
  }

}
