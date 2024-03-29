# These instructions run every time the container is started,
# after the entrypoint script from the parent container.

if [ -e "$0.done" ]; then
  # The script has already run and does not need to run again.
  echo "already done: $0"
  exit
fi

set -x

cd /var/www/html

# Enable apps that were downloaded when the image was built.
# Use `set +e` to ignore failure exit code if the apps are already enabled.
set +e
sudo -E -u www-data ./occ app:enable richdocuments
sudo -E -u www-data ./occ app:enable groupfolders
sudo -E -u www-data ./occ app:enable -f user_cas
set -e

# Allow API requests to 'http://nextcloud/' from 'web' container.
sudo -E -u www-data ./occ config:system:set --value=nextcloud trusted_domains 1
sudo -E -u www-data ./occ config:system:set --value=nextcloud_test trusted_domains 2

# Make user_cas use the CAS mock server from Drupal.
sudo -E -u www-data ./occ config:app:set --value="web" user_cas cas_server_hostname
sudo -E -u www-data ./occ config:app:set --value="8080" user_cas cas_server_port
sudo -E -u www-data ./occ config:app:set --value="/build/cas-mock-server" user_cas cas_server_path
# Change CAS login button label.
sudo -E -u www-data ./occ config:app:set --value="EU Login" user_cas cas_login_button_label
# Do not auto-create users.
sudo -E -u www-data ./occ config:app:set --value="0" user_cas cas_autocreate
# Allow to bypass cas login. This is needed for convenient admin access.
sudo -E -u www-data ./occ config:app:set --value="0" user_cas cas_force_login

# Prevent the script from running again on next startup.
touch "$0.done"
