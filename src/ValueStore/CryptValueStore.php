<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\ValueStore;

use Drupal\poc_nextcloud\Crypt\CryptorInterface;

/**
 * Decorator using encryption.
 */
final class CryptValueStore implements ValueStoreInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\ValueStore\ValueStoreInterface $decorated
   *   Decorated value store.
   * @param \Drupal\poc_nextcloud\Crypt\CryptorInterface $crypt
   *   Encryption and decryption.
   */
  public function __construct(
    private ValueStoreInterface $decorated,
    private CryptorInterface $crypt,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function get(): mixed {
    $stored_record = $this->decorated->get();
    $serialized_value = $this->crypt->decrypt($stored_record);
    return unserialize($serialized_value);
  }

  /**
   * {@inheritdoc}
   */
  public function set(mixed $value): void {
    $serialized_value = serialize($value);
    $record = $this->crypt->encrypt($serialized_value);
    $this->decorated->set($record);
  }

}
