<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Response;

use Drupal\poc_nextcloud\DataUtil;
use Drupal\poc_nextcloud\Exception\FailureResponseException;
use Drupal\poc_nextcloud\Exception\UnexpectedResponseDataException;

/**
 * Value object for a response from the Nextcloud API.
 *
 * Responses from most Nextcloud APIs follow the "OpenCloudMesh specification".
 * See https://lukasreschke.github.io/OpenCloudMeshSpecification/
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
   * Creates a new instance from response data.
   *
   * @param array $data
   *   Response data (parsed json).
   *
   * @return self
   *   New instance.
   *
   * @throws \Drupal\poc_nextcloud\Exception\UnexpectedResponseDataException
   */
  public static function fromResponseData(array $data): self {
    try {
      $meta = $data['ocs']['meta'];
      return new self(
        $meta['status'],
        DataUtil::toIntIfPossible($meta['statuscode']),
        $meta['message'],
        DataUtil::toIntIfPossible($meta['totalitems'] ?? NULL),
        DataUtil::toIntIfPossible($meta['itemsperpage'] ?? NULL),
        $data['ocs']['data'],
      );
    }
    catch (\Throwable $e) {
      throw new UnexpectedResponseDataException(sprintf(
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
   * This allows for uninterrupted method chaining.
   *
   * @return $this
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   This is a failure response.
   *   The exception contains the parts of information from the response object
   *   that are relevant in case of failure.
   */
  public function throwIfFailure(): static {
    if ($this->isFailure()) {
      throw new FailureResponseException($this->statuscode, $this->message);
    }
    return $this;
  }

  /**
   * Returns null if data has a specific value.
   *
   * This allows for uninterrupted method chaining with `?->`.
   *
   * @param mixed $data
   *   Value for which to return NULL.
   *
   * @return $this|null
   *   NULL or the response object.
   */
  public function nullIfData(mixed $data): ?static {
    return $this->data === $data ? NULL : $this;
  }

  /**
   * Returns null for a given status code.
   *
   * This allows for uninterrupted method chaining with `?->`.
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
