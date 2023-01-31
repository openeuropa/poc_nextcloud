<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\EntityHook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\poc_nextcloud\Exception\ServiceNotAvailableException;
use Psr\Container\ContainerInterface;

/**
 * Dispatcher for entity hook callbacks.
 *
 * This is needed because the entity hook callbacks may be not unavailable if
 * the Nextcloud is not configured or not available.
 */
class EntityHookDispatcher {

  /**
   * Constructor.
   *
   * @param array $callbacks
   *   Callbacks by entity type id.
   *
   * @psalm-param array<string, callable(EntityInterface, string): void> $callbacks
   */
  public function __construct(
    private array $callbacks,
  ) {}

  /**
   * Creates a new instance from service ids for the callbacks.
   *
   * @param \Psr\Container\ContainerInterface $container
   *   Container.
   * @param string[] $service_ids
   *   Service ids.
   *
   * @return self
   *   New instance.
   *
   * @throws \Psr\Container\ContainerExceptionInterface
   */
  public static function fromServiceIdsWithEmptyFallback(
    ContainerInterface $container,
    array $service_ids,
  ): self {
    try {
      $callbacks = [];
      foreach ($service_ids as $entity_id => $callback_service_id) {
        $callbacks[$entity_id] = $container->get($callback_service_id);
      }
    }
    catch (ServiceNotAvailableException) {
      return new self([]);
    }
    return new self($callbacks);
  }

  /**
   * Callback to respond to an entity hook.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which the hook was triggered.
   * @param string $op
   *   Verb from the entity hook.
   *   One of 'presave', 'insert', 'update', 'delete'.
   */
  public function __invoke(EntityInterface $entity, string $op): void {
    static $stack = [];
    $callback = $this->callbacks[$entity->getEntityTypeId()] ?? NULL;
    if (!$callback) {
      // @todo Throw an exception here, once the module is finished.
      return;
    }
    // Maintain a stack for debugging.
    // @todo Remove this extra logic, it seems no longer needed.
    $stack[] = $entity->getEntityTypeId() . ':' . $entity->id() . ':' . $op;
    if (count($stack) > 10) {
      // This could happen if the callback triggers another entity save, and
      // does not properly prevent recursion. This would be a programming error.
      // Leave this check in place for future versions of the module.
      throw new \RuntimeException(sprintf(
        'Likely infinite recursion detected: %s.',
        implode(', ', $stack),
      ));
    }
    try {
      $callback($entity, $op);
    }
    finally {
      array_pop($stack);
    }
  }

}
