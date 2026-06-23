# Contributing

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/) (for wp-env local WordPress environment)
- [Node.js](https://nodejs.org/) v18+
- [pnpm](https://pnpm.io/)
- [Composer](https://getcomposer.org/)

## Setup

```bash
pnpm install
composer install
```

To start a local WordPress environment with the plugin active:

```bash
pnpm wp-env start
```

WordPress will be available at `http://localhost:8888` (admin: `admin` / `password`).

## Building JS

Only needed when changing files in `src/`:

```bash
pnpm build          # one-off build
pnpm start          # watch mode
```

Commit the updated `build/` directory alongside any `src/` changes. The build output is committed so the plugin installs via Composer without requiring Node.

## Running Tests

**PHP integration tests** (requires wp-env running):

```bash
pnpm test:php
```

**JS unit tests** (no wp-env needed):

```bash
pnpm test:js
pnpm test:js:watch  # watch mode
```

## Linting

```bash
pnpm lint:js         # ESLint + Prettier
pnpm lint:css        # Stylelint
pnpm lint:php        # PHPCS (WordPress coding standards)
pnpm lint:php:fix    # Auto-fix PHP style violations
```

A pre-commit hook (via husky + lint-staged) runs linters automatically on staged files.

## Architecture

- `docs/architecture.md` -- caching strategy and data flow
- `docs/database.md` -- `wp_options` keys written by this plugin

## Key Conventions

- **Single PHP file** -- all logic lives in `qomon.php`. No autoloader, no classes.
- **`function_exists()` guards** -- every public function is wrapped so the plugin can coexist with other versions.
- **`save()` returns null** -- the Gutenberg block is server-rendered via `render_callback`.
- **`type="module"`** -- a `script_loader_tag` filter patches this for any handle prefixed `qomon-form-`.
- **Cron schedules on activation** -- deactivate/reactivate to reschedule after changing the interval.

## Releasing

1. Bump the version in `qomon.php` (plugin header), `readme.txt` (stable tag), and `package.json`.
2. Rebuild JS: `pnpm build`
3. Commit `build/` alongside the version bump.
4. Tag the release: `git tag v2.x.x && git push --tags`
5. The GitHub Actions release workflow will build a zip and attach it to the GitHub Release.
