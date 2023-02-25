# Build script to prepare entrypoint scripts.

set -ex

cd /
mkdir entrypoint.d

# Process entrypoint.sh from parent image.
sed -i 's/exec "$@"//g' /entrypoint.sh
mv /entrypoint.sh /entrypoint.d/10-nextcloud-install.sh

mv /image/entrypoint.post-install.sh entrypoint.d/20-post-install.sh

mv /image/entrypoint.sh /entrypoint.sh
chmod u+x /entrypoint.sh
