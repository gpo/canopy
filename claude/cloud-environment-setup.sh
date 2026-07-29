#!/bin/bash
# Fetched by the environment's Setup Script field (see README.md) via
# raw.githubusercontent.com, or run by hand via .claude/setup-env.sh.
#
# Self-contained: clones canopy itself if it isn't already attached as a
# source, since a script fetched by curl can't assume a pre-existing
# checkout. Confirmed by a real environment rebuild that composer install
# succeeds during Setup Script execution (pulling dist zips from
# api.github.com, which 403s in a live session).
set -u
CANOPY_DIR=/home/user/canopy

(
  set -e
  if [ ! -d "$CANOPY_DIR/.git" ]; then
    echo "==> cloning gpo/canopy"
    git clone --depth 1 https://github.com/gpo/canopy.git "$CANOPY_DIR"
  fi
  cd "$CANOPY_DIR"
  echo "==> composer install (canopy)"
  COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction
) || {
  msg="canopy: composer install failed during environment setup - PHP tooling (pint/pest) unavailable until this is fixed"
  echo "!! $msg"
  echo "$msg" >> ~/.cloud-setup-errors.log
}
