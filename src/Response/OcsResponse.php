<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Response;

use Drupal\poc_nextcloud\Exception\FailureResponseException;
use Drupal\poc_nextcloud\Exception\MalformedDataException;

/**
 * Value object for a response from the Nextcloud API.
 */
class OcsResponse {

  /**
   * Constructor.
   *
   * This is private, so that the signature can be changed without breaking BC.
   *
   * @param string $status
   *   Value from $response['ocs']['meta']['status'].
   *   Typically one of 'ok' or 'failure'.
   * @param int $statuscode
   *   Value from $response['ocs']['meta']['statuscode'].
   *   E.g. 200.
   * @param string $message
   *   Value from $response['ocs']['meta']['message'].
   *   Either 'OK', or a failure message.
   * @param int|null $totalitems
   *   Value from $response['ocs']['meta']['totalitems'].
   *   This seems to be always empty.
   * @param int|null $itemsperpage
   *   Value from $response['ocs']['meta']['itemsperpage'].
   *   This seems to be always empty.
   * @param mixed $data
   *   Data from $response['ocs']['data'].
   */
  private function __construct(
    private string $status,
    private int $statuscode,
    private string $message,
    private ?int $totalitems,
    private ?int $itemsperpage,
    private mixed $data,
  ) {}

  /**
   * Converts stringified integers to true integers.
   *
   * Also converts '' to NULL.
   *
   * @param mixed $value
   *   A value which could be a stringified integer, e.g. "6".
   *
   * @return mixed
   *   The converted value, e.g. 6 for "6".
   *   If the value was not a stringified integer, the original value is
   *   returned.
   */
  private static function convertIntLikeString(mixed $value): mixed {
    if (is_string($value)) {
      if ($value === '') {
        return NULL;
      }
      if ((string) (int) $value === $value) {
        return (int) $value;
      }
    }
    // Return the original value.
    // It is better if calling code does the validation, to have better context
    // for failure messages.
    return $value;
  }

  /**
   * Creates a new instance from response data.
   *
   * @param array $data
   *   Response data (parsed json).
   *
   * @return self
   *   New instance.
   *
   * @throws \Drupal\poc_nextcloud\Exception\MalformedDataException
   */
  public static function fromResponseData(array $data): self {
    try {
      $meta = $data['ocs']['meta'];
      return new self(
        $meta['status'],
        self::convertIntLikeString($meta['statuscode']),
        $meta['message'],
        self::convertIntLikeString($meta['totalitems'] ?? NULL),
        self::convertIntLikeString($meta['itemsperpage'] ?? NULL),
        $data['ocs']['data'],
      );
    }
    catch (\Throwable $e) {
      throw new MalformedDataException(sprintf(
        'Unexpected response data. Message: %s.',
        $e->getMessage(),
      ), 0, $e);
    }
  }

  /**
   * Gets whether the response is marked as failure.
   *
   * @return bool
   *   TRUE for failure response.
   */
  public function isFailure(): bool {
    return $this->status === 'failure';
  }

  /**
   * Throws an exception if this is a failure response.
   *
   * @return $this
   *
   * @throws \Drupal\poc_nextcloud\Exception\FailureResponseException
   *   This is a failure response.
   */
  public function throwIfFailure(): static {
    if ($this->isFailure()) {
      throw new FailureResponseException($this->statuscode, $this->message);
    }
    return $this;
  }

  /**
   * Returns null for a given status code.
   *
   * @param int $statucode
   *   Status code.
   *
   * @return $this|null
   *   NULL or the response object.
   */
  public function nullIfStatusCode(int $statucode): ?static {
    return $this->statuscode === $statucode ? NULL : $this;
  }

  /**
   * Gets a failure response if this is a failure.
   *
   * @return \Drupal\poc_nextcloud\Response\FailureResponse|null
   *   Failure response, or NULL on success.
   */
  public function getFailureResponse(): ?FailureResponse {
    if ($this->isFailure()) {
      return new FailureResponse($this->statuscode, $this->message);
    }
    return NULL;
  }

  /**
   * Gets the status code.
   *
   * @return int
   *   Status code from $['ocs']['meta']['statuscode'].
   *   E.g. '200'.
   */
  public function getStatusCode(): int {
    return $this->statuscode;
  }

  /**
   * Gets the message sent with the response.
   *
   * @return string
   *   Message from $['ocs']['meta']['message'].
   *   Either 'OK', or a failure message.
   */
  public function getMessage(): string {
    return $this->message;
  }

  /**
   * Gets the data sent with the response.
   *
   * @return mixed
   *   Data from $['ocs']['data'].
   */
  public function getData(): mixed {
    return $this->data;
  }

  /**
   * Gets the total item count, if provided. Don't use this.
   *
   * @return int|null
   *   This seems to be always null.
   */
  public function getTotalitems(): ?int {
    return $this->totalitems;
  }

  /**
   * Gets items per page, if provided. Don't use this.
   *
   * @return int|null
   *   This seems to be always null.
   */
  public function getItemsperpage(): ?int {
    return $this->itemsperpage;
  }

}
