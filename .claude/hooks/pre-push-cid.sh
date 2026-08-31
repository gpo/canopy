#!/bin/bash
set -uo pipefail

# Only enforced in Claude Code on the web / remote sandboxes - a human
# pushing from a local machine is unaffected, as is a local Claude Code
# session (this only fires when CLAUDE_CODE_REMOTE=true).
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

cd "$CLAUDE_PROJECT_DIR"

if [ ! -d vendor ]; then
  echo "vendor/ missing - can't run CI checks before push. Run composer install first." >&2
  exit 2
fi

if ! vendor/bin/pint --test; then
  echo "pint --test failed - fix formatting (vendor/bin/pint) before pushing." >&2
  exit 2
fi

if ! vendor/bin/pest; then
  echo "pest failed - fix failing tests before pushing." >&2
  exit 2
fi
