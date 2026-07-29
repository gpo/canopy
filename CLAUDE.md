# Canopy

Shared codebase for all GPO WordPress work: one multisite network hosting riding sites (CA-managed, syndication-eligible) and specialty/campaign sites (`1997.gpo.ca`, `islandgetaway.ca`). Contains multiple themes and plugins.

## Public Repository

- Redact proper names; use roles instead
- Packages may be open to other political organisations but must not provide exclusive benefit to any one party

## Maintainer

Director of Technology — sole technical owner.

## Hosting

- **Runtime:** Kubernetes on GKE
- **Database:** Cloud SQL (MySQL, GCP-managed)
- **Media:** GCS via WP Offload Media — pods are stateless

## Development Constraints

- **No local media writes.** All media goes to GCS. Plugins that assume `wp-content/uploads` is writable will break in a multi-replica deployment.
- **No WP admin updates.** Core, plugins, and themes ship as immutable container images. All updates go through the deployment pipeline.

## Integrations

- **Qomon** — forms and canvassing (MVP)
- **Stripe** — payments and donations (MVP)

## Central Publishers

Two teams publish independently with equal push-with-approval authority over riding sites:

- **DComms** — press releases, key messages, main pages
- **Fundraising** — action campaigns, petitions, donations

## MVP Scope

Network, riding site templates (target + development tiers), Qomon, Stripe. Content sharing, French, analytics, paper-candidate stubs, and content calendar are Phase 2+.

## French

Hard requirement, deferred to Phase 3. See `docs/multisite-platform.md` for strategy options.

## Verification

- Lint: `vendor/bin/pint --test` (fix with `vendor/bin/pint`)
- Tests: `vendor/bin/pest` (`tests/Unit`, `tests/Integration`; see `phpunit.xml`)
- Run both before pushing.

## Cloud agent sessions (Claude Code on the web)

`composer install` reliably fails mid-session (GitHub access is scoped, and most deps resolve through `api.github.com`, which 403s). It reliably **succeeds** during the environment's Setup Script phase instead, which has broader GitHub access - confirmed by a real environment rebuild. See README.md for the Setup Script block to paste in; `.claude/hooks/session-start.sh` only attempts a direct install as a fallback for environments without that block, and it's expected to fail there for the same reason.

The hook also provisions a native WordPress runtime (no Docker/DDEV in the sandbox): MariaDB via apt (`canopy`/`canopy` credentials, matching the generated `.env`), wp-cli, and a sandbox `.env`. Once `web/wp` exists (from the Setup Script step), bootstrap a network with `wp core multisite-install --allow-root`, serve with `wp server --host=127.0.0.1 --port=8080 --allow-root`, and verify rendered pages with the Playwright MCP.

## Key Documents

- [`docs/multisite-platform.md`](docs/multisite-platform.md) — design doc
