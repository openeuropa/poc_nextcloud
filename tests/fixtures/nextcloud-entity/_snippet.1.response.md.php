<?php

/**
 * @file
 * Template for the second part of a markdown file.
 *
 * @var string $php
 *   First code snippet found in the file.
 * @var string[] $snippets
 *   Code snippets from the existing markdown file.
 * @var string $file
 *   The markdown file.
 * @var string $content
 *   Content of the markdown file.
 */

declare(strict_types = 1);

use Drupal\Tests\poc_nextcloud\Tools\CapturingClient;
use Drupal\Tests\poc_nextcloud\Tools\TestConnection;
use Drupal\Tests\poc_nextcloud\Tools\TestUtil;
use Drupal\Tests\poc_nextcloud\Tools\ValueExporter;
use Symfony\Component\Yaml\Yaml;

// If no real nextcloud instance is set up, work with the pre-recorded traffic.
$traffic = Yaml::parse($snippets[2] ?? '{}');

// Make this variable available in the.
$client = CapturingClient::create($traffic);

$connection = TestConnection::fromClient($client);

if (substr_count($content, '<?') !== 1) {
  throw new \Exception('Markdown file must have exactly one php portion.');
}
if (!preg_match('@^[ \n]*<\?php.*\?>[ \n]*$@sm', $php)) {
  throw new \Exception('The php code must be enclosed in "```php" / "```".');
}

$result = TestUtil::includeFile($file, [
  'client' => $client,
  'connection' => $connection,
]);

$result = ValueExporter::export($result);

// Stabilize auto-increment ids.
$idMap = [];
$replacementId = 1000001;
foreach ($traffic as &$record) {
  if (($record['response']['data']['ocs']['status'] ?? NULL) !== 'failure') {

  }
}

?>

Result:

```yml
<?php echo Yaml::dump($result, 99, 2) . "\n" ?>
```

Recorded traffic:

```yml
<?php echo Yaml::dump($traffic, 99, 2) . "\n" ?>
```
