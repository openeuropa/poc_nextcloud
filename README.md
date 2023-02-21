# poc_nextcloud
Proof of concept for an integration of Drupal and Nextcloud.


## Development setup

Open a terminal, open the project directory, run `docker-compose up`.
Fade this terminal to the background.

In another terminal, run these commands:

```
# Install additional apps and settings in Nextcloud.
docker-compose exec nextcloud sh /usr/bin/install-nextcloud.sh

# Install Drupal.
docker-compose exec web composer install
docker-compose exec web ./vendor/bin/run drupal:site-install

# Login to the site.
docker-compose exec web ./vendor/bin/drush uli
```

### Get started

User:
1. Create a new user account "somebody".
2. Add the role "Nextcloud user".
3. Visit the profile.
  - There should be a link to a Nextcloud user account.

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
