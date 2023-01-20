<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Response;

/**
 * Value object for a failed response.
 *
 * @todo Determine if this is really needed.
 */
class FailureResponse {

  /**
   * Constructor.
   *
   * @param int $statusCode
   *   Status code from $response['ocs']['meta']['statuscode'].
   * @param string $message
   *   Message from $response['ocs']['meta']['message'].
   */
  public function __construct(
    private int $statusCode,
    private string $message,
  ) {}

  /**
   * Static factory.
   *
   * @param array $data
   *   The complete parsed json data from a failed request.
   *
   * @return self|null
   *   New instance, or NULL if not a failure.
   */
  public static function fromResponseData(array $data): ?self {
    if (!isset($data['ocs'])) {
      throw new \RuntimeException();
    }
    $ret = self::fromResponseMeta($data['ocs']['meta']);
    return $ret;
  }

  /**
   * Static factory.
   *
   * @param array $meta
   *   The meta sub-array containing the status, statuscode and message.
   *
   * @return self|null
   *   New instance, or NULL if not a failure.
   */
  public static function fromResponseMeta(array $meta): ?self {
    if ($meta['status'] !== 'failure') {
      return NULL;
    }
    return new self(
      $meta['statuscode'],
      $meta['message'],
    );
  }

  /**
   * Gets the status code.
   *
   * @return int
   *   Status code from $response['ocs']['meta']['statuscode'].
   */
  public function getStatusCode(): int {
    return $this->statusCode;
  }

  /**
   * Gets a message describing the problem.
   *
   * @return string
   *   Message from $response['ocs']['meta']['message'].
   */
  public function getMessage(): string {
    return $this->message;
  }

}
