<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Endpoint;

use Drupal\poc_nextcloud\NxEntity\NxEntityInterface;

/**
 * Interface for Nextcloud entity endpoints.
 *
 * Note that some endpoints might have more methods than this.
 *
 * @template EntityClass as \Drupal\poc_nextcloud\NxEntity\NxEntityInterface
 * @template IdType as int|string
 */
interface EntityEndpointInterface {

  /**
   * Creates a Nextcloud entity.
   *
   * @param \Drupal\poc_nextcloud\NxEntity\NxEntityInterface $entity
   *   Stub entity to be created.
   *
   * @return int|string
   *   Id of the new entity, or a failure response.
   *
   * @psalm-param EntityClass $entity
   * @psalm-return IdType
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Failed to create the new entity.
   */
  public function insert(NxEntityInterface $entity): int|string;

  /**
   * Loads a Nextcloud entity.
   *
   * @param int|string $id
   *   Entity id.
   *
   * @psalm-param IdType $id
   *
   * @return EntityClass|null
   *   The entity, or NULL if not found.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Something went wrong.
   */
  public function load(int|string $id): object|null;

  /**
   * Fetches all existing ids.
   *
   * @return int[]|string[]
   *   Nextcloud entity ids.
   *
   * @psalm-return list<IdType>
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Failed to fetch ids.
   */
  public function loadIds(): array;

  /**
   * Loads all existing entities.
   *
   * @return \Drupal\poc_nextcloud\NxEntity\NxEntityInterface[]
   *   Entities by id.
   *
   * @psalm-return array<IdType, EntityClass>
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Something went wrong.
   *
   * @todo Use an iterator or limit the results for scaling.
   * @todo Consider to remove this altogether. Having a list of ids is enough.
   */
  public function loadAll(): array;

  /**
   * Deletes an entity by the given id, if it exists.
   *
   * @param int|string $id
   *   Id of the entity to be deleted.
   *
   * @psalm-param IdType $id
   *
   * @throws \Drupal\poc_nextcloud\Exception\FailureResponseException
   *   API returned failure response.
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Something else went wrong.
   */
  public function deleteIfExists(int|string $id): void;

  /**
   * Deletes an entity by the given id.
   *
   * @param int|string $id
   *   Id of the entity to be deleted.
   *
   * @psalm-param IdType $id
   *
   * @throws \Drupal\poc_nextcloud\Exception\FailureResponseException
   *   API returned failure response.
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Something else went wrong.
   */
  public function delete(int|string $id): void;

  /**
   * Updates entity values.
   *
   * @param int|string $id
   *   Id of the entity to be updated.
   * @param array $entityValues
   *   New values.
   *
   * @psalm-param array<string, mixed> $entityValues
   *
   * @throws \Drupal\poc_nextcloud\Exception\FailureResponseException
   *   API returned failure response.
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Something else went wrong.
   */
  public function update(int|string $id, array $entityValues): void;

}
