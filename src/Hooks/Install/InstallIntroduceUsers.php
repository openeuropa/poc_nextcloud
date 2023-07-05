<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Hooks\Install;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\hux\Attribute\Hook;
use Drupal\poc_nextcloud\Service\IteratingEntityLoader;

/**
 * Install hook to initialize tracking tables based on existing users.
 */
class InstallIntroduceUsers {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Service\IteratingEntityLoader $iteratingEntityLoader
   *   Iterating entity loader.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler.
   */
  public function __construct(
    private IteratingEntityLoader $iteratingEntityLoader,
    private ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_install().
   */
  #[Hook('install')]
  public function install(): void {
    $module = explode('\\', static::class, 3)[1];
    foreach ($this->iteratingEntityLoader->forType('user') as $user) {
      $this->moduleHandler->invoke($module, 'user_install', [$user]);
    }
  }

}
