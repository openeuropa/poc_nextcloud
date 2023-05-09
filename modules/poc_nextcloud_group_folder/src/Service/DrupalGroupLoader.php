<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\GroupTypeInterface;

/**
 * Service to load groups and group types.
 *
 * This is a one-off convenience wrapper around entity storages.
 * Purpose:
 * - Simplify autowire service definitions.
 * - Simplify constructors.
 * - Provide more specific return types.
 * - Provide dedicated parameter signatures.
 */
class DrupalGroupLoader {

  /**
   * Entity storage for groups.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private EntityStorageInterface $groupStorage;

  /**
   * Storage for 'group_type' entities.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private EntityStorageInterface $groupTypeStorage;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->groupTypeStorage = $entityTypeManager->getStorage('group_type');
    $this->groupStorage = $entityTypeManager->getStorage('group');
  }

  /**
   * Loads all groups for a given group type.
   *
   * @param \Drupal\group\Entity\GroupTypeInterface $group_type
   *   Group type.
   *
   * @return \Drupal\group\Entity\GroupInterface[]
   *   Groups.
   *
   * @todo Use a query, and return an iterator to not exhaust memory.
   */
  public function loadGroupsForType(GroupTypeInterface $group_type): array {
    /** @var \Drupal\group\Entity\GroupInterface[] $groups */
    $groups = $this->groupStorage->loadByProperties([
      'type' => $group_type->id(),
    ]);
    return $groups;
  }

  /**
   * Loads all group types.
   *
   * @return \Drupal\group\Entity\GroupTypeInterface[]
   *   Group types.
   */
  public function loadGroupTypes(): array {
    /** @var \Drupal\group\Entity\GroupTypeInterface[] $group_types */
    $group_types = $this->groupTypeStorage->loadMultiple();
    return $group_types;
  }

}
