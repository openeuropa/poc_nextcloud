<?php

/**
 * @file
 * Template for the first part of a markdown file.
 *
 * @var string $title
 *   Human version of the file name.
 * @var string $php
 *   Original php snippet.
 */

declare(strict_types = 1);

?>
## <?php echo $title . "\n" ?>

Code to execute:

```php
<?php echo $php . "\n" ?>
```
