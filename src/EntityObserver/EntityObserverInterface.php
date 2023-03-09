<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\EntityObserver;

use Drupal\Core\Entity\EntityInterface;

/**
 * Object that responds to entity hooks.
 */
interface EntityObserverInterface {

  /**
   * Responds to an entity hook.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which the event was fired.
   * @param string $op
   *   Verb from an entity hook, e.g. 'presave', 'update', 'insert', 'delete'.
   *
   * @throws \Exception
   */
  public function entityOp(EntityInterface $entity, string $op): void;

}
