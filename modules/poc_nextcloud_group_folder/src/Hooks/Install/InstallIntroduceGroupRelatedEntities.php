<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud_group_folder\Hooks\Install;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\hux\Attribute\Hook;
use Drupal\poc_nextcloud\Service\IteratingEntityLoader;

/**
 * Install hook to initialize tracking tables based on existing entities.
 */
class InstallIntroduceGroupRelatedEntities {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Service\IteratingEntityLoader $iteratingEntityLoader
   *   Iterating entity loader.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(
    private IteratingEntityLoader $iteratingEntityLoader,
    private ModuleHandlerInterface $moduleHandler,
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_install().
   */
  #[Hook('install')]
  public function install(): void {
    $module = explode('\\', static::class, 3)[1];
    $entity_type_ids = ['group', 'group_role'];
    if ($this->entityTypeManager->hasDefinition('group_relationship')) {
      $entity_type_ids[] = 'group_relationship';
    }
    else {
      $entity_type_ids[] = 'group_content';
    }
    foreach ($entity_type_ids as $entity_type_id) {
      foreach ($this->iteratingEntityLoader->forType($entity_type_id) as $entity) {
        $this->moduleHandler->invoke($module, $entity_type_id . '_install', [$entity]);
      }
    }
  }

}
