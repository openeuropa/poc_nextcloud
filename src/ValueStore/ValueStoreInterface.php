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
   *
   * @throws \Drupal\poc_nextcloud\Exception\ValueException
   *   Value cannot be read.
   *   Possibly the storage contains bad data, or the storage is failing.
   */
  public function get(): mixed;

  /**
   * Sets the value.
   *
   * @param mixed $value
   *   New value.
   *
   * @throws \Drupal\poc_nextcloud\Exception\ValueException
   *   Value cannot be written.
   *   Possibly the data is not suitable for storage, or the storage is failing.
   */
  public function set(mixed $value): void;

}
