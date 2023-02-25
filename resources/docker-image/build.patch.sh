# Script to download Nextcloud apps.

set -ex

cd /usr/src/nextcloud/

# Patch the user_cas app.
echo "Patch: \OCP\App was removed."
curl https://patch-diff.githubusercontent.com/raw/felixrupp/user_cas/pull/106.diff | patch -p1 -d custom_apps/user_cas
echo "Patch: Don't pass NULL to setcookie(,,*)."
curl https://patch-diff.githubusercontent.com/raw/felixrupp/user_cas/pull/108.diff | patch -p1 -d custom_apps/user_cas

# Patch phpCAS to link to http instead of https.
# This is needed to make it work with the cas mock server.
# See https://github.com/felixrupp/user_cas/issues/109
cat /image/phpCAS.http.patch | patch -p0 -d custom_apps/user_cas/vendor/jasig/phpcas
