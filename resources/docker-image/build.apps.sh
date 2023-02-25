# Script to download Nextcloud apps.

set -ex

# Create a temporary Nextcloud instance with sqlite, to download apps.
rsync -a /usr/src/nextcloud/ /usr/src/nextcloud1/
cd /usr/src/nextcloud1/
./occ maintenance:install --admin-pass=admin

# Download the apps using occ.
# The app:install command is only available if Nextcloud is installed.
./occ app:install --keep-disabled richdocuments
./occ app:install --keep-disabled groupfolders
./occ app:install --keep-disabled -f user_cas

# Copy the apps back into the source Nextcloud instance.
# Later, on container startup, all of this is copied to /var/www/html.
rsync -a /usr/src/nextcloud1/custom_apps/ /usr/src/nextcloud/custom_apps/

# Remove the temporary Nextcloud instance.
# This also removes the sqlite database.
rm -rf /usr/src/nextcloud1/


