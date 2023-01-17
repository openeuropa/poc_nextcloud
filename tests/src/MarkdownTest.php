<?php

declare(strict_types = 1);

namespace Drupal\Tests\poc_nextcloud;

use Drupal\Tests\poc_nextcloud\Tools\TestUtil;
use PHPUnit\Framework\TestCase;

/**
 * Test that uses markdown files as fixtures.
 *
 * The fixtures will be automatically updated, if env var UPDATE_TESTS is
 * true-ish.
 *
 * The benefit of markdown is that they contain code snippets of different
 * languages, and all of them will be syntax-colored by an IDE or file viewer.
 *
 * This class is adapted from elsewhere, and currently contains more
 * functionality than really needed for this project.
 */
class MarkdownTest extends TestCase {

  /**
   * Runs the main test case for a specific markdown file.
   *
   * @param string $dirname
   *   Directory with markdown files.
   * @param string $markdownFileName
   *   Name of a specific markdown file.
   * @param string[] $snippetFileNames
   *   File names of template snippets.
   *
   * @dataProvider markdownProvider
   *
   * @SuppressWarnings(PHPMD.NPathComplexity)
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function testMarkdownFile(string $dirname, string $markdownFileName, array $snippetFileNames): void {
    $dir = $this->getFixturesDir() . '/' . $dirname;
    $markdownFile = $dir . '/' . $markdownFileName;
    $markdownFileContent = file_get_contents($markdownFile);
    $parts = preg_split(
      '@^```(\w*)\n(.*?)\n```$@sm',
      $markdownFileContent,
      -1,
      PREG_SPLIT_DELIM_CAPTURE,
    );
    $partss = [[], [], []];
    foreach ($parts as $i => $part) {
      $partss[$i % 3][] = $part;
    }
    [$texts, $types, $snippets] = $partss;
    if (!isset($parts[2])) {
      throw new \Exception(sprintf('No original code found in %s.', $markdownFile));
    }
    $actualMarkdown = '';
    foreach ($snippetFileNames as $snippetFileName) {
      $snippetFile = $dir . '/' . $snippetFileName;
      if (!is_file($snippetFile)) {
        throw new \Exception(sprintf(
          'Missing template file %s.',
          $snippetFile,
        ));
      }
      $actualMarkdown .= TestUtil::includeTemplateFile($snippetFile, [
        'file' => $markdownFile,
        'content' => $markdownFileContent,
        'title' => ucfirst(str_replace('-', ' ', basename($markdownFileName, '.md'))),
        'php' => $parts[2],
        'first' => $parts[2],
        'types' => $types,
        'texts' => $texts,
        'snippets' => $snippets,
      ]);
    }
    TestUtil::assertFileContents(
      $markdownFile,
      $actualMarkdown,
    );
  }

  /**
   * Data provider for the self-updating test.
   *
   * @return \Iterator
   *   Parameter combinations for self-updating test.
   */
  public function markdownProvider(): \Iterator {
    foreach ($this->getDirNames() as $dirname) {
      $markdownFileNames = $this->getMatchingFileNames($dirname, '@^[^_\.].*\.md$@');
      $snippetFileNames = $this->getMatchingFileNames($dirname, '@^_snippet\..+\.md\.php$@');
      if (!$snippetFileNames) {
        continue;
      }
      foreach ($markdownFileNames as $markdownFileName) {
        yield $dirname . '/' . $markdownFileName => [
          $dirname,
          $markdownFileName,
          $snippetFileNames,
        ];
      }
    }
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
  private function getMatchingFileNames(string $dirname, string $pattern): array {
    $names = [];
    foreach (scandir($this->getFixturesDir() . '/' . $dirname) as $candidate) {
      if (preg_match($pattern, $candidate)) {
        $names[] = $candidate;
      }
    }
    sort($names);
    return $names;
  }

  /**
   * Gets directory names within the fixtures dir.
   *
   * @return string[]
   *   Directory names.
   */
  private function getDirNames(): array {
    $fixturesDir = $this->getFixturesDir();
    $names = [];
    foreach (scandir($this->getFixturesDir()) as $candidate) {
      if (($candidate[0] ?? NULL) === '.') {
        continue;
      }
      if (!is_dir($fixturesDir . '/' . $candidate)) {
        continue;
      }
      $names[] = $candidate;
    }
    return $names;
  }

  /**
   * Gets the fixtures directory.
   *
   * @return string
   *   Fixtures directory.
   */
  private function getFixturesDir(): string {
    return dirname(__DIR__) . '/fixtures';
  }

}
