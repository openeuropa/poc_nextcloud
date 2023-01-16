<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Endpoint;

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\NxEntity\NxGroupFolder;

/**
 * Endpoint for Nextcloud users.
 *
 * @template-extends \Drupal\poc_nextcloud\Endpoint\EntityEndpoint<\Drupal\poc_nextcloud\NxEntity\NxUser, string>
 */
class NxGroupFolderEndpoint extends EntityEndpoint {

  /**
   * Creates a new instance.
   *
   * This is not meant to be called directly, instead it should be registered as
   * a factory for a service.
   *
   * @param \Drupal\poc_nextcloud\Connection\ApiConnectionInterface $connection
   *   Connection.
   *
   * @return \Drupal\poc_nextcloud\Endpoint\EntityEndpoint
   *   Endpoint to read and write users in nextcloud.
   */
  public static function fromConnection(ApiConnectionInterface $connection): EntityEndpoint {
    return new self(
      $connection,
      'apps/groupfolders/folders',
      [NxGroupFolder::class, 'fromResponseData'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function loadIds(): array {
    return array_keys(parent::loadIds());
  }

  /**
   * {@inheritdoc}
   *
   * phpcs:disable
   */
  public function load(int|string $id): NxGroupFolder|null {
    // phpcs:enable
    // This is overridden because the return type changes.
    return parent::load($id);
  }

}
