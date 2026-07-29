#!/bin/bash
set -uo pipefail

# Only run this in Claude Code on the web / remote sandboxes - this early
# exit gates everything below (composer install, MariaDB/wp-cli
# provisioning, .env generation included), so none of it touches a local
# dev machine. The container state is cached after this hook completes, so
# installs are one-time per environment build, not per session.
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

cd "$CLAUDE_PROJECT_DIR"

# composer install here is a fallback: this repo's environment Setup Script
# (see README.md) already runs it during environment build, when GitHub
# access is broader than mid-session (verified: composer pulled dist zips
# straight from api.github.com at setup time; the same host 403s here).
# If that hasn't run - e.g. a session in an environment without the canopy
# setup-script line - try it directly anyway and report plainly if it fails.
if [ -d vendor ]; then
  echo "==> vendor/ already present (cached container) - skipping composer install"
elif COMPOSER_ALLOW_SUPERUSER=1 timeout 180 composer install --no-interaction; then
  echo "==> composer install succeeded"
else
  echo "!! composer install failed or timed out - Pest/Pint unavailable this session. Add the Setup Script line from README.md to fix this at environment-build time instead."
  rm -rf vendor web/wp
fi

# --- Local WordPress runtime -------------------------------------------
# No Docker/DDEV in the sandbox, so run the stack natively: MariaDB via
# apt, wp-cli via phar, PHP's built-in server via `wp server`. Multisite
# setup (this network's real topology) is left to the agent per task:
#   wp core multisite-install --url=http://127.0.0.1:8080 ... --allow-root
echo "==> provisioning MariaDB"
if ! command -v mariadbd >/dev/null 2>&1 && ! command -v mysqld >/dev/null 2>&1; then
  apt-get update -qq >/dev/null 2>&1
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq mariadb-server >/dev/null 2>&1 \
    || echo "!! mariadb install failed - WordPress cannot run this session"
fi
if command -v mysqld_safe >/dev/null 2>&1; then
  mkdir -p /run/mysqld && chown mysql:mysql /run/mysqld
  if ! mariadb -e "SELECT 1" >/dev/null 2>&1; then
    mysqld_safe >/dev/null 2>&1 &
    for _ in $(seq 1 30); do mariadb -e "SELECT 1" >/dev/null 2>&1 && break; sleep 1; done
  fi
  mariadb -e "CREATE DATABASE IF NOT EXISTS canopy;
              CREATE USER IF NOT EXISTS 'canopy'@'localhost' IDENTIFIED BY 'canopy';
              GRANT ALL ON canopy.* TO 'canopy'@'localhost';" \
    && echo "==> MariaDB running, database 'canopy' ready" \
    || echo "!! could not create database"
fi

if ! command -v wp >/dev/null 2>&1; then
  echo "==> installing wp-cli"
  curl -fsSL --max-time 60 -o /usr/local/bin/wp \
    https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp || echo "!! wp-cli download failed"
fi

if [ ! -f .env ] && [ -f .env.example ]; then
  echo "==> writing sandbox .env"
  sed -e "s|^DB_NAME=.*|DB_NAME='canopy'|" \
      -e "s|^DB_USER=.*|DB_USER='canopy'|" \
      -e "s|^DB_PASSWORD=.*|DB_PASSWORD='canopy'|" \
      -e "s|^WP_HOME=.*|WP_HOME='http://127.0.0.1:8080'|" \
      .env.example > .env
  for key in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT; do
    sed -i "s|^${key}=.*|${key}='$(head -c 32 /dev/urandom | base64 | tr -d "=+/'")'|" .env
  done
fi

echo "==> session-start hook complete"
echo "    Run checks with: vendor/bin/pint --test && vendor/bin/pest"
