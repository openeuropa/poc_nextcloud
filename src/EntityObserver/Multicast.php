<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\EntityObserver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\poc_nextcloud\Exception\ServiceNotAvailableException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Delegates entity events to multiple observers/subscribers.
 */
class Multicast implements EntityObserverInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\EntityObserver\EntityObserverInterface[] $observers
   *   List of observers to delegate to.
   */
  public function __construct(
    private array $observers,
  ) {}

  /**
   * Creates a new instance from service ids for the callbacks.
   *
   * If any of the services is not available, e.g. due to incomplete
   * configuration, an empty dispatcher is returned instead.
   *
   * @param \Psr\Container\ContainerInterface $container
   *   Container.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param string[] $service_ids
   *   Service ids. Each of these services should be callable.
   *
   * @return self
   *   New instance, either with the given callbacks, or with no callbacks.
   *
   * @throws \Psr\Container\ContainerExceptionInterface
   */
  public static function fromServiceIdsWithEmptyFallback(
    ContainerInterface $container,
    LoggerInterface $logger,
    array $service_ids,
  ): self {
    try {
      $observers = [];
      foreach ($service_ids as $service_id) {
        $candidate = $container->get($service_id);
        if (!$candidate instanceof EntityObserverInterface) {
          throw new InvalidArgumentException(sprintf(
            'Expected a %s for service id %s, found %s.',
            EntityObserverInterface::class,
            $service_id,
            get_debug_type($candidate),
          ));
        }
        $observers[] = $candidate;
      }
      return new self($observers);
    }
    catch (ServiceNotAvailableException $e) {
      $logger->warning("Service '@service_id' is currently not available. Message: @message.", [
        '@service_id' => $callback_service_id ?? '?',
        '@message' => $e->getMessage(),
      ]);
      return new self([]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function entityOp(EntityInterface $entity, string $op): void {
    foreach ($this->observers as $listener) {
      $listener->entityOp($entity, $op);
    }
  }

}
