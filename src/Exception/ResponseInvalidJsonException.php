<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Exception;

/**
 * A response to a Nextcloud API request returned invalid json.
 *
 * Probably it is just HTMl instead of json.
 */
class ResponseInvalidJsonException extends NextcloudApiException {

}
