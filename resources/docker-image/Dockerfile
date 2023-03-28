FROM nextcloud:25-apache

# Only copy the specific files needed for the next RUN step,
# to not have the cache invalidated by changes in other files.
# Keep copied files in a subdirectory, to not pollute the root directory.

# Install system packages.
COPY build.packages.sh /image/
RUN sh /image/build.packages.sh

# Download additional apps for Nextcloud.
COPY build.apps.sh /image/
RUN sh /image/build.apps.sh

# Patch the user_cas Nextcloud app.
COPY build.patch.sh /image/
COPY phpCAS.http.patch /image/
RUN sh /image/build.patch.sh

# Prepare entrypoint scripts.
COPY build.entrypoint.sh /image/
COPY entrypoint.sh /image/
COPY entrypoint.post-install.sh /image/
RUN sh /image/build.entrypoint.sh

COPY build.config.sh /image/
RUN sh /image/build.config.sh
