<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\NxEntity;

use Drupal\poc_nextcloud\Endpoint\EntityEndpoint;

/**
 * Interface for Nextcloud entities.
 *
 * Unlike Drupal entities, these are implemented as immutable value objects.
 * An instance does not represent "the entity", but rather a snapshot of an
 * entity at a given time, or a possible state that an entity could be in.
 *
 * @template IdType as string|int
 */
interface NxEntityInterface {

  /**
   * Creates a Nextcloud entity.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\EntityEndpoint $endpoint
   *   The suitable API endpoint.
   *
   * @return int|string
   *   Id of the new entity.
   *
   * @psalm-return IdType
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Failed to create the new entity.
   *
   * @todo Instead of passing the correct endpoint, pass an object that has
   *   all kinds of endpoints.
   */
  public function save(EntityEndpoint $endpoint): string|int;

  /**
   * Gets an id how the entity can be referenced.
   *
   * @return string|int
   *   E.g. "Philippe" for users, or a numeric id for other entities.
   *
   * @psalm-return IdType
   */
  public function getId(): string|int;

}
