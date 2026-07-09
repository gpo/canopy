#!/usr/bin/env bash

set -euo pipefail

if [ $# -lt 2 ]; then
  echo "Usage: $0 <gcp-project> <secret-name> [version]" >&2
  exit 1
fi

project="$1"
secret_name="$2"
version="${3:-latest}"

if ! command -v gcloud >/dev/null 2>&1; then
  echo "gcloud CLI is required but not found on PATH" >&2
  exit 1
fi

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
out_file="$root_dir/.env.local"

if [ -e "$out_file" ]; then
  read -r -p ".env.local already exists — overwrite? [y/N] " confirm
  case "$confirm" in
    [yY]*) ;;
    *) echo "Aborted."; exit 1 ;;
  esac
fi

umask 077
gcloud secrets versions access "$version" \
  --project "$project" \
  --secret "$secret_name" \
  > "$out_file"

echo "Wrote $out_file"
