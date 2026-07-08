#!/usr/bin/env bash
# Bootstrap a local multisite network for development: installs WordPress,
# converts it to a subdomain multisite network, creates a seed subsite, and
# activates the network plugins. Safe to re-run — each step no-ops if the
# corresponding state already exists.
#
# Usage: bin/setup-local.sh [site-title]

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
  echo ".env not found — copy .env.example to .env and fill in DB/WP_HOME first" >&2
  exit 1
fi

domain_current_site="$(grep -E '^WP_HOME=' .env | sed -E "s/^WP_HOME=['\"]?https?:\/\/([^'\"\/]+).*/\1/")"

if ! wp core is-installed --network 2>/dev/null; then
  if ! wp core is-installed 2>/dev/null; then
    echo "==> Installing WordPress"
    wp core install \
      --title="$site_title" \
      --admin_user=admin \
      --admin_email="admin@${domain_current_site}" \
      --admin_password="admin" \
      --skip-email
  fi

  echo "==> Converting to subdomain multisite network"
  wp core multisite-convert --subdomains --title="$site_title"
fi

echo "==> Ensuring a seed subsite exists"
if ! wp site list --field=url | grep -q "^https\?://riding\.${domain_current_site}"; then
  wp site create --slug=riding --title="Riding Site" --url="riding.${domain_current_site}"
fi

echo "==> Activating network plugins"
wp plugin activate --network --all

echo "Done. Admin: admin / admin"
