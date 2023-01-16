<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Endpoint;

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\Exception\NextcloudApiException;

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
  private mixed $createObjectFromData;

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Connection\ApiConnectionInterface $connection
   *   Nextcloud API connection.
   * @param string $basePath
   *   Path for the endpoint.
   * @param callable $createObjectFromData
   *   Callback to create a new entity object from response data.
   *
   * @psalm-param callable(array): EntityClass $createObjectFromData
   */
  public function __construct(
    protected ApiConnectionInterface $connection,
    protected string $basePath,
    callable $createObjectFromData,
  ) {
    $this->createObjectFromData = $createObjectFromData;
  }

  /**
   * {@inheritdoc}
   */
  public function create(array $entityValues): int|string {
    return $this->connection->request('POST', $this->basePath, $entityValues)
      ->throwIfFailure()
      ->getData()['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function load(int|string $id): object|null {
    $response = $this->connection->request('GET', $this->basePath . '/' . $id);
    if ($response->isFailure()) {
      // Not found.
      // @todo Inspect the status code.
      return NULL;
    }
    /** @var \Drupal\poc_nextcloud\NxEntity\NxEntityInterface $entity */
    $entity = ($this->createObjectFromData)($response->getData());
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
