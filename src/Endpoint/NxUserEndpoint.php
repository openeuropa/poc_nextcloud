<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Endpoint;

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\NxEntity\NxUser;

/**
 * Endpoint for Nextcloud users.
 *
 * @template-extends \Drupal\poc_nextcloud\Endpoint\EntityEndpoint<\Drupal\poc_nextcloud\NxEntity\NxUser, string>
 *
 * @see https://docs.nextcloud.com/server/latest/admin_manual/configuration_user/instruction_set_for_users.html
 */
class NxUserEndpoint extends EntityEndpoint {

  /**
   * Creates a new instance.
   *
   * @param \Drupal\poc_nextcloud\Connection\ApiConnectionInterface $connection
   *   Connection.
   *
   * @return self
   *   New instance.
   */
  public static function fromConnection(
    ApiConnectionInterface $connection,
  ): self {
    return new self(
      $connection,
      'ocs/v1.php/cloud/users',
      NxUser::class,
      [NxUser::class, 'fromResponseData'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function loadIds(): array {
    return parent::loadIds()['users'];
  }

  /**
   * {@inheritdoc}
   *
   * phpcs:disable
   */
  public function load(int|string $id): NxUser|null {
    // phpcs:enable
    // This is overridden because the return type changes.
    return parent::load($id);
  }

}
