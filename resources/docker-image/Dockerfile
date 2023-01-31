FROM nextcloud:25-apache

RUN set -ex; \
  apt-get update; \
  apt-get install -y --no-install-recommends \
    less \
    nano \
    sudo \
    git \
  ; \
  pecl install xdebug; \
  docker-php-ext-enable xdebug;

COPY build.sh /

RUN set -ex; \
  sh /build.sh;

COPY entrypoint.d/ /entrypoint.d/

RUN set -ex; \
  sed -i 's/exec "$@"//g' /entrypoint.sh; \
  mv /entrypoint.sh /entrypoint.d/00-nextcloud-install.sh;

COPY entrypoint.sh /

RUN chmod a+x /entrypoint.sh
