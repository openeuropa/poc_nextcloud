<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Exception;

use Psr\Container\ContainerExceptionInterface;

/**
 * The API is not configured.
 */
class ServiceNotAvailableException extends \Exception implements ContainerExceptionInterface {

}
