<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Query\QueryInterface;

/**
 * Itera to load a potentially big range of entities in a memory-friendly way.
 */
class EntityIterable implements \IteratorAggregate {

  /**
   * How many entities to load and keep in php memory at once.
   *
   * @var int
   */
  private int $entitiesChunkSize = 10;

  /**
   * How many ids to load and keep in php and sql memory at once.
   *
   * This is to protect websites with millions of entities of a given type.
   * It also limits the loading of unnecessary ids when only a few entities are
   * processed.
   *
   * @var int
   */
  private int $idsChunkSize = 100;

  /**
   * Upper limit for entity id.
   *
   * @var int|null
   */
  private ?int $supId = NULL;

  /**
   * Id key.
   *
   * @var string
   */
  private string $idKey;

  /**
   * Bundle key.
   *
   * @var string
   */
  private string $bundleKey;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   Entity storage.
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   Entity query.
   */
  public function __construct(
    private EntityStorageInterface $storage,
    private QueryInterface $query,
  ) {
    assert($storage->getEntityTypeId() === $query->getEntityTypeId(), sprintf(
      '%s !== %s',
      var_export($storage->getEntityTypeId(), TRUE),
      var_export($query->getEntityTypeId(), TRUE),
    ));
    $entity_type = $storage->getEntityType();
    ['id' => $this->idKey, 'bundle' => $this->bundleKey] = $entity_type->getKeys();
  }

  /**
   * Immutable setter. Sets the number of entities to keep in memory at once.
   *
   * @param int $size
   *   Number of entities to keep in memory at once.
   *
   * @return static
   *   Modified cloned instance.
   */
  public function withChunkSize(int $size): static {
    $clone = clone $this;
    $clone->entitiesChunkSize = $size;
    return $clone;
  }

  /**
   * Immutable setter. Sets an upper limit for the entity id.
   *
   * @param int $sup_id
   *   Upper limit for the entity id.
   *   Only entities with id smaller than this will be loaded.
   *
   * @return static
   *   Modified cloned instance.
   */
  public function withSupId(int $sup_id): static {
    $clone = clone $this;
    $clone->supId = $sup_id;
    return $clone;
  }

  /**
   * Immutable setter. Adds a field condition to the entity query.
   *
   * @param string $field
   *   Field name.
   * @param mixed $value
   *   Value to filter by.
   *
   * @return static
   */
  public function withCondition(string $field, mixed $value): static {
    $clone = clone $this;
    $clone->query = clone $this->query;
    $clone->query->condition($field, $value);
    return $clone;
  }

  /**
   * Immutable setter. Adds a field condition to the entity query.
   *
   * @param string[] $bundles
   *   Bundle names to filter by.
   *
   * @return static
   */
  public function withBundles(array $bundles): static {
    $clone = clone $this;
    $clone->query = clone $this->query;
    $clone->query->condition($this->bundleKey, $bundles);
    return $clone;
  }

  /**
   * Immutable setter. Alters the query using a callback.
   *
   * @param callable $alter
   *   Callback to alter the query.
   *
   * @return static
   */
  public function withQueryAlteration(callable $alter): static {
    $clone = clone $this;
    $clone->query = clone $this->query;
    $alter($clone->query);
    return $clone;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return \Iterator<int, \Drupal\Core\Entity\EntityInterface>
   */
  public function getIterator(): \Iterator {
    foreach ($this->loadIdChunks() as $ids) {
      $entities = $this->storage->loadMultiple($ids);
      krsort($entities);
      yield from $entities;
    }
  }

  /**
   * Loads chunks of entity ids, in reverse order.
   *
   * @return \Iterator
   *   Iterator of entity ids.
   */
  private function loadIdChunks(): \Iterator {
    $sup_id = $this->supId;
    while (TRUE) {
      $query = clone $this->query;
      $query->sort($this->idKey, 'DESC');
      $query->range(0, $this->idsChunkSize);
      if ($sup_id !== NULL) {
        $query->condition($this->idKey, $sup_id, '<');
      }
      $ids = $query->execute();
      if (!$ids) {
        // Done.
        break;
      }
      yield from array_chunk($ids, $this->entitiesChunkSize);
      if (count($ids) < $this->idsChunkSize) {
        // The next chunk would be empty.
        break;
      }
      $sup_id = min($ids);
    }
  }

}
