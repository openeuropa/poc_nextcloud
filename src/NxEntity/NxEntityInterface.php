<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\NxEntity;

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
   * Exports data for insert.
   *
   * @return array
   *   Data to use in a create request.
   *
   * @todo Should this be part of the entity, or part of the repo or endpoint?
   */
  public function exportForInsert(): array;

  /**
   * Gets an id how the entity can be referenced.
   *
   * @return string|int|null
   *   The id, or NULL if this is a stub object and has no id.
   *   Note that for some entity types (e.g. user) even stub objects have an id.
   *
   * @psalm-return IdType
   */
  public function getId(): string|int|null;

  /**
   * Gets whether this is a "stub" entity.
   *
   * A "stub" entity is created from custom values, where as a non-stub entity
   * is loaded from the API.
   *
   * @return bool
   *   TRUE if created from custom values, FALSE if loaded from API.
   */
  public function isStub(): bool;

}
