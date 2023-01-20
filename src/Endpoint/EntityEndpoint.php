<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Endpoint;

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\Exception\NextcloudApiException;
use Drupal\poc_nextcloud\NxEntity\NxEntityInterface;

/**
 * Default class for entity endpoints.
 *
 * @template EntityClass as \Drupal\poc_nextcloud\NxEntity\NxEntityInterface
 * @template IdType as int|string
 *
 * @template-extends \Drupal\poc_nextcloud\Endpoint\EntityEndpointInterface<EntityClass, IdType>
 */
class EntityEndpoint implements EntityEndpointInterface {

  /**
   * Callback to create a new entity object from response data.
   *
   * @var callable
   */
  protected mixed $denormalize;

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Connection\ApiConnectionInterface $connection
   *   Nextcloud API connection.
   * @param string $basePath
   *   Path for the endpoint.
   * @param string $entityClass
   *   Class or interface for entity objects.
   * @param callable $denormalize
   *   Callback to create entity object from loaded data.
   *
   * @psalm-param class-string<EntityClass> $entityClass
   * @psalm-param callable(array): EntityClass $denormalize
   */
  public function __construct(
    protected ApiConnectionInterface $connection,
    protected string $basePath,
    protected string $entityClass,
    callable $denormalize,
  ) {
    $this->denormalize = $denormalize;
  }

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
  public function insert(NxEntityInterface $entity): int|string {
    if (!$entity->isStub()) {
      throw new NextcloudApiException('Entity must be a stub.');
    }
    if (!$entity instanceof $this->entityClass) {
      // This is a programming error.
      throw new \InvalidArgumentException(sprintf(
        'Entity must be an instance of %s, found %s.',
        $this->entityClass,
        get_class($entity),
      ));
    }
    $entityValues = $entity->exportForInsert();
    // For some entity types, the stub object already contains an id.
    $new_id = $this->insertValues($entityValues)['id'];
    $stub_id = $entity->getId();
    if ($stub_id !== NULL && $new_id !== $stub_id) {
      throw new NextcloudApiException(sprintf(
        'Expected insert id %s, but API returned %s.',
        $stub_id,
        $new_id,
      ));
    }
    return $new_id;
  }

  /**
   * Creates a Nextcloud entity from an array of values.
   *
   * @param array $values
   *   Values for the new entity.
   *
   * @return mixed
   *   Data from $response_data['ocs']['data'].
   *   Typically this will contain the new id.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Failed to create the new entity.
   */
  protected function insertValues(array $values): mixed {
    return $this->connection->request('POST', $this->basePath, $values)
      ->throwIfFailure()
      ->getData();
  }

  /**
   * {@inheritdoc}
   */
  public function load(int|string $id): object|null {
    $response = $this->connection->request('GET', $this->basePath . '/' . $id);
    if ($response->getStatusCode() === 404) {
      return NULL;
    }
    // Check if something else went wrong.
    $response->throwIfFailure();
    /** @var \Drupal\poc_nextcloud\NxEntity\NxEntityInterface $entity */
    $entity = ($this->denormalize)($response->getData());
    if ($entity->getId() !== $id) {
      throw new NextcloudApiException(sprintf('Loaded id is %s, while expected id is %s.', $entity->getId(), $id));
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function loadIds(): array {
    return $this->connection->request('GET', $this->basePath)
      ->throwIfFailure()
      ->getData();
  }

  /**
   * {@inheritdoc}
   */
  public function loadAll(): array {
    $ids = $this->loadIds();
    $ids = array_combine($ids, $ids);
    return array_map([$this, 'load'], $ids);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteIfExists(int|string $id): void {
    $this->connection->request('DELETE', $this->basePath . '/' . $id)
      ->nullIfStatusCode(998)
      ?->throwIfFailure();
  }

  /**
   * {@inheritdoc}
   */
  public function delete(int|string $id): void {
    $this->connection->request('DELETE', $this->basePath . '/' . $id)
      ->throwIfFailure();
  }

  /**
   * {@inheritdoc}
   */
  public function update(int|string $id, array $entityValues): void {
    // Base implementation works for users, but might not work for other entity
    // types.
    foreach ($entityValues as $key => $value) {
      $this->connection->request('PUT', $this->basePath . '/' . $id, [
        'key' => $key,
        'value' => $value,
      ])->throwIfFailure();
    }
  }

}
