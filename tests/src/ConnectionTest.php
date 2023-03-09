<?php

declare(strict_types = 1);

namespace Drupal\Tests\poc_nextcloud;

use Drupal\Tests\poc_nextcloud\Tools\TrafficNoiseRemover;
use Drupal\Tests\poc_nextcloud\Tools\TestConnection;
use Drupal\Tests\poc_nextcloud\Tools\TestUtil;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Test for API requests.
 */
class ConnectionTest extends TestCase {

  /**
   * Runs the test from a php file found in the fixtures dir.
   *
   * @param string $filename
   *   Name of a php file in the fixtures directory for this test.
   *
   * @dataProvider provider()
   */
  public function test(string $filename): void {
    $name = basename($filename, '.php');
    $php_file = $this->getFixturesDir() . '/' . $filename;
    $traffic_file = $this->getFixturesDir() . '/traffic/' . $name . '.traffic.yml';
    $traffic = is_file($traffic_file)
      ? Yaml::parseFile($traffic_file, Yaml::PARSE_CUSTOM_TAGS)
      : [];
    $connection = TestConnection::fromRecordedTrafficReference($traffic);
    $function = include $php_file;
    self::assertInstanceOf(\Closure::class, $function);
    $function($connection);
    $noiseRemover = TrafficNoiseRemover::createForTrafficItems();
    $traffic = $noiseRemover->removeNoise($traffic);
    TestUtil::assertFileContents(
      $traffic_file,
      Yaml::dump($traffic, 99, 2),
    );
  }

  /**
   * Detects or cleans up leftover traffic files.
   */
  public function testLeftoverTrafficFiles(): void {
    $php_files = $this->findMatchingFileNames(
      '',
      '@^(.*)\.php$@',
    );
    $traffic_files = $this->findMatchingFileNames(
      'traffic',
      '@^(.*)\.traffic\.yml$@',
    );
    $leftover_traffic_files = array_diff_key($traffic_files, $php_files);
    if (TestUtil::updateTestsEnabled()) {
      foreach ($leftover_traffic_files as $file) {
        unlink($this->getFixturesDir() . '/traffic/' . $file);
      }
    }
    // Make the test fail even if leftover files were just deleted.
    self::assertSame([], $leftover_traffic_files);
  }

  /**
   * Data provider.
   *
   * @return \Iterator
   *   Argument combinations.
   */
  public function provider(): \Iterator {
    foreach ($this->findMatchingFileNames('', '@^.*\.php$@') as $filename) {
      yield 'connection/' . $filename => [$filename];
    }
  }

  /**
   * Gets the fixtures directory.
   *
   * @return string
   *   Fixtures directory.
   */
  private function getFixturesDir(): string {
    return dirname(__DIR__) . '/fixtures/connection';
  }

  /**
   * Gets file names that match a regular expression.
   *
   * @param string $dirname
   *   Directory.
   * @param string $pattern
   *   Regex pattern.
   *
   * @return string[]
   *   File names.
   */
  private function findMatchingFileNames(string $dirname, string $pattern): array {
    $names = [];
    $dir = $this->getFixturesDir() . ($dirname ? '/' . $dirname : '');
    $assoc = FALSE;
    foreach (scandir($dir) as $candidate) {
      // The pattern automatically removes '.' and '..'.
      if (preg_match($pattern, $candidate, $m)) {
        $key = $m[1] ?? NULL;
        if ($key === NULL) {
          $names[] = $candidate;
        }
        else {
          $names[$key] = $candidate;
          $assoc = TRUE;
        }
      }
    }
    if ($assoc) {
      asort($names);
    }
    else {
      sort($names);
    }
    return $names;
  }

}
