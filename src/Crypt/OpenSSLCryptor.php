<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Crypt;

use Drupal\poc_nextcloud\Exception\ValueException;

/**
 * Encryption using openssl.
 */
class OpenSSLCryptor implements CryptorInterface {

  /**
   * Constructor.
   *
   * @param string $secret
   *   Secret passphrase.
   * @param string $cipherAlgo
   *   Algorithm for OpenSSL.
   */
  public function __construct(
    #[\SensitiveParameter]
    private string $secret,
    private string $cipherAlgo = 'AES-256-GCM',
  ) {}

  /**
   * {@inheritdoc}
   */
  public function encrypt(string $value): string|array {
    $iv_length = openssl_cipher_iv_length($this->cipherAlgo);
    try {
      $iv = random_bytes($iv_length);
    }
    catch (\Exception $e) {
      throw new ValueException($e->getMessage(), 0, $e);
    }
    $encrypted_value = openssl_encrypt(
      $value,
      $this->cipherAlgo,
      $this->secret,
      0,
      $iv,
    );
    if ($encrypted_value === FALSE) {
      throw new ValueException('Failed to encrypt value.');
    }
    return [$encrypted_value, $iv];
  }

  /**
   * {@inheritdoc}
   */
  public function decrypt(string|array $encrypted_record): string {
    [$encrypted_record, $iv] = $encrypted_record;
    $value = openssl_decrypt(
      $encrypted_record,
      $this->cipherAlgo,
      $this->secret,
      0,
      $iv,
    );
    if ($value === FALSE) {
      throw new ValueException('Failed to decrypt value.');
    }
    return $value;
  }

}
