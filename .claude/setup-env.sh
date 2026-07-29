#!/bin/bash
# Verified working: paste the block in README.md's "Claude Code cloud
# environments" section directly into the environment's Setup Script field
# (self-contained there - it can't reference this file, since this file
# won't exist yet if canopy isn't already cloned). This copy exists for
# convenience when running by hand from an existing checkout, and as the
# single place this logic is defined - keep README.md's block in sync with
# it if you change this.
#
# Confirmed by a real environment rebuild: composer install succeeds during
# Setup Script execution by pulling dist zips from api.github.com, which
# 403s in a live session. The `if [ ! -d .git ]` clone fallback below is
# defense-in-depth for an environment that doesn't have canopy pre-attached
# as a source; in the normal case the platform has already cloned it before
# the setup script runs, so that branch is skipped.
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
  mkdir -p ~/.claude
  echo "$msg" >> ~/.claude/cloud-setup-errors.log
}
