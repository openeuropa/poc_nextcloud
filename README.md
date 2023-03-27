# poc_nextcloud

_This module is in "Proof of concept" state. Do not use on a production site._

Module to remote-control users and group folders in Nextcloud from Drupal.
See [doc/behavior.md](doc/behavior.md) for details on how this module works.


## Usage in an existing website

See [doc/install.md](doc/install.md).

## Development setup

Edit `/etc/hosts`, and add this line (It might look different on MacOS):

```
127.0.1.1       web nextcloud
```

Open a terminal, open the project directory, run `docker-compose up`.
Fade this terminal to the background.

In another terminal, run these commands:

```
# Install Drupal.
docker-compose exec web composer install
docker-compose exec web ./vendor/bin/run drupal:site-install

# Login to the site.
docker-compose exec web ./vendor/bin/drush uli
```

### Get started

User:
1. Create a new user account "eliza".
   (For this username there is already an entry in the EU Login mock server)
2. Set an email address, e.g. `eliza@example.com`.
3. Add the role "Nextcloud user".
4. Visit the profile.
  - There should be a link to a Nextcloud user profile.

Group:
1. Create a group 'Example group'.
2. Visit the group page.
  - There should be a link to the group folder in Nextcloud.

Group membership:
1. Add the user 'somebody' to the group 'Example group'.
2. Add the group role 'View Nextcloud group folder content'.
3. Visit the profile of 'somebody'.
  - Now the info should show a group name.
4. Use the link to the profile in Nextcloud.
