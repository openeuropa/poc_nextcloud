<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\poc_nextcloud\NextcloudConstants;
use Drupal\poc_nextcloud\NxEntity\NxGroup;
use Drupal\poc_nextcloud\NxEntity\NxGroupFolder;
use Symfony\Component\Yaml\Yaml;

/**
 * Field formatter for Nextcloud workspace or group folder reference.
 *
 * Note that the field types are only available by enabling one of the
 * submodules.
 *
 * @FieldFormatter(
 *   id = "poc_nextcloud_group_folder_info",
 *   label = @Translation("Info for group folder in Nextcloud"),
 *   field_types = {
 *     "poc_nextcloud_group_folder",
 *   }
 * )
 */
class NextcloudGroupFolderInfoFormatter extends NextcloudGroupFolderLinkFormatter {

  /**
   * {@inheritdoc}
   */
  protected function viewGroupFolder(NxGroupFolder $groupfolder, EntityInterface $entity): array {
    $link_element = parent::viewGroupFolder($groupfolder, $entity);
    $groups = $this->groupEndpoint->loadGroups('DRUPAL-GROUP-' . $entity->id() . '-');
    $groups_info = array_combine(
      array_map(
        static fn (NxGroup $group) => $group->getId(),
        $groups,
      ),
      array_map(
        static fn (NxGroup $group) => $group->getDisplayName(),
        $groups,
      ),
    );
    $perms_labels = [
      NextcloudConstants::PERMISSION_READ => 'read',
      NextcloudConstants::PERMISSION_WRITE => 'write',
      NextcloudConstants::PERMISSION_SHARE => 'share',
      NextcloudConstants::PERMISSION_DELETE => 'delete',
      NextcloudConstants::PERMISSION_ADVANCED => 'manage',
    ];
    return [
      '#cache' => ['max-age' => 0],
      'link' => $link_element,
      'info' => [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#value' => Yaml::dump([
          'id' => $groupfolder->getId(),
          'mountpoint' => $groupfolder->getMountPoint(),
          'acl_groups' => $groupfolder->getAclManagerGroupIds(),
          'groups' => array_map(
            static function (int $group_perms) use ($perms_labels): array {
              return array_values(array_filter(
                $perms_labels,
                static fn (int $perm_bitmask) => $perm_bitmask & $group_perms,
                ARRAY_FILTER_USE_KEY,
              ));
            },
            $groupfolder->getGroupsPerms(),
          ),
          'other_groups' => $groups_info,
        ], 2, 2),
      ],
    ];
  }

}
