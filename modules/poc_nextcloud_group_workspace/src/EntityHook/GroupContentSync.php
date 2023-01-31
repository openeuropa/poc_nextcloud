<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_workspace\EntityHook;

use Drupal\group\Entity\GroupContentInterface;
use Drupal\group\GroupMembership;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud_group_workspace\Service\WorkspaceMemberHelper;

/**
 * Callback for group_content entity hooks.
 */
class GroupContentSync {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $userEndpoint
   *   User endpoint.
   * @param \Drupal\poc_nextcloud_group_workspace\Service\WorkspaceMemberHelper $workspaceMemberHelper
   *   Helper to update workspace members when responding to different events.
   */
  public function __construct(
    private NxUserEndpoint $userEndpoint,
    private WorkspaceMemberHelper $workspaceMemberHelper,
  ) {}

  /**
   * Responds to a group content hook.
   *
   * @param \Drupal\group\Entity\GroupContentInterface $group_content
   *   Group content entity.
   * @param string $op
   *   Verb from the hook name, e.g. 'update'.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function __invoke(GroupContentInterface $group_content, string $op): void {
    try {
      $membership = new GroupMembership($group_content);
    }
    catch (\Exception) {
      // This is not a user membership, but some other group content.
      return;
    }
    $user = $membership->getUser();
    $username = $user->getAccountName();
    $nextcloud_user = $this->userEndpoint->load($username);
    if (!$nextcloud_user) {
      return;
    }
    $this->workspaceMemberHelper->updateNextcloudUserGroupsForMembership($membership, $nextcloud_user);
  }

}
