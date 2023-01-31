# Instructions for building the Docker image.

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

# Patch the user_cas app.
echo "Patch: \OCP\App was removed."
curl https://patch-diff.githubusercontent.com/raw/felixrupp/user_cas/pull/106.diff | patch -p1 -d custom_apps/user_cas
echo "Patch: Don't pass NULL to setcookie(,,*)."
curl https://patch-diff.githubusercontent.com/raw/felixrupp/user_cas/pull/108.diff | patch -p1 -d custom_apps/user_cas

# Copy the apps back into the source Nextcloud instance.
# Later, on container startup, all of this is copied to /var/www/html.
rsync -a /usr/src/nextcloud1/custom_apps/ /usr/src/nextcloud/custom_apps/

# Remove the temporary Nextcloud instance.
# This also removes the sqlite database.
rm -rf /usr/src/nextcloud1/


