<?php

declare(strict_types = 1);

namespace Drupal\Tests\poc_nextcloud;

use Drupal\Tests\poc_nextcloud\Tools\TestUtil;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

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
   *
   * @dataProvider markdownProvider
   *
   * @SuppressWarnings(PHPMD.NPathComplexity)
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function testMarkdownFile(string $dirname, string $markdownFileName): void {
    $snippetFileNames = $this->getSnippetFileNames($dirname);
    if (!preg_match('@^(0\.|)(.+)\.md$@', $markdownFileName, $m)) {
      Assert::fail('File name does not match.');
    }
    [, $fail, $name] = $m;
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
    $hasFailingSnippets = FALSE;
    foreach ($snippetFileNames as $snippetFileName) {
      $snippetFile = $dir . '/' . $snippetFileName;
      if (!is_file($snippetFile)) {
        throw new \Exception(sprintf(
          'Missing template file %s.',
          $snippetFile,
        ));
      }
      try {
        $actualMarkdown .= TestUtil::includeTemplateFile($snippetFile, [
          'file' => $markdownFile,
          'content' => $markdownFileContent,
          'title' => ucfirst(str_replace('-', ' ', basename($name, '.md'))),
          'php' => $parts[2],
          'first' => $parts[2],
          'types' => $types,
          'texts' => $texts,
          'snippets' => $snippets,
          'fail' => !!$fail,
        ]);
      }
      catch (AssertionFailedError $e) {
        throw $e;
      }
      catch (\Throwable $e) {
        if (!$fail) {
          throw $e;
        }
        $hasFailingSnippets = TRUE;
        $yml = Yaml::dump([
          'class' => get_class($e),
          'message' => $this->sanitizeExceptionMessage($e->getMessage()),
        ]);
        $actualMarkdown .= <<<EOT

Exception:

```yml
$yml
```

EOT;
      }
    }
    if ($fail && !$hasFailingSnippets) {
      self::fail('Expected at least one snippet to fail.');
    }
    TestUtil::assertFileContents(
      $markdownFile,
      $actualMarkdown,
    );
  }

  /**
   * Removes system-specific information from exception messages.
   *
   * @param string $message
   *   The exception message.
   *
   * @return string
   *   Cleaned-up exception message.
   */
  private function sanitizeExceptionMessage(string $message): string {
    $message = strtr($message, [
      realpath(dirname(__DIR__, 2)) => '<project root>',
      dirname(__DIR__, 2) => '<project root>',
    ]);
    $message = preg_replace('@, called in .* eval\(\)\'d code on line (\d+)$@', ', called in [..] eval()\'d code on line $1', $message);
    return $message;
  }

  /**
   * Data provider for the self-updating test.
   *
   * @return \Iterator
   *   Parameter combinations for self-updating test.
   */
  public function markdownProvider(): \Iterator {
    foreach ($this->getDirNames() as $dirname) {
      $markdownFileNames = $this->getMarkdownFileNames($dirname);
      $snippetFileNames = $this->getSnippetFileNames($dirname);
      if (!$snippetFileNames) {
        continue;
      }
      foreach ($markdownFileNames as $markdownFileName) {
        yield $dirname . '/' . $markdownFileName => [
          $dirname,
          $markdownFileName,
        ];
      }
    }
  }

  /**
   * Runs a complementary test for a markdown file.
   *
   * @param string $dirname
   *   Directory with markdown files.
   * @param string $testCaseFileName
   *   Name of the complementary test.
   * @param string $markdownFileName
   *   Name of the markdown file.
   *
   * @dataProvider otherProvider
   */
  public function testOther(string $dirname, string $testCaseFileName, string $markdownFileName): void {
    $dir = $this->getFixturesDir() . '/' . $dirname;
    $markdownFile = $dir . '/' . $markdownFileName;
    $originalMarkdown = file_get_contents($markdownFile);
    $parts = preg_split(
      '@^```(\w*)\n(.*?)\n```$@sm',
      $originalMarkdown,
      -1,
      PREG_SPLIT_DELIM_CAPTURE,
    );
    if (!isset($parts[2])) {
      throw new \Exception(sprintf('No original code found in %s.', $markdownFile));
    }
    $testCaseFile = $dir . '/' . $testCaseFileName;
    if (!is_file($testCaseFile)) {
      throw new \Exception(sprintf(
        'Missing template file %s.',
        $testCaseFile,
      ));
    }
    $partss = [];
    foreach ($parts as $i => $part) {
      $partss[$i % 3][] = $part;
    }
    [$texts, $types, $snippets] = $partss;
    TestUtil::includeFile($testCaseFile, [
      'php' => $parts[2],
      'first' => $parts[2],
      'title' => ucfirst(str_replace('-', ' ', basename($markdownFileName, '.md'))),
      'types' => $types,
      'texts' => $texts,
      'snippets' => $snippets,
      'name' => $testCaseFileName,
    ]);
  }

  /**
   * Data provider for testOther().
   *
   * @return \Iterator
   *   Argument combinations.
   */
  public function otherProvider(): \Iterator {
    foreach ($this->getDirNames() as $dirname) {
      $markdownFileNames = $this->getMarkdownFileNames($dirname);
      foreach ($this->getMatchingFileNames($dirname, '@^_[\w\-]+\.php$@') as $testCaseFileName) {
        foreach ($markdownFileNames as $markdownFileName) {
          if (str_starts_with($markdownFileName, '0.')) {
            continue;
          }
          yield $dirname . '/' . $testCaseFileName . ': ' . $markdownFileName => [
            $dirname,
            $testCaseFileName,
            $markdownFileName,
          ];
        }
      }
    }
  }

  /**
   * Gets snippets that form a template for updating the markdown file.
   *
   * @param string $dirname
   *   Directory name.
   *
   * @return string[]
   *   The file names.
   */
  private function getSnippetFileNames(string $dirname): array {
    return $this->getMatchingFileNames($dirname, '@^_snippet\..+\.md\.php$@');
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
   * Gets the names of markdown files inside a directory.
   *
   * @param string $dirname
   *   Directory name.
   *
   * @return string[]
   *   File names.
   */
  private function getMarkdownFileNames(string $dirname): array {
    return $this->getMatchingFileNames($dirname, '@^[^_\.].*\.md$@');
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
