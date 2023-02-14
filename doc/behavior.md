# Behavior of this module

We are describing functionality for a Drupal website that is linked to a Nextcloud instance.This functionality will be available as a reusable component (e.g. Drupal module) that can be applied on different instances of Drupal and/or Nextcloud, and configured to the needs of an individual project.

## Synchronization from Drupal to Nextcloud

There will be an automatic synchronization that updates data in Nextcloud based on data/content in Drupal.This synchronization happens whenever relevant changes happen in Drupal.

### Drupal users to Nextcloud users

At any time, when the synchronization has finished, there will be:
- For every Drupal user account, if they match specific criteria, there will be a Nextcloud user account associated with it.
  - A user only gets a Nextcloud account, if:
    - The Drupal account has a certain permission.
    - The Drupal account is "active" (not blocked).
    - The Drupal account has an email address.
  - The user id in Nextcloud will have:
    - a user id identical with the Drupal account name. (TBD).
    - an email address identical with the Drupal email address.
  - When a user is deleted in Drupal, the user in Nextcloud is also deleted.
  - When a user in Drupal loses the permission or the criterion to have a Nextcloud account, if a Nextcloud account was already there, it will be deactivated, not deleted.
  - (some notes about eulogin)

### Drupal groups to Nextcloud group folders

At any time, when the synchronization has finished, there will be:
- For every Drupal group, if it matches specific criteria, there will be an associated "space" in Nextcloud to manage documents specific to the group.
  - For Drupal groups, see https://www.drupal.org/project/group
  - The "space" in Nextcloud can contain documents created by Nextcloud users, with co-editing functionality. Access to these documents will be restricted to members of the space, more details below.
  - For the "space" in Nextcloud, we can use https://apps.nextcloud.com/apps/groupfolders, it does everything we need. The rest of this document assumes that we use group folders for this purpose.
- For every Drupal user in a Drupal group, the respective Nextcloud user account will have access to the associated group folder.
  - The synchronization process may need to create groups in Nextcloud to make this happen.
  - The level of access in the Nextcloud group folder should depend on the type of membership the user has in the Drupal group.
  - The Drupal group module has a system with group-level roles and permissions, that can be used for this purpose.
  - The exact mapping and the exact roles can be configurable per website.

## User interface in Drupal

### No configuration UI

The Nextcloud connection should be configured in settings.php, possibly using env vars. There will be no administration UI.

### Links to Nextcloud user accounts

In Drupal, when logged in, if the current user has an associated Nextcloud account, there will be a link to that account.
- The link can be in a block, to be placed by a site builder.

In Drupal, when viewing a user account, if that user has an associated Nextcloud account, there will be a link to that account.
- The link only appears to users with respective permissions in Nextcloud.
- The placement of the link can be configured by a site builder.

In Drupal, when viewing a user account, I want to see additional info about the Nextcloud user account.
- Info can contain the display name, email and groups of the user.
- This should only be visible to admins for debug purpose.
- This can be enabled and placed by site builders for debug purpose, but should be disabled by default.

### Links to Nextcloud group folders

In Drupal, when viewing a group, I want to see a link to the associated space (group folder) in Nextcloud.
- The linked page will show the group folder as a directory with documents.
- The link should only appear to users with sufficient access.

In Drupal, when viewing a group, I want to see additional information about the Nextcloud group folder.
- Only for admins.
- Only in development instance.
- Can be placed by site builder.

## Variations

## Not in this POC

Nextcloud groups or permissions by Drupal user role:
- There are no "roles" in Nextcloud, only groups.
- The only "group" in Nextcloud that resembles a role is "GeneralManager". But there is no need to assign this to any users generated in the synchronization process.

Configuration UI:
- It is safer to configure the module through other means, e.g. env vars.
- Nextcloud connection details should be different per instance (production, staging/acceptance, local).
- Therefore, they should not be exported with other configuration.


