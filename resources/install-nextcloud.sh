# This file will be mounted inside the nextcloud container.
# It should only be executed in that container.

echo ''
echo '                                                  install system packages'
echo '-------------------------------------------------------------------------'
echo ''
apt-get update
# Packages needed for the installationn
apt-get install -y --no-install-recommends git npm sudo
# Packages added for convenience.
apt-get install -y --no-install-recommends less nano
# Packages needed to clear out existing installation.
apt-get install -y --no-install-recommends mariadb-client

echo ''
echo '                                                        Install nextcloud'
echo '-------------------------------------------------------------------------'
cd /var/www/html
if sudo -E -u www-data ./occ status | grep -q 'installed: true'; then
  echo 'Nextcloud is already installed.'
elif true; then
  # This only happens if something went wrong in the container setup.
  echo 'Nextcloud should be installed manually:'
  echo '- Visit http://localhost:8081/'
  echo '- Enter "admin" / "admin", and click "Install".'
  echo '- Skip installation of recommended apps.'
  echo '- Then run this script again.'
  echo ''
  exit
else
  # This is currently disabled, but I am keeping the code for now.
  echo ''
  set -e
  sudo -E -u www-data /var/www/html/occ maintenance:install \
    --admin-user=admin \
    --admin-pass=admin \
    --data-dir=/var/www/html/data \
    --database=mysql \
    --database-host=nextcloud_db \
    --database-port=3306 \
    --database-name=nextcloud \
    --database-user=nextcloud \
    --database-pass=nextpw
    set +e
fi

echo ''
echo '                                                             Install apps'
echo '-------------------------------------------------------------------------'
echo ''
cd /var/www/html
sudo -E -u www-data ./occ app:install groupfolders
sudo -E -u www-data ./occ app:enable groupfolders
sudo -E -u www-data ./occ app:install user_saml
sudo -E -u www-data ./occ app:enable user_saml

echo ''
echo '                                                        Set configuration'
echo '-------------------------------------------------------------------------'
cd /var/www/html
sudo -E -u www-data /var/www/html/occ config:system:set --value=nextcloud trusted_domains 1

echo ''
echo '                                                                    Done!'
echo '-------------------------------------------------------------------------'
echo 'Visit http://localhost:8081/'
echo ''
