<?php

declare(strict_types = 1);

namespace Drupal\Tests\poc_nextcloud\Tools;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;

/**
 * Static methods used in tests.
 */
class TestUtil {

  /**
   * Checks an env flag that determines if fixture files should be updated.
   *
   * @return bool
   *   If TRUE, fixture files will be overwritten.
   */
  public static function updateTestsEnabled(): bool {
    return (bool) getenv('UPDATE_TESTS');
  }

  /**
   * Asserts file contents, and conditionally updates the file.
   *
   * @param string $file
   *   File with expected content.
   *   The file will be updated if UPDATE_TESTS env variable is set.
   * @param string $content_actual
   *   Actual content.
   */
  public static function assertFileContents(string $file, string $content_actual): void {
    try {
      if (!is_file($file)) {
        Assert::fail("File '$file' is missing.");
      }
      $content_expected = file_get_contents($file);
      Assert::assertSame($content_expected, $content_actual);
    }
    catch (AssertionFailedError $e) {
      if (self::updateTestsEnabled()) {
        file_put_contents($file, $content_actual);
      }
      throw $e;
    }
  }

  /**
   * Includes a file with variables, and captures and returns the output.
   *
   * @param string $file
   *   Template file.
   * @param array $variables
   *   Variables to be made available in the template.
   *
   * @return string
   *   Output from the template.
   *
   * @throws \Throwable
   *   Anything that might go wrong in the included file.
   */
  public static function includeTemplateFile(string $file, array $variables): string {
    return self::doIncludeFile($file, $variables)[1];
  }

  /**
   * Includes a php file with variables, and gets the return value.
   *
   * Output is captured and discarded.
   *
   * @throws \Throwable
   *   Anything that might go wrong in the included file.
   *
   * @return mixed
   *   Return value from the included file.
   */
  public static function includeFile(string $file, array $variables): mixed {
    return self::doIncludeFile($file, $variables)[0];
  }

  /**
   * Includes a php file.
   *
   * @return array
   *   Return value and captured output.
   *
   * @psalm-return array{mixed,string}
   */
  private static function doIncludeFile(): array {
    // Use func_get_args() to prevent leaking of unrelated variables.
    extract(func_get_arg(1));
    ob_start();
    try {
      $ret = include func_get_arg(0);
      return [$ret, ob_get_contents()];
    }
    finally {
      ob_end_clean();
    }
  }

}
