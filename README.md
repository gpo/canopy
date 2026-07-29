# canopy


## Claude Code cloud environments

Sessions in [Claude Code on the web](https://claude.ai/code) install this repo's dependencies at session start via `.claude/hooks/session-start.sh`, but a plain `composer install` there often fails: the session's GitHub access is scoped and most of this repo's dependencies resolve through `api.github.com`, which 403s mid-session.

**Verified fix:** the environment's **Setup Script** phase (before a session starts) has broader GitHub access - confirmed by a real environment rebuild, where `composer install` succeeded there by pulling dist zips straight from `api.github.com`. Add this to the environment's **Setup script** field, after any dotfiles/bootstrap step, alongside the equivalent line for any other attached repo you want pre-built:

```bash
if curl -fsSL "https://raw.githubusercontent.com/gpo/canopy/main/claude/cloud-environment-setup.sh" -o /tmp/cloud-environment-setup.sh; then
  bash /tmp/cloud-environment-setup.sh
else
  echo "canopy: could not fetch cloud-environment-setup.sh during environment setup - PHP tooling (pint/pest) unavailable until this is fixed" >> ~/.cloud-setup-errors.log
fi
```

The fetched script (`claude/cloud-environment-setup.sh`) is self-contained: it clones canopy itself if it isn't already attached as a source, rather than assuming a pre-existing checkout. It logs its own failures (e.g. a failing `composer install`) to `~/.cloud-setup-errors.log` too - append-only, so it must run **after** any step that truncates that file. On failure this surfaces at the start of the next session instead of vanishing into an unwatched setup-script console.

`.claude/setup-env.sh` runs the same script for convenience when working by hand from an existing checkout - it's a thin wrapper, not a second copy, so there's nothing to keep in sync.

Sessions fall back to a direct `composer install` in `.claude/hooks/session-start.sh` if the Setup Script step didn't run (e.g. an environment without the block above), but that's expected to fail mid-session for the same GitHub-scoping reason - use the Setup Script block for anything that needs to reliably work.
