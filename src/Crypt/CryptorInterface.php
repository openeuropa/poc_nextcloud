<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Crypt;

/**
 * Abstraction for encryption of values.
 *
 * @todo Check if a class/interface like this already exists somewhere.
 */
interface CryptorInterface {

  /**
   * Encrypts a value.
   *
   * @param string $value
   *   Value to encrypt, as string.
   *   It is up to calling code to serialize or otherwise stringify values.
   *
   * @return string|array
   *   Encrypted value, or a record with the encrypted value and additional
   *   data needed for decryption.
   *
   * @throws \Drupal\poc_nextcloud\Exception\ValueException
   */
  public function encrypt(string $value): string|array;

  /**
   * Decrypts a value.
   *
   * This is meant to be symmetric to encrypt().
   *
   * @param string|array $encrypted_record
   *   The encrypted value, or a record containing the value and additional
   *   data needed for decryption.
   *
   * @return string
   *   Decrypted value, as string.
   *   It is up to calling code to unserialize or otherwise de-stringify values.
   *
   * @throws \Drupal\poc_nextcloud\Exception\ValueException
   */
  public function decrypt(string|array $encrypted_record): string;

}
