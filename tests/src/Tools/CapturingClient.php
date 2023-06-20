<?php

declare(strict_types = 1);

namespace Drupal\Tests\poc_nextcloud\Tools;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Http client that can record and replay responses.
 *
 * The client has two modes:
 * - If a decorated client is provided, it will forward requests to the
 *   decorated client, and record the request and response.
 * - If a decorated client is not provided, it will assert that the request data
 *   is as previously recorded, and return the pre-recorded response data.
 *
 * Request and response data are stored in an array format suitable for yml.
 *
 * @todo This might not be the most elegant or standard way to do this.
 */
class CapturingClient implements ClientInterface {

  /**
   * Index within pre-recorded traffic.
   *
   * @var int
   */
  private int $index = 0;

  /**
   * Constructor.
   *
   * @param \GuzzleHttp\ClientInterface|null $decorated
   *   Decorated client, or NULL.
   *   If this is not null, the client will be in "recording mode".
   *   If it is null, the client will be in "playback mode".
   * @param array[] $traffic
   *   Pre-recorded traffic (playback mode), OR
   *   traffic to be recorded (recording mode).
   *
   * @psalm-param list<array{request: array, response: array}> $traffic
   */
  public function __construct(
    private ?ClientInterface $decorated,
    private array &$traffic,
  ) {
    if ($this->decorated) {
      // Start recording with empty traffic.
      $this->traffic = [];
    }
  }

  /**
   * Creates a new instance based on env variables.
   *
   * @param array[] $traffic
   *   Pre-recorded traffic.
   *
   * @psalm-param list<array{request: array, response: array}> $traffic
   *
   * @return self
   *   New instance.
   */
  public static function create(array &$traffic): self {
    $client = getenv('REAL_CLIENT') ? new Client() : NULL;
    return new self($client, $traffic);
  }

  /**
   * {@inheritdoc}
   */
  public function send(RequestInterface $request, array $options = []) {
    throw new \RuntimeException('Not implemented');
  }

  /**
   * {@inheritdoc}
   */
  public function sendAsync(RequestInterface $request, array $options = []) {
    throw new \RuntimeException('Not implemented');
  }

  /**
   * {@inheritdoc}
   */
  public function request($method, $uri, array $options = []) {
    $request_data = $this->packRequest($method, $uri, $options);
    if (!$this->decorated) {
      // Use pre-recorded request and response.
      $record = $this->readTrafficRecord();
      Assert::assertSame($record['request'], $request_data);
      $response = $this->unpackResponse($record['response']);
      if (empty($record['exception'])) {
        return $response;
      }
      // Recreate the exception.
      $handler = HandlerStack::create();
      $handler->push(fn() => static function (
        RequestInterface $request,
        array $options,
      ) use ($response) {
        throw RequestException::create($request, $response);
      });
      $client = new Client(['handler' => $handler]);
      $client->request($method, $uri, $options);
      throw new \RuntimeException('The client was expected to throw an exception, but it did not.');
    }
    else {
      // Update pre-recorded request and response.
      try {
        $response = $this->decorated->request($method, $uri, $options);
      }
      catch (RequestException $exception) {
        $response = $exception->getResponse();
        if ($response === NULL) {
          throw new \Exception(sprintf('%s exceptions without a response are currently not supported in CapturingClient.', get_class($exception)), 0, $exception);
        }
        $this->writeTrafficRecord([
          'request' => $request_data,
          'response' => $this->packResponse($response),
          'exception' => TRUE,
        ]);
        throw $exception;
      }
      catch (GuzzleException $e) {
        throw new \Exception(sprintf('%s exceptions are currently not supported in CapturingClient.', get_class($e)), 0, $e);
      }
      $this->writeTrafficRecord([
        'request' => $request_data,
        'response' => $this->packResponse($response),
      ]);
      return $response;
    }
  }

  /**
   * Reads a record from the recorded traffic, and increments the counter.
   *
   * @return array
   *   Recorded record.
   */
  private function readTrafficRecord(): array {
    $record = $this->traffic[$this->index] ?? [];
    if ($record === []) {
      Assert::fail('End of pre-recorded traffic reached.');
    }
    ++$this->index;
    return $record;
  }

  /**
   * Writes a traffic record.
   *
   * @param array $record
   *   Record.
   */
  private function writeTrafficRecord(array $record): void {
    $this->traffic[$this->index] = $record;
    ++$this->index;
  }

  /**
   * Converts request data into an array suitable for yml.
   *
   * @param string $method
   *   E.g. 'GET' or 'POST'.
   * @param string $uri
   *   Request url.
   * @param array $options
   *   Request options.
   *
   * @return array
   *   Data suitable for yml.
   */
  private function packRequest(string $method, string $uri, array $options): array {
    $uri = preg_replace('@^http://nextcloud_test@', 'http://nextcloud', $uri);
    unset($options['auth'], $options['debug'], $options['headers']);
    return ['method' => $method, 'uri' => $uri] + $options;
  }

  /**
   * Converts a response object into an array suitable for yml.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   Response object.
   *
   * @return array
   *   Data suitable for yml.
   */
  private function packResponse(ResponseInterface $response): array {
    $response_data = [
      'status' => $response->getStatusCode(),
      'body' => (string) $response->getBody(),
    ];
    try {
      $response_data['data'] = json_decode($response_data['body'], TRUE, JSON_THROW_ON_ERROR);
      unset($response_data['body']);
    }
    catch (\Throwable) {
      // Not valid json.
      // Keep the $response_data['body'], don't set $response_data['data'].
    }
    return $response_data;
  }

  /**
   * Restores a response object based on recorded response data.
   *
   * @param array $response_data
   *   Response data that was previously recorded.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   New response object.
   */
  private function unpackResponse(array $response_data): Response {
    if (isset($response_data['data'])) {
      $response_data['body'] = json_encode($response_data['data']);
    }
    return new Response(
      $response_data['status'] ?? 200,
      // Headers are irrelevant.
      [],
      $response_data['body'] ?? NULL,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function requestAsync($method, $uri, array $options = []) {
    throw new \RuntimeException('Not implemented');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig($option = NULL) {
    throw new \RuntimeException('Not implemented');
  }

}
