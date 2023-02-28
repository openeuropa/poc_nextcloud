<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Empty block class to use as a fallback.
 */
class EmptyBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [];
  }

}
