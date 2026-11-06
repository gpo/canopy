#!/usr/bin/env bash

set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root_dir"

site_title="${1:-Canopy}"

wp() {
  if command -v ddev >/dev/null 2>&1 && ddev describe >/dev/null 2>&1; then
    ddev exec wp "$@"
  else
    command wp "$@"
  fi
}

if [ ! -f .env ]; then
  echo ".env not found. Copy .env.example to .env and fill in DB/WP_HOME first" >&2
  exit 1
fi

read_env_file_value() {
  [ -f "$1" ] || return 0
  { grep -E '^WP_ENV=' "$1" || true; } | tail -n 1 | cut -d= -f2- | sed -E "s/^(['\"])(.*)\1\$/\2/"
}

if [ -n "${WP_ENV+set}" ]; then
  wp_env="$WP_ENV"
else
  wp_env="$(read_env_file_value .env.local)"
  [ -n "$wp_env" ] || wp_env="$(read_env_file_value .env)"
fi

if [ "$wp_env" != "development" ]; then
  echo "Refusing to run: WP_ENV is not development" >&2
  exit 1
fi

domain_current_site="$(grep -E '^WP_HOME=' .env | sed -E "s/^WP_HOME=['\"]?https?:\/\/([^'\"\/]+).*/\1/")"

if ! wp core is-installed --network 2>/dev/null; then
  echo "==> Installing WordPress multisite network"
  wp core multisite-install \
    --subdomains \
    --title="$site_title" \
    --admin_user=admin \
    --admin_email="admin@example.com" \
    --admin_password="admin" \
    --skip-email
fi

echo "==> Ensuring a seed subsite exists"
if ! wp site list --field=url | grep -q "^https\?://riding\.${domain_current_site}"; then
  wp site create --slug=riding --title="Riding Site"
fi

echo "==> Activating network plugins"
wp plugin activate --network --all

echo "Done. Admin: admin / admin"
