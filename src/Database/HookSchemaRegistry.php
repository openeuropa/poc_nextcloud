<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Database;

/**
 * Collector for hook_schema() data.
 *
 * Schemas are collected and lazy-evaluated only for the module for which the
 * schema is being requested.
 *
 * This is needed until this issue is solved in Hux:
 * https://www.drupal.org/project/hux/issues/3365770.
 */
class HookSchemaRegistry {

  /**
   * Schema providers by module.
   *
   * @var \Drupal\poc_nextcloud\Database\SchemaProviderInterface[][]
   */
  private array $providers = [];

  /**
   * Registers a schema part for a module.
   *
   * @param \Drupal\poc_nextcloud\Database\SchemaProviderInterface $provider
   *   Schema provider.
   * @param string $id
   *   Service id of the schema provider.
   * @param string|null $module
   *   (optional) Module of the schema provider.
   *   If not given, this will be determined from the service id.
   *
   * @throws \Exception
   */
  public function addProvider(SchemaProviderInterface $provider, string $id, string $module = NULL): void {
    if ($module === NULL) {
      if (preg_match('@^Drupal\\\\(\w+)\\\\@', $id, $m)) {
        $module = $m[1];
      }
      elseif (preg_match('@^(\w+)\.\w+@', $id, $m)) {
        $module = $m[1];
      }
      else {
        throw new \Exception("Cannot determine the module name for the schema provider '$id'.");
      }
    }
    $this->providers[$module][] = $provider;
  }

  /**
   * Gets the schema for a specific module.
   *
   * @param string $module
   *   Module name.
   *
   * @return array
   *   Schema for this module.
   */
  public function getModuleSchema(string $module): array {
    $schema = [];
    foreach ($this->providers[$module] ?? [] as $provider) {
      $schema += $provider->getSchema();
    }
    return $schema;
  }

}
