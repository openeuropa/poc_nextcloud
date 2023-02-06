<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\EntityHook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\group\Entity\GroupInterface;
use Drupal\poc_nextcloud\DataUtil;
use Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint;
use Drupal\poc_nextcloud\Exception\NextcloudApiException;
use Drupal\poc_nextcloud\NextcloudConstants;
use Drupal\poc_nextcloud\NxEntity\NxGroupFolder;
use Drupal\poc_nextcloud\Service\GroupFolderHelper;
use Drupal\poc_nextcloud\Service\NextcloudGroupHelper;
use Drupal\poc_nextcloud_group_folder\GroupFolderConstants;
use Drupal\poc_nextcloud_group_folder\Service\GroupFolderFieldHelper;
use Drupal\poc_nextcloud_group_folder\Service\GroupFolderMemberHelper;
use Psr\Log\LoggerInterface;

/**
 * Callable service to create Nextcloud group folders for Drupal groups.
 */
class GroupSync {

  use StringTranslationTrait;

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud_group_folder\Service\GroupFolderFieldHelper $groupFolderFieldHelper
   *   Helper to interact with the group folder field.
   * @param \Drupal\poc_nextcloud_group_folder\Service\GroupFolderMemberHelper $groupFolderMemberHelper
   *   Helper to update group folder members in response to different events.
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint $groupFolderEndpoint
   *   Nextcloud API endpoint for user accounts.
   * @param \Drupal\poc_nextcloud\Service\NextcloudGroupHelper $groupHelper
   *   Helper methods to add or remove Nextcloud users from groups.
   * @param \Drupal\poc_nextcloud\Service\GroupFolderHelper $groupFolderHelper
   *   Higher-level methods to manipulate group folders.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   */
  public function __construct(
    private GroupFolderFieldHelper $groupFolderFieldHelper,
    private GroupFolderMemberHelper $groupFolderMemberHelper,
    private NxGroupFolderEndpoint $groupFolderEndpoint,
    private NextcloudGroupHelper $groupHelper,
    private GroupFolderHelper $groupFolderHelper,
    private LoggerInterface $logger,
  ) {}

  /**
   * Creates a Nextcloud group folder for a Drupal group, if applicable.
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
    $group_folder_id = $this->groupFolderFieldHelper->groupGetGroupFolderId($group);
    $group_folder = NULL;
    if ($group_folder_id) {
      $group_folder = $this->groupFolderEndpoint->load($group_folder_id);
      if ($group_folder === NULL) {
        $this->logger->warning("Group @group_name / @group_id references a non-existing group folder @group_folder_id. Detected during @operation.", [
          '@group_name' => $group->label(),
          '@group_id' => $group->id(),
          '@group_folder_id' => $group_folder_id,
          '@operation' => $op,
        ]);
      }
    }
    switch ($op) {
      case 'presave':
        $should_have_nextcloud_group_folder = $this->groupFolderFieldHelper->groupShouldHaveGroupFolder($group);
        if (!$should_have_nextcloud_group_folder) {
          if ($group_folder_id === NULL) {
            // The group already doesn't have a group folder id.
            break;
          }
          if ($group_folder !== NULL) {
            $this->deleteNextcloudGroupFolder($group, $group_folder);
          }
          $this->groupFolderFieldHelper->groupSetGroupFolderId($group, NULL);
          return;
        }
        else {
          if ($group_folder !== NULL) {
            // The group already has an existing group folder.
            break;
          }
          // If the id is not empty, it will be replaced now.
          $group_folder_id = $this->createNextcloudGroupFolder($group);
          if ($group_folder_id === NULL) {
            // Give up.
            return;
          }
          // Store the id.
          $this->groupFolderFieldHelper->groupSetGroupFolderId($group, $group_folder_id);
        }
        break;

      case 'delete':
        if ($group_folder !== NULL) {
          $this->deleteNextcloudGroupFolder($group, $group_folder);
        }
        break;

      case 'update':
      case 'insert':
        if ($group_folder !== NULL) {
          $this->updateNextcloudGroupFolderDetails($group, $group_folder);
          $this->groupFolderMemberHelper->updateNextcloudMembershipsForGroup($group, $group_folder);
        }
        break;

      default:
        throw new \RuntimeException("Unexpected operation '$op'.");
    }
  }

  /**
   * Creates a group folder for a group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group.
   *
   * @return int|null
   *   The new group folder id, or NULL on failure.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function createNextcloudGroupFolder(GroupInterface $group): ?int {
    $label = (string) $group->label();
    // @todo Does the name need to be sanitized? Should we add the id?
    $mountpoint = $label;
    $group_folder_id = $this->groupFolderEndpoint
      ->insertWithMountPoint($mountpoint);
    $group_folder = $this->groupFolderEndpoint->load($group_folder_id);
    if (!$group_folder) {
      throw new NextcloudApiException(sprintf(
        "A group folder with id %s was just created for group '%s' / %d, but it cannot be loaded.",
        $group_folder_id,
        $group->label(),
        $group->id(),
      ));
    }
    return $group_folder_id;
  }

  /**
   * Updates details of a group folder.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Drupal group.
   * @param \Drupal\poc_nextcloud\NxEntity\NxGroupFolder $group_folder
   *   Nextcloud group folder.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function updateNextcloudGroupFolderDetails(GroupInterface $group, NxGroupFolder $group_folder): void {
    // @todo Sanitize the name.
    $label = (string) $group->label();
    $group_folder_id = $group_folder->getId();
    $this->groupFolderEndpoint->setMountPoint($group_folder->getId(), $label);
    $group_id_regex = sprintf('@^GROUPFOLDER-%s-\w+-\w+$@', $group_folder_id);
    $bitmasks_by_group_id = [];
    $group_display_names = [];
    $permissions_by_bitmask = array_flip(GroupFolderConstants::PERMISSIONS_MAP);
    foreach ($group->getGroupType()->getRoles() as $role) {
      $role_permissions_by_bitmask = array_intersect($permissions_by_bitmask, $role->getPermissions());
      if (!$role_permissions_by_bitmask) {
        continue;
      }
      $role_bitmask = DataUtil::bitwiseOr(...array_keys($role_permissions_by_bitmask));
      $group_id = 'GROUPFOLDER-' . $group_folder_id . '-' . $role->id();
      $bitmasks_by_group_id[$group_id] = $role_bitmask;
      // @todo Come up with a nicer naming pattern.
      $perm_string = implode('', array_intersect_key(
        GroupFolderConstants::PERMISSIONS_SHORTCODE_MAP,
        $role_permissions_by_bitmask,
      ));
      $group_display_names[$group_id] = 'D:G:' . $group->id() . ':' . $perm_string . ': ' . $label . ': ' . $role->label();
    }
    $this->groupHelper->setGroups(
      $group_display_names,
      $group_id_regex,
    );
    $this->groupFolderHelper->setGroups(
      $group_folder_id,
      array_keys($bitmasks_by_group_id),
      $group_id_regex,
    );
    $acl_group_ids = array_filter(
      $bitmasks_by_group_id,
      fn (int $bitmask) => $bitmask & NextcloudConstants::PERMISSION_ADVANCED,
    );
    $this->groupFolderEndpoint->setAcl($group_folder_id, $acl_group_ids !== []);
    $this->groupFolderHelper->setManageAclGroups($group_folder_id, $acl_group_ids, $group_id_regex);
  }

  /**
   * Deletes a Nextcloud group folder, and logs the event.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Drupal group.
   * @param \Drupal\poc_nextcloud\NxEntity\NxGroupFolder $group_folder
   *   Nextcloud group folder.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function deleteNextcloudGroupFolder(GroupInterface $group, NxGroupFolder $group_folder): void {
    $this->groupFolderEndpoint->delete($group_folder->getId());

    $this->logger->info("The group folder @group_folder_id for group @group_name / @group_id was deleted.", [
      '@group_folder_id' => $group_folder->getId(),
      '@group_name' => $group->label(),
      '@group_id' => $group->id(),
    ]);
  }

}
