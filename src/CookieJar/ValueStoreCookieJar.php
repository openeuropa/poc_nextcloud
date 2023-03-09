<?php

namespace Drupal\poc_nextcloud\CookieJar;

use Drupal\poc_nextcloud\ValueStore\ValueStoreInterface;

/**
 * Persists cookies into a value store object.
 *
 * @see \GuzzleHttp\Cookie\FileCookieJar
 */
class ValueStoreCookieJar extends PersistentCookieJarBase {

  /**
   * Constructor.
   *
   * This cookie jar always starts empty, it must be initialized first.
   *
   * @param \Drupal\poc_nextcloud\ValueStore\ValueStoreInterface $valueStore
   *   Storage for a single value.
   * @param bool $storeSessionCookies
   *   Whether to store session cookies.
   * @param bool $strictMode
   *   TRUE to throw exceptions when invalid cookies are added.
   */
  public function __construct(
    private ValueStoreInterface $valueStore,
    bool $storeSessionCookies = TRUE,
    bool $strictMode = FALSE,
  ) {
    parent::__construct(
      $storeSessionCookies,
      $strictMode,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function loadData(): array {
    // @todo Catch and log.
    return $this->valueStore->get() ?? [];
  }

  /**
   * {@inheritdoc}
   */
  protected function saveData(array $data): void {
    // @todo Catch and log.
    $this->valueStore->set($data);
  }

}
