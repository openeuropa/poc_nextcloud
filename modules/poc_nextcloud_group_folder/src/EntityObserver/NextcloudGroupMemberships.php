<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\EntityObserver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\group\Entity\GroupContentInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\group\Entity\GroupTypeInterface;
use Drupal\group\GroupMembership;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint;
use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud\EntityObserver\EntityObserverInterface;
use Drupal\poc_nextcloud\Service\NextcloudUserMap;
use Drupal\poc_nextcloud\WritableImage\GroupsUsersImage;
use Drupal\poc_nextcloud\WritableImage\UserGroupsImage;
use Drupal\poc_nextcloud_group_folder\Service\DrupalGroupLoader;
use Drupal\poc_nextcloud_group_folder\Service\GroupRoleToGroupId;
use Drupal\user\UserInterface;

/**
 * Creates Nextcloud group memberships for Drupal group memberships.
 */
class NextcloudGroupMemberships implements EntityObserverInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\group\GroupMembershipLoaderInterface $groupMembershipLoader
   *   Group membership loader.
   * @param \Drupal\poc_nextcloud\Service\NextcloudUserMap $nextcloudUserMap
   *   Service to get Nextcloud user for Drupal user.
   * @param \Drupal\poc_nextcloud\Endpoint\NxGroupEndpoint $groupEndpoint
   *   Group endpoint.
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $userEndpoint
   *   User endpoint.
   * @param \Drupal\poc_nextcloud_group_folder\Service\GroupRoleToGroupId $groupRoleToGroupId
   *   Service to build Nextcloud group names and namespaces.
   * @param \Drupal\poc_nextcloud_group_folder\Service\DrupalGroupLoader $drupalGroupLoader
   *   Service to load groups and group types.
   */
  public function __construct(
    private GroupMembershipLoaderInterface $groupMembershipLoader,
    private NextcloudUserMap $nextcloudUserMap,
    private NxGroupEndpoint $groupEndpoint,
    private NxUserEndpoint $userEndpoint,
    private GroupRoleToGroupId $groupRoleToGroupId,
    private DrupalGroupLoader $drupalGroupLoader,
  ) {}

  /**
   * Rebuilds all Nextcloud group memberships related to this module.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function rebuildAll(): void {
    $this->updateGroupsScope(
      NULL,
      NULL,
      NULL,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function entityOp(EntityInterface $entity, string $op): void {
    if (!in_array($op, ['update', 'insert', 'delete'])) {
      return;
    }
    if ($entity instanceof GroupContentInterface) {
      try {
        $entity = new GroupMembership($entity);
      }
      catch (\Exception) {
        return;
      }
      $user = $entity->getUser();
      if (!$user) {
        // The user was already deleted. Nothing to do.
        return;
      }
      $this->updateUserScope(
        $user,
        $entity,
      );
    }
    elseif ($entity instanceof UserInterface) {
      $this->updateUserScope(
        $entity,
        NULL,
      );
    }
    elseif ($entity instanceof GroupInterface) {
      $this->updateGroupsScope(
        $entity->getGroupType(),
        NULL,
        $entity,
      );
    }
    elseif ($entity instanceof GroupTypeInterface) {
      $this->updateGroupsScope(
        $entity,
        NULL,
        NULL,
      );
    }
    elseif ($entity instanceof GroupRoleInterface) {
      $this->updateGroupsScope(
        $entity->getGroupType(),
        $entity,
        NULL,
      );
    }
  }

  /**
   * Updates a range of group memberships.
   *
   * @param \Drupal\group\Entity\GroupTypeInterface|null $drupal_group_type
   *   Group type, or NULL for all group types.
   * @param \Drupal\group\Entity\GroupRoleInterface|null $drupal_group_role
   *   Group role, or NULL for all group roles in the given type(s).
   *   If $group_type is NULL, the role will also be NULL.
   * @param \Drupal\group\Entity\GroupInterface|null $drupal_group
   *   Group, or NULL for all groups in the given type(s).
   *   If $group_type is NULL, the group will also be NULL.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function updateGroupsScope(
    ?GroupTypeInterface $drupal_group_type,
    ?GroupRoleInterface $drupal_group_role,
    ?GroupInterface $drupal_group,
  ): void {
    $image = new GroupsUsersImage(
      $this->groupEndpoint,
      $this->userEndpoint,
      $this->groupRoleToGroupId->getGroupNamespaceRegex(
        $drupal_group,
        $drupal_group_role ?? $drupal_group_type,
      ),
    );
    if ($drupal_group !== NULL) {
      $this->processGroup(
        $image,
        $drupal_group_role,
        $drupal_group,
      );
    }
    elseif ($drupal_group_type !== NULL) {
      $this->processGroupType(
        $image,
        $drupal_group_type,
        $drupal_group_role,
      );
    }
    else {
      foreach ($this->drupalGroupLoader->loadGroupTypes() as $a_group_type) {
        $this->processGroupType(
          $image,
          $a_group_type,
          NULL,
        );
      }
    }
    $image->write();
  }

  /**
   * Updates memberships for a single user or single membership.
   *
   * @param \Drupal\user\UserInterface $drupal_user
   *   Drupal user.
   * @param \Drupal\group\GroupMembership|null $drupal_membership
   *   A specific Drupal group membershp, or NULL for all memberships of the
   *   given user.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function updateUserScope(
    UserInterface $drupal_user,
    ?GroupMembership $drupal_membership,
  ): void {
    $nextcloud_user = $this->nextcloudUserMap->getNextcloudUser($drupal_user);
    if ($nextcloud_user === NULL) {
      return;
    }
    $drupal_group = $drupal_membership?->getGroup();
    $drupal_group_type = $drupal_group?->getGroupType();
    $image = new UserGroupsImage(
      $this->userEndpoint,
      $this->groupEndpoint,
      $nextcloud_user->getId(),
      $this->groupRoleToGroupId->getGroupNamespaceRegex(
        $drupal_group,
        $drupal_group_type,
      ),
    );
    // @todo More sophisticated mapping.
    if ($drupal_membership !== NULL) {
      $this->processMembership(
        [$image, 'addGroup'],
        $drupal_membership,
        NULL,
      );
    }
    else {
      foreach ($this->groupMembershipLoader->loadByUser($drupal_user) as $a_drupal_membership) {
        $this->processMembership(
          [$image, 'addGroup'],
          $a_drupal_membership,
          NULL,
        );
      }
    }
    $image->write();
  }

  /**
   * Collects memberships for a single group type.
   *
   * @param \Drupal\poc_nextcloud\WritableImage\GroupsUsersImage $image
   *   Image to collect Nextcloud group memberships.
   * @param \Drupal\group\Entity\GroupTypeInterface $drupal_group_type
   *   Drupal group type.
   * @param \Drupal\group\Entity\GroupRoleInterface|null $drupal_group_role
   *   Drupal group role, or NULL for all roles of the type.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function processGroupType(
    GroupsUsersImage $image,
    GroupTypeInterface $drupal_group_type,
    ?GroupRoleInterface $drupal_group_role,
  ): void {
    $drupal_groups = $this->drupalGroupLoader->loadGroupsForType($drupal_group_type);
    foreach ($drupal_groups as $drupal_group) {
      $this->processGroup(
        $image,
        $drupal_group_role,
        $drupal_group,
      );
    }
  }

  /**
   * Processes a group.
   *
   * @param \Drupal\poc_nextcloud\WritableImage\GroupsUsersImage $image
   *   Image to collect Nextcloud group memberships.
   * @param \Drupal\group\Entity\GroupRoleInterface|null $drupal_group_role
   *   Drupal group role, or NULL for all roles in the group.
   * @param \Drupal\group\Entity\GroupInterface $drupal_group
   *   Drupal group.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  private function processGroup(
    GroupsUsersImage $image,
    ?GroupRoleInterface $drupal_group_role,
    GroupInterface $drupal_group,
  ): void {
    foreach ($drupal_group->getMembers() as $drupal_membership) {
      $nextcloud_user = $this->nextcloudUserMap->getNextcloudUser($drupal_membership->getUser());
      if (!$nextcloud_user) {
        // If the user does not exist, no memberships can be updated.
      }
      $this->processMembership(
        fn ($group_id) => $image->addUserGroup(
          $nextcloud_user->getId(),
          $group_id,
        ),
        $drupal_membership,
        $drupal_group_role,
      );
    }
  }

  /**
   * Collects Nextcloud memberships for a single Drupal group membership.
   *
   * @param callable $add_group_id
   *   Callback to add a Nextcloud group id.
   * @param \Drupal\group\GroupMembership $drupal_membership
   *   Drupal group membership.
   * @param \Drupal\group\Entity\GroupRoleInterface|null $drupal_group_role
   *   Drupal group role.
   *
   * @psalm-param callable(string): void $add_group_id
   */
  private function processMembership(
    callable $add_group_id,
    GroupMembership $drupal_membership,
    ?GroupRoleInterface $drupal_group_role,
  ): void {
    foreach ($drupal_membership->getRoles() as $a_drupal_group_role) {
      if ($drupal_group_role !== NULL && $a_drupal_group_role->id() !== $drupal_group_role->id()) {
        continue;
      }
      $group_id = $this->groupRoleToGroupId->buildGroupId(
        $drupal_membership->getGroup(),
        $a_drupal_group_role,
      );
      if (!$group_id) {
        continue;
      }
      $add_group_id($group_id);
    }
  }

}
