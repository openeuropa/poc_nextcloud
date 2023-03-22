<?php

namespace Drupal\poc_nextcloud\CookieJar;

use Drupal\poc_nextcloud\ValueStore\ValueStoreInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Persists cookies into a value store object.
 *
 * @see \GuzzleHttp\Cookie\FileCookieJar
 */
class PersistentCookieJar extends CookieJar {

  /**
   * Data to compare if cookies have changed.
   *
   * @var array|null
   */
  private ?array $data = NULL;

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
    private bool $storeSessionCookies = TRUE,
    bool $strictMode = FALSE,
  ) {
    parent::__construct($strictMode);
    $this->load();
  }

  /**
   * {@inheritdoc}
   */
  public function extractCookies(RequestInterface $request, ResponseInterface $response) {
    parent::extractCookies($request, $response);
    $this->saveIfChanged();
  }

  /**
   * Loads cookies from storage into the jar.
   */
  private function load(): void {
    // @todo Catch and log.
    $data = $this->valueStore->get() ?? [];
    $this->data = $data;
    $this->import($data);
  }

  /**
   * Saves cookies from the jar into storage, if they have changed.
   */
  private function saveIfChanged(): void {
    $data = $this->export();
    if ($data === $this->data) {
      return;
    }
    // @todo Catch and log.
    $this->valueStore->set($data);
    $this->data = $data;
  }

  /**
   * Writes cookie data into the jar.
   *
   * @param array $data
   *   Cookie data.
   */
  private function import(array $data): void {
    foreach ($data as $cookie_data) {
      $this->setCookie(new SetCookie($cookie_data));
    }
  }

  /**
   * Exports cookie data from the jar.
   *
   * @return array
   *   Cookie data.
   */
  private function export(): array {
    $data = [];
    /** @var \GuzzleHttp\Cookie\SetCookie $cookie */
    foreach ($this as $cookie) {
      if (CookieJar::shouldPersist($cookie, $this->storeSessionCookies)) {
        $data[] = $cookie->toArray();
      }
    }
    return $data;
  }

}
