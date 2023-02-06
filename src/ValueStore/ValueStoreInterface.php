<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\ValueStore;

/**
 * Object to store a single value.
 *
 * Typically this will be a wrapper around another storage class.
 *
 * @todo Check if a class/interface like this already exists somewhere.
 */
interface ValueStoreInterface {

  /**
   * Reads the value.
   *
   * @return mixed
   *   Value, or NULL if not initialized.
   */
  public function get(): mixed;

  /**
   * Sets the value.
   *
   * @param mixed $value
   *   New value.
   */
  public function set(mixed $value): void;

}
