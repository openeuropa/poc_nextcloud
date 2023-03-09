<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Exception;

/**
 * The API request failed.
 *
 * Possible reasons:
 *   - Values were invalid.
 *   - An id already exists.
 *   - Connection is not properly configured.
 *   - Nextcloud is behaving differently than this module expects.
 */
class NextcloudApiException extends \Exception {

}
