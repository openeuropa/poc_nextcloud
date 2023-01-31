<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_workspace\EntityHook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\group\Entity\GroupInterface;
use Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint;
use Drupal\poc_nextcloud\Endpoint\NxWorkspaceEndpoint;
use Drupal\poc_nextcloud\Exception\NextcloudApiException;
use Drupal\poc_nextcloud\NxEntity\NxWorkspace;
use Drupal\poc_nextcloud\Service\GroupFolderHelper;
use Drupal\poc_nextcloud_group_workspace\Service\GroupWorkspaceFieldHelper;
use Drupal\poc_nextcloud_group_workspace\Service\WorkspaceMemberHelper;
use Psr\Log\LoggerInterface;

/**
 * Callback for group hooks.
 */
class GroupSync {

  use StringTranslationTrait;

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud_group_workspace\Service\GroupWorkspaceFieldHelper $groupWorkspaceFieldHelper
   *   Helper to interact with the field that references a workspace.
   * @param \Drupal\poc_nextcloud_group_workspace\Service\WorkspaceMemberHelper $workspaceMemberHelper
   *   Helper to update workspace members when responding to different events.
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint $groupFolderEndpoint
   *   Nextcloud API endpoint for user accounts.
   * @param \Drupal\poc_nextcloud\Service\GroupFolderHelper $groupFolderHelper
   *   Higher-level methods to manipulate group folders.
   * @param \Drupal\poc_nextcloud\Endpoint\NxWorkspaceEndpoint $workspaceEndpoint
   *   Workspace endpoint.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   */
  public function __construct(
    private GroupWorkspaceFieldHelper $groupWorkspaceFieldHelper,
    private WorkspaceMemberHelper $workspaceMemberHelper,
    private NxGroupFolderEndpoint $groupFolderEndpoint,
    private GroupFolderHelper $groupFolderHelper,
    private NxWorkspaceEndpoint $workspaceEndpoint,
    private LoggerInterface $logger,
  ) {}

  /**
   * Creates a Nextcloud workspace for a Drupal group, if applicable.
   *
   * This should be called from hook_group_insert() and hook_group_update().
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group that was created or updated.
   * @param string $op
   *   One of 'presave', 'insert', 'update', 'delete'.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function __invoke(GroupInterface $group, string $op): void {
    $workspace_id = $this->groupWorkspaceFieldHelper->groupGetWorkspaceId($group);
    $workspace = NULL;
    if ($workspace_id) {
      $workspace = $this->workspaceEndpoint->load($workspace_id);
    }
    switch ($op) {
      case 'presave':
        $should_have_nextcloud_workspace = $this->groupWorkspaceFieldHelper->groupShouldHaveWorkspace($group);
        if (!$should_have_nextcloud_workspace) {
          if ($workspace_id === NULL) {
            // The group already doesn't have a workspace id.
            break;
          }
          if ($workspace !== NULL) {
            $this->deleteNextcloudWorkspace($group, $workspace);
          }
          $this->groupWorkspaceFieldHelper->groupSetWorkspaceId($group, NULL);
          return;
        }
        else {
          if ($workspace !== NULL) {
            // The group already has an existing workspace.
            break;
          }
          // If the id is not empty, it will be replaced now.
          $workspace_id = $this->createNextcloudWorkspace($group);
          if ($workspace_id === NULL) {
            // Give up.
            return;
          }
          // Store the id.
          $this->groupWorkspaceFieldHelper->groupSetWorkspaceId($group, $workspace_id);
        }
        break;

      case 'delete':
        if ($workspace !== NULL) {
          $this->deleteNextcloudWorkspace($group, $workspace);
        }
        break;

      case 'update':
      case 'insert':
        if ($workspace) {
          $this->updateNextcloudWorkspaceDetails($group, $workspace);
          $this->workspaceMemberHelper->updateNextcloudMembershipsForGroup($group, $workspace_id);
        }
        break;

      default:
        throw new \RuntimeException("Unexpected operation '$op'.");
    }
  }

  /**
   * Creates a workspace for a group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group.
   *
   * @return int|null
   *   The new workspace id, or NULL on failure.
   */
  private function createNextcloudWorkspace(GroupInterface $group): ?int {
    $label = (string) $group->label();
    // @todo Does the name need to be sanitized? Should we add the id?
    $mountpoint = $label;
    try {
      $group_folder_id = $this->groupFolderEndpoint
        ->insertWithMountPoint($mountpoint);
    }
    catch (NextcloudApiException $e) {
      $this->logger->warning("Failed to create Nextcloud group folder with mountpoint @mountpoint for Drupal group @group_id. Message: @message", [
        '@mountpoint' => $mountpoint,
        '@group_id' => $group->id(),
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
    try {
      $workspace_id = $this->workspaceEndpoint
        ->insertWorkspace($label, $group_folder_id)
        ->getId();
    }
    catch (NextcloudApiException $e) {
      $this->logger->error("Failed to create Nextcloud workspace named @workspace_name for group folder @group_folder_id for Drupal group @group_id. Message: @message", [
        '@workspace_name' => $label,
        '@group_folder_id' => $group_folder_id,
        '@group_id' => $group->id(),
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
    try {
      $this->workspaceEndpoint->load($workspace_id);
      return $workspace_id;
    }
    catch (NextcloudApiException) {
      $this->logger->warning("A workspace with id @workspace_id was just created for group '@group_name' / @group_id, but it cannot be loaded.", [
        '@group_name' => $group->label(),
        '@group_id' => $group->id(),
        '@workspace_id' => $workspace_id,
      ]);
      // Give up.
      return NULL;
    }
  }

  /**
   * Updates details of a workspace and its group folder.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Drupal group.
   * @param \Drupal\poc_nextcloud\NxEntity\NxWorkspace $workspace
   *   Nextcloud workspace.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Something went wrong.
   */
  private function updateNextcloudWorkspaceDetails(GroupInterface $group, NxWorkspace $workspace): void {
    // @todo Sanitize the name.
    $label = (string) $group->label();
    $workspace_id = $workspace->getId();
    $this->workspaceEndpoint->rename($workspace->getId(), $label);
    $group_folder_id = $workspace->getGroupFolderId();
    $group_folder = $this->groupFolderEndpoint->load($group_folder_id);
    if (!$group_folder) {
      return;
    }
    $this->groupFolderEndpoint->setMountPoint($group_folder->getId(), $label);
    $this->groupFolderHelper->setGroups($group_folder_id, [
      'SPACE-U-' . $workspace_id,
      'SPACE-GE-' . $workspace_id,
    ], '@^SPACE-(?:U|GE)-' . $workspace_id . '$@');
    $this->groupFolderEndpoint->setAcl($group_folder_id, TRUE);
    $this->groupFolderHelper->setManageAclGroup($group_folder_id, 'SPACE-GE-' . $workspace_id);
  }

  /**
   * Deletes a Nextcloud workspace, and logs the event.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Drupal group.
   * @param \Drupal\poc_nextcloud\NxEntity\NxWorkspace $workspace
   *   Nextcloud workspace.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function deleteNextcloudWorkspace(GroupInterface $group, NxWorkspace $workspace): void {
    // Delete the group folder. This will also delete the workspace.
    // (The delete API for workspace is not reliable atm.)
    $this->groupFolderEndpoint->delete($workspace->getGroupFolderId());

    $this->logger->info("The group folder @group_folder_id and the workspace @workspace_id for group @group_name / @group_id were deleted.", [
      '@group_folder_id' => $workspace->getGroupFolderId(),
      '@workspace_id' => $workspace->getId(),
      '@group_name' => $group->label(),
      '@group_id' => $group->id(),
    ]);
  }

}
