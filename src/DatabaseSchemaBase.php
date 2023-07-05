<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud;

use Drupal\hux\Attribute\Hook;
use Drupal\poc_nextcloud\Database\HookSchemaRegistry;

/**
 * Base class for hook_schema() implementations.
 *
 * This is outside of /Hooks/, because Hux does not currently skip base classes.
 * See https://www.drupal.org/project/hux/issues/3367717.
 */
abstract class DatabaseSchemaBase {

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Database\HookSchemaRegistry $hookSchemaRegistry
   *   Registry of database schema providers.
   */
  final public function __construct(
    private HookSchemaRegistry $hookSchemaRegistry,
  ) {}

  /**
   * Implements hook_schema().
   */
  #[Hook('schema')]
  final public function schema(): array {
    $module = explode('\\', static::class, 3)[1];
    return $this->hookSchemaRegistry->getModuleSchema($module);
  }

}
