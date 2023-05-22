<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\Tracker;

use Drupal\group\Entity\GroupContentInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\group\GroupMembership;
use Drupal\hux\Attribute\Hook;
use Drupal\poc_nextcloud\Tracking\RecordSubmit\NcUserGroupSubmit;
use Drupal\poc_nextcloud\Tracking\Tracker\TrackerBase;
use Drupal\poc_nextcloud\Tracking\Tracker\UserNcUserTracker;
use Drupal\poc_nextcloud\Tracking\TrackingTableFactory;
use Drupal\poc_nextcloud\Tracking\TrackingTableRelationship;

/**
 * Queues up user data for write to Nextcloud.
 */
class GroupMembershipRoleNcUserGroupTracker extends TrackerBase {

  const TABLE_NAME = 'poc_nextcloud_group_membership_role_nc_user_group';

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Tracking\TrackingTableFactory $trackingTableFactory
   *   Tracking table factory.
   */
  public function __construct(
    TrackingTableFactory $trackingTableFactory,
  ) {
    parent::__construct(
      NcUserGroupSubmit::class,
      $trackingTableFactory->create(self::TABLE_NAME)
        ->addLocalPrimaryField('uid', [
          'description' => 'Drupal user id',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ])
        ->addLocalPrimaryField('gid', [
          'description' => 'Drupal group id',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ])
        ->addLocalPrimaryField('group_role_id', [
          'description' => 'Drupal group role id',
          'type' => 'varchar',
          'length' => 254,
          'not null' => TRUE,
        ])
        ->addParentTableRelationship('u', new TrackingTableRelationship(
          UserNcUserTracker::TABLE_NAME,
          ['uid' => 'uid'],
          ['nc_user_id'],
        ))
        ->addParentTableRelationship('g', new TrackingTableRelationship(
          GroupAndRoleNcGroupTracker::TABLE_NAME,
          ['gid' => 'gid', 'group_role_id' => 'group_role_id'],
          ['nc_group_id'],
        )),
    );
  }

  /**
   * Implements hook_group_role_delete().
   *
   * @param \Drupal\group\Entity\GroupRoleInterface $group_role
   *   Group role.
   *
   * @todo Is this already covered with group_content_delete?
   *   If so, remove this method.
   */
  #[Hook('group_role_delete')]
  public function groupRoleDelete(GroupRoleInterface $group_role): void {
    $this->trackingTable->queueDelete([
      'group_role_id' => $group_role->id(),
    ]);
  }

  /**
   * Implements hook_group_content_delete().
   *
   * @param \Drupal\group\Entity\GroupRelationshipInterface|\Drupal\group\Entity\GroupContentInterface $group_content
   *   Group content entity.
   */
  #[Hook('group_content_delete')]
  #[Hook('group_relationship_delete')]
  public function groupContentDelete(GroupRelationshipInterface|GroupContentInterface $group_content): void {
    // Check if this is a membership or something else.
    // Doing it with this way works for different versions of drupal/group.
    try {
      new GroupMembership($group_content);
    }
    catch (\Exception) {
      // This is a different type of group content, not a membership.
      return;
    }
    $this->trackingTable->queueDelete([
      // Use code that works for different versions of drupal/group.
      'uid' => $group_content->get('entity_id')->target_id,
      'gid' => $group_content->get('gid')->target_id,
    ]);
  }

  /**
   * Implements hook_group_content_insert() and hook_group_content_update().
   *
   * @param \Drupal\group\Entity\GroupRelationshipInterface|\Drupal\group\Entity\GroupContentInterface $group_content
   *   Group content entity.
   *   The interface is different for different versions of drupal/group.
   */
  #[Hook('group_content_insert'), Hook('group_content_update')]
  #[Hook('group_relationship_insert'), Hook('group_relationship_update')]
  public function groupContentWrite(GroupRelationshipInterface|GroupContentInterface $group_content): void {
    try {
      $membership = new GroupMembership($group_content);
    }
    catch (\Exception) {
      // This is a different type of group content, not a membership.
      return;
    }
    $roles = $membership->getRoles();
    $condition = [
      // Use code that works for different versions of drupal/group.
      'uid' => $group_content->get('entity_id')->target_id,
      'gid' => $group_content->get('gid')->target_id,
    ];
    // Queue all for deletion, then re-insert those we want to keep.
    // @todo Create a method that does both.
    $this->trackingTable->queueDelete($condition);
    // Re-insert records we want to keep.
    foreach ($roles as $role) {
      $this->trackingTable->queueWrite(
        $condition + ['group_role_id' => $role->id()],
      );
    }
  }

}
