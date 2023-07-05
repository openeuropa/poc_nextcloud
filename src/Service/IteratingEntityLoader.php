<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Helper to load a potentially big range of entities in a memory-friendly way.
 */
class IteratingEntityLoader {

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Gets an iterable for a specific entity type.
   *
   * @param string $entity_type_id
   *   Entity type id.
   *
   * @return \Drupal\poc_nextcloud\Service\EntityIterable
   *   Iterable for entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   Entity not found or misconfigured.
   */
  public function forType(string $entity_type_id): EntityIterable {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $query = $storage->getQuery();
    return new EntityIterable($storage, $query);
  }

}
