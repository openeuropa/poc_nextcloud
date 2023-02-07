<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;

/**
 * Fallback extra field with empty output.
 */
class EmptyExtraField extends ExtraFieldDisplayBase {

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $entity) {
    return [];
  }

}
