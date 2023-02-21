# This file will be mounted inside the nextcloud container.
# It should only be executed in that container.

echo ''
echo '                                                  Install system packages'
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
echo '                                            Verify Nextcloud installation'
echo '-------------------------------------------------------------------------'
cd /var/www/html
if sudo -E -u www-data ./occ status | grep -q 'installed: true'; then
  echo 'Nextcloud is already installed.'
else
  # This only happens if something went wrong in the container setup.
  echo 'Nextcloud installation failed on container startup. Giving up.'
  echo ''
  exit
fi

echo ''
echo '                                                             Install apps'
echo '-------------------------------------------------------------------------'
echo ''
cd /var/www/html
sudo -E -u www-data ./occ app:install richdocuments
sudo -E -u www-data ./occ app:enable richdocuments
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
