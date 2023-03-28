<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Exception;

use Psr\Container\ContainerExceptionInterface;

/**
 * A service is not available, e.g. due to incomplete configuration.
 *
 * Components that would need such services can catch this specific exception to
 * provide a fallback behavior.
 */
class ServiceNotAvailableException extends \Exception implements ContainerExceptionInterface {

}
