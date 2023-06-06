<?php

declare(strict_types=1);

use Drupal\poc_nextcloud_group_folder\GroupFolderConstants;
use Drupal\poc_nextcloud_group_folder\Hooks\EntityBaseFieldInfo\GroupBaseField;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Low-level integrity checks.
 */
class CodeIntegrityTest extends TestCase {

  /**
   * Tests that group permissions are spelled consistently in different places.
   *
   * This does not test any functionality, its only purpose is to detect
   * possible regressions.
   */
  public function testGroupPermissionConstants(): void {
    // Get permission names as declared in yaml.
    $file = dirname(__DIR__, 2) . '/poc_nextcloud_group_folder.group.permissions.yml';
    $data = Yaml::parseFile($file);
    $yaml_perm_names = array_keys($data);
    sort($yaml_perm_names);

    // Get group permission names as in class constants.
    // These are used in code to avoid misspelled string literals.
    $const_perm_names = [
      ...array_values(GroupFolderConstants::PERMISSIONS_MAP),
      ...array_values(GroupBaseField::PERMISSIONS),
    ];
    sort($const_perm_names);

    // Both lists should be identical.
    // This will also detect duplicate values in the const permission names.
    self::assertSame($yaml_perm_names, $const_perm_names);
  }

}
