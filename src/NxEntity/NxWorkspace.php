<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\NxEntity;

/**
 * Value object for a workspace loaded from the API.
 *
 * This assumes the 'workspace' app is installed in Nextcloud.
 *
 * Currently this class is only used in the respective submodule, but other
 * modules might want it available without having the submodule enabled.
 */
class NxWorkspace {

  /**
   * Constructor.
   *
   * @param int $id
   *   Id, or NULL if this is a stub group folder.
   * @param int $groupFolderId
   *   Group folder id.
   * @param string $spaceName
   *   Workspace name.
   * @param string $color
   *   Workspace color.
   */
  public function __construct(
    private int $id,
    private int $groupFolderId,
    private string $spaceName,
    private string $color,
  ) {}

  /**
   * Gets the workspace id.
   *
   * @return int
   *   Workspace id.
   */
  public function getId(): int {
    return $this->id;
  }

  /**
   * Gets the referenced group folder id.
   *
   * @return int
   *   Group folder id.
   */
  public function getGroupFolderId(): int {
    return $this->groupFolderId;
  }

  /**
   * Gets the workspace name.
   *
   * @return string
   *   Workspace name.
   */
  public function getSpaceName(): string {
    return $this->spaceName;
  }

  /**
   * Gets the color stored for the workspace.
   *
   * @return string
   *   Color value, e.g. '#0fc09b'.
   */
  public function getColor(): string {
    return $this->color;
  }

}
