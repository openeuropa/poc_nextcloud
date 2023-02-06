<?php

namespace Drupal\poc_nextcloud\CookieJar;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;

/**
 * Base class for cookie jar with persistence.
 *
 * Some of the private methods might become public in future versions.
 *
 * @see \GuzzleHttp\Cookie\FileCookieJar
 */
abstract class PersistentCookieJarBase extends CookieJar {

  /**
   * Constructor.
   *
   * This cookie jar always starts empty, it must be initialized first.
   *
   * @param bool $storeSessionCookies
   *   Whether to store session cookies.
   * @param bool $strictMode
   *   TRUE to throw exceptions when invalid cookies are added.
   */
  public function __construct(
    private bool $storeSessionCookies = TRUE,
    bool $strictMode = TRUE,
  ) {
    parent::__construct($strictMode);
    $this->load();
  }

  /**
   * Destructor.
   */
  public function __destruct() {
    $this->save();
  }

  /**
   * Loads cookies from storage into the jar.
   */
  private function load(): void {
    $data = $this->loadData();
    $this->import($data);
  }

  /**
   * Saves cookies from the jar into storage.
   */
  private function save(): void {
    $data = $this->export();
    $this->saveData($data);
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

  /**
   * Loads cookie data from storage.
   *
   * @return array
   *   Cookie data.
   */
  abstract protected function loadData(): array;

  /**
   * Writes cookie data to storage.
   *
   * @param array $data
   *   Cookie data.
   */
  abstract protected function saveData(array $data): void;

}
