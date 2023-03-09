<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\EntityObserver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint;
use Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud\Endpoint\NxWebdavEndpoint;
use Drupal\poc_nextcloud\EntityObserver\EntityObserverInterface;
use Drupal\poc_nextcloud\Exception\NextcloudApiException;
use Drupal\poc_nextcloud_group_folder\Service\GroupToGroupFolderMap;

/**
 * Writes a README.md for the group folder.
 *
 * The README.md contains a link back to the Drupal group.
 */
class NextcloudGroupFolderReadme implements EntityObserverInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud_group_folder\Service\GroupToGroupFolderMap $groupToGroupFolderMap
   *   Service to map Drupal groups to Nextcloud group folders.
   * @param \Drupal\poc_nextcloud\Connection\ApiConnectionInterface $connection
   *   Connection.
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint $groupFolderEndpoint
   *   Group folder endpoint.
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint $groupEndpoint
   *   Group endpoint.
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $userEndpoint
   *   User endpoint.
   * @param \Drupal\poc_nextcloud\Endpoint\NxWebdavEndpoint $webdavEndpoint
   *   WebDAV endpoint.
   */
  public function __construct(
    private GroupToGroupFolderMap $groupToGroupFolderMap,
    private ApiConnectionInterface $connection,
    private NxGroupFolderEndpoint $groupFolderEndpoint,
    private NxGroupEndpoint $groupEndpoint,
    private NxUserEndpoint $userEndpoint,
    private NxWebdavEndpoint $webdavEndpoint,
  ) {}

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function entityOp(EntityInterface $entity, string $op): void {
    if ($entity instanceof GroupInterface) {
      $this->groupOp($entity, $op);
    }
  }

  /**
   * Responds to a group entity hook.
   *
   * @param \Drupal\group\Entity\GroupInterface $drupal_group
   *   Drupal group.
   * @param string $op
   *   Verb from the entity hook.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function groupOp(GroupInterface $drupal_group, string $op): void {
    if (!in_array($op, ['update', 'insert'])) {
      return;
    }
    $group_folder_id = $this->groupToGroupFolderMap->groupGetGroupFolderId($drupal_group);
    if (!$group_folder_id) {
      return;
    }
    // @todo Cache this, so that the group folder only has to be loaded once for
    //   all observers.
    $group_folder = $this->groupFolderEndpoint->load($group_folder_id);
    if (!$group_folder) {
      return;
    }
    $cancel_tmp_access = $this->giveTemporaryAccess($group_folder_id);
    $readme_content = sprintf(
      'Drupal group: [%s](%s)',
      // @todo Sanitize the text and url for Markdown.
      $drupal_group->label(),
      $drupal_group->toUrl()->setAbsolute()->toString());
    $this->webdavEndpoint->writeFile($group_folder->getMountPoint() . '/README.md', $readme_content);
    $cancel_tmp_access();
  }

  /**
   * Temporarily gives access to a Nextcloud group folder.
   *
   * Doing this minimizes the risk that Nextcloud chooses a different mount
   * point for the group folder due to name clashes.
   *
   * @param int $group_folder_id
   *   Group folder id.
   *
   * @return callable
   *   Callback to cancel the access and clean up.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function giveTemporaryAccess(int $group_folder_id): callable {
    $tmp_group_id = 'tmp_group_folder_manager';
    $nextcloud_user_id = $this->connection->getUserId();
    try {
      $this->groupEndpoint->insert($tmp_group_id);
    }
    catch (NextcloudApiException) {
      // Likely the group already exists.
    }
    $this->groupFolderEndpoint->addGroup($group_folder_id, $tmp_group_id);
    $this->userEndpoint->joinGroup($nextcloud_user_id, $tmp_group_id);
    return function () use ($tmp_group_id) {
      $this->groupEndpoint->delete($tmp_group_id);
    };
  }

}
