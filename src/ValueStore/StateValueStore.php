<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\ValueStore;

use Drupal\Core\State\StateInterface;

/**
 * Single-value store that uses the state system.
 */
final class StateValueStore implements ValueStoreInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   State system.
   * @param string $key
   *   State key.
   */
  public function __construct(
    private StateInterface $state,
    private string $key,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function get(): mixed {
    return $this->state->get($this->key);
  }

  /**
   * {@inheritdoc}
   */
  public function set(mixed $value): void {
    if ($value !== NULL) {
      $this->state->set($this->key, $value);
    }
    else {
      $this->state->delete($this->key);
    }
  }

}
