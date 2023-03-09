<?php

namespace Drupal\poc_nextcloud\CookieJar;

use Drupal\Core\State\StateInterface;

/**
 * Persists cookies into Drupal state storage.
 *
 * @see \GuzzleHttp\Cookie\FileCookieJar
 */
class DrupalStateCookieJar extends PersistentCookieJarBase {

  /**
   * Constructor.
   *
   * This cookie jar always starts empty, it must be initialized first.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   Drupal state system.
   * @param string $stateKey
   *   Key for Drupal state system.
   * @param bool $storeSessionCookies
   *   Whether to store session cookies.
   * @param bool $strictMode
   *   TRUE to throw exceptions when invalid cookies are added.
   */
  public function __construct(
    private StateInterface $state,
    private string $stateKey,
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
    return $this->state->get($this->stateKey);
  }

  /**
   * {@inheritdoc}
   */
  protected function saveData(array $data): void {
    $this->state->set($this->stateKey, $data);
  }

}
