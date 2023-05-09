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
    /* @noinspection PhpInapplicableAttributeTargetDeclarationInspection */
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
    set_error_handler($this->getExceptionErrorHandler());
    try {
      $encrypted_value = openssl_encrypt(
        $value,
        $this->cipherAlgo,
        $this->secret,
        0,
        $iv,
        $tag,
      );
    }
    finally {
      restore_error_handler();
    }
    if ($encrypted_value === FALSE) {
      throw new ValueException('Failed to encrypt value.');
    }
    return [$encrypted_value, $iv, $tag];
  }

  /**
   * {@inheritdoc}
   */
  public function decrypt(string|array $encrypted_record): string {
    [$encrypted_record, $iv, $tag] = $encrypted_record;
    set_error_handler($this->getExceptionErrorHandler());
    try {
      $value = openssl_decrypt(
        $encrypted_record,
        $this->cipherAlgo,
        $this->secret,
        0,
        $iv,
        $tag,
      );
    }
    finally {
      restore_error_handler();
    }
    if ($value === FALSE) {
      throw new ValueException('Failed to decrypt value.');
    }
    return $value;
  }

  /**
   * Gets an error handler callback that throws exceptions.
   *
   * @return callable
   *   Error handler callback.
   */
  private function getExceptionErrorHandler(): callable {
    return function (
      int $errno,
      string $errstr,
    ) {
      throw new ValueException(sprintf(
        'Failed to encrypt value: [%s] %s',
        $errno,
        $errstr,
      ));
    };
  }

}
