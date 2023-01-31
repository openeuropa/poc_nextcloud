#!/bin/sh

# Run all scripts in the /entrypoint.d/ directory.
# One of them is the parent image's entrypoint.sh, but without the last line
# that would execute the CMD.
for f in /entrypoint.d/*.sh; do
  # Pass the command-line arguments to each script.
  # At least one of them, the reduced entrypoint.sh from the parent image, needs
  # the arguments.
  sh "$f" "$@"
done

# Execute the CMD command, as in the parent image entrypoint.sh.
exec "$@"
