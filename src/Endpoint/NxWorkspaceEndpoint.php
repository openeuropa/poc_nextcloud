<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Endpoint;

use Drupal\poc_nextcloud\Connection\ApiConnectionInterface;
use Drupal\poc_nextcloud\Exception\NextcloudApiException;
use Drupal\poc_nextcloud\Exception\UnexpectedResponseDataException;
use Drupal\poc_nextcloud\NxEntity\NxWorkspace;

/**
 * Endpoint for Nextcloud 'workspace' app.
 *
 * See
 * - https://github.com/arawa/workspace/blob/main/appinfo/routes.php
 * - https://github.com/arawa/workspace/issues/678
 */
class NxWorkspaceEndpoint {

  /**
   * The connection with url of the endpoint.
   *
   * @var \Drupal\poc_nextcloud\Connection\ApiConnectionInterface
   */
  private ApiConnectionInterface $connection;

  /**
   * Constructor.
   *
   * @param \Drupal\poc_nextcloud\Connection\ApiConnectionInterface $connection
   *   Connection.
   */
  public function __construct(ApiConnectionInterface $connection) {
    $this->connection = $connection->withPath('apps/workspace');
  }

  /**
   * Makes a request to create a workspace.
   *
   * @param string $workspace_name
   *   Workspace name.
   * @param int $group_folder_id
   *   Group folder id that will be linked to the workspace.
   *
   * @return \Drupal\poc_nextcloud\NxEntity\NxWorkspace
   *   Workspace object that was just created.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   Failed to create the workspace.
   */
  public function insertWorkspace(string $workspace_name, int $group_folder_id): NxWorkspace {
    $data = $this->connection->requestJson('POST', '/spaces', [
      'spaceName' => $workspace_name,
      'folderId' => $group_folder_id,
    ]);
    // Some heuristics for a failing request.
    if (!is_array($data)
      || !isset($data['id_space'])
      || !isset($data['statuscode'])
      || $data['statuscode'] !== 201
    ) {
      throw new UnexpectedResponseDataException(sprintf(
        "Unexpected response data from attempt to create workspace '%s' for group folder %d.",
        $workspace_name,
        $group_folder_id,
      ));
    }
    return new NxWorkspace(
      $data['id_space'],
      $data['folder_id'],
      $data['space_name'],
      $data['color'],
    );
  }

  /**
   * Loads a workspace by its id.
   *
   * @param int $workspace_id
   *   Workspace id.
   *
   * @return \Drupal\poc_nextcloud\NxEntity\NxWorkspace
   *   The workspace object.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   *   The workspace does not exist or something went wrong.
   *   Unfortunately, workspaces API always gives 500 Internal Server Error, so
   *   we cannot distinguish different causes of failure.
   */
  public function load(int $workspace_id): NxWorkspace {
    $data = $this->connection->requestJson(
      'GET',
      '/workspaces/' . $workspace_id,
    );
    return new NxWorkspace(
      // Note that property keys are different in this GET request.
      $data['id'],
      $data['groupfolder_id'],
      $data['space_name'],
      $data['color_code'],
    );
  }

  /**
   * Renames a workspace.
   *
   * @param int $workspace_id
   *   Workspace id.
   * @param string $name
   *   New name.
   *
   * @throws \Drupal\poc_nextcloud\Exception\NextcloudApiException
   */
  public function rename(int $workspace_id, string $name): void {
    $success = $this->connection->requestJson(
      'PATCH',
      '/api/space/rename',
      [
        'workspace' => ['id' => $workspace_id],
        'newSpaceName' => $name,
      ],
    )['space'];
    if (!$success) {
      throw new NextcloudApiException(sprintf(
        'Failed to rename workspace %d to %s.',
        $workspace_id,
        $name,
      ));
    }
  }

}
