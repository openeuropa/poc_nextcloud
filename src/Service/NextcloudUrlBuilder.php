<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Service to build front-end urls from Drupal to the Nextcloud instance.
 *
 * Wrapping this in a service allows to autowire respective arguments, and frees
 * other constructors from having to deal with configuration directly.
 */
class NextcloudUrlBuilder {

  use StringTranslationTrait;

  /**
   * Constructor.
   *
   * @param string|null $nextcloudWebUrl
   *   Nextcloud url.
   */
  public function __construct(
    private ?string $nextcloudWebUrl,
  ) {}

  /**
   * Static factory.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   *
   * @return self
   *   New instance.
   */
  public static function create(
    ConfigFactoryInterface $configFactory,
  ): self {
    $config = $configFactory->get('poc_nextcloud.settings');
    // The web url can be different from the API url.
    $url = $config->get('nextcloud_web_url')
      ?: $config->get('nextcloud_url');
    return new self($url);
  }

  /**
   * Builds a front-end url to Nextcloud.
   *
   * @param string $path
   *   Path.
   * @param array $query
   *   Query.
   *
   * @return \Drupal\Core\Url
   *   The url object.
   */
  public function url(string $path = '', array $query = []): Url {
    return Url::fromUri($this->nextcloudWebUrl . $path, [
      'query' => $query,
      'attributes' => ['target' => '_blank'],
    ]);
  }

}
