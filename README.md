# canopy


## Claude Code cloud environments

Sessions in [Claude Code on the web](https://claude.ai/code) install this repo's dependencies at session start via `.claude/hooks/session-start.sh`, but a plain `composer install` there often fails: the session's GitHub access is scoped and most of this repo's dependencies resolve through `api.github.com`, which 403s mid-session.

**Verified fix:** the environment's **Setup Script** phase (before a session starts) has broader GitHub access - confirmed by a real environment rebuild, where `composer install` succeeded there by pulling dist zips straight from `api.github.com`. Add this block to the environment's **Setup script** field, after any dotfiles/bootstrap step, alongside the equivalent block for any other attached repo you want pre-built:

```bash
(
  set -e
  CANOPY_DIR=/home/user/canopy
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
```

This is self-contained (it clones canopy itself if it isn't already attached as a source, rather than assuming a pre-existing checkout), and it's append-only to a shared log, so it must run **after** any step that truncates `~/.claude/cloud-setup-errors.log` at the start of its own run. On failure it logs there instead of only printing to the (often unwatched) setup-script console, so the failure surfaces at the start of the next session instead of vanishing.

The same content lives in `.claude/setup-env.sh` for convenience when running by hand from an existing checkout - keep both in sync if you change it.

Sessions fall back to a direct `composer install` in `.claude/hooks/session-start.sh` if the Setup Script step didn't run (e.g. an environment without the block above), but that's expected to fail mid-session for the same GitHub-scoping reason - use the Setup Script block for anything that needs to reliably work.
