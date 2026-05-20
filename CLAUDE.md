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

## Key Documents

- [`docs/multisite-platform.md`](docs/multisite-platform.md) — design doc
