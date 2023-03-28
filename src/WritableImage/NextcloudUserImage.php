<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\WritableImage;

use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;

/**
 * Creates or updates a user.
 *
 * After the image is written, the user will exist, and it will have exactly the
 * values as defined.
 *
 * @todo This is currently not used, consider to remove.
 */
class NextcloudUserImage {

  /**
   * Key changes for edit vs insert.
   */
  const EDIT_KEYS = [
    'displayName' => 'displayname',
  ];

  /**
   * Values for user insert.
   *
   * @var array
   */
  private array $values;

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Endpoint\NxUserEndpoint $userEndpoint
   *   User endpoint.
   * @param string $userId
   *   User id.
   * @param string|null $email
   *   Email address.
   * @param string|null $displayName
   *   Display name.
   */
  public function __construct(
    private NxUserEndpoint $userEndpoint,
    private string $userId,
    ?string $email,
    ?string $displayName,
  ) {
    $this->values = array_diff([
      'email' => $email,
      'displayName' => $displayName,
    ], [NULL]);
  }

  /**
   * Sets the user email.
   *
   * @param string $email
   *   Email.
   */
  public function setEmail(string $email): void {
    $this->values['email'] = $email;
  }

  /**
   * Sets the user display name.
   *
   * @param string $display_name
   *   Display name.
   */
  public function setDisplayName(string $display_name): void {
    $this->values['displayName'] = $display_name;
  }

  /**
   * Writes the image to Nextcloud.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function write(): void {
    $nextcloud_user = $this->userEndpoint->load($this->userId);
    if (!$nextcloud_user) {
      $this->userEndpoint->insertValues(
        ['userid' => $this->userId] + $this->values,
      );
    }
    else {
      foreach ($this->values as $key => $value) {
        $edit_key = self::EDIT_KEYS[$key] ?? $key;
        $this->userEndpoint->setUserField($this->userId, $edit_key, $value);
      }
    }
  }

}
