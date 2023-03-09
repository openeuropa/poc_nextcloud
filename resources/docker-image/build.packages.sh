# Build script to install packages.

set -ex

apt-get update
apt-get install -y --no-install-recommends \
  less \
  nano \
  sudo \
  git
pecl install xdebug
docker-php-ext-enable xdebug
