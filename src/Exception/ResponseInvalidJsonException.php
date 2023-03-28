<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Exception;

/**
 * A response to a Nextcloud API request returned invalid json.
 *
 * Typically this would happen if the API returns a http error response with
 * html.
 */
class ResponseInvalidJsonException extends UnexpectedResponseDataException {

}
