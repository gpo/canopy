# Architectural Decision Records — Canopy Phase 1

**Format:** condensed — one paragraph stating the choice and why the alternatives lose, plus a short consequences list only when there's a real gotcha to flag. Optimized for a reader to quickly find and understand why a decision was made, not for a full write-up.
**Status key:** `accepted` | `proposed` | `deprecated` | `superseded`

---

## Index

**Accepted**

- [ADR-001 — Bedrock as the WordPress stack foundation](#adr-001--bedrock-as-the-wordpress-stack-foundation)
- [ADR-002 — Separate staging cluster](#adr-002--separate-staging-cluster)
- [ADR-003 — Subdomain and custom domain URL structure](#adr-003--subdomain-and-custom-domain-url-structure)
- [ADR-004 — Specialty sites on the same network](#adr-004--specialty-sites-on-the-same-network)
- [ADR-005 — Block theme (FSE) with Meta Box](#adr-005--block-theme-fse-with-meta-box)
- [ADR-006 — PHPUnit + Jest as the testing stack](#adr-006--phpunit--jest-as-the-testing-stack)
- [ADR-007 — DDEV for local development](#adr-007--ddev-for-local-development)

- [ADR-011 — Vanilla WordPress block theme as the base theme](#adr-011--vanilla-wordpress-block-theme-as-the-base-theme)
- [ADR-012 — pnpm as the front-end package manager](#adr-012--pnpm-as-the-front-end-package-manager)
- [ADR-013 — TypeScript as the front-end language standard](#adr-013--typescript-as-the-front-end-language-standard)

**TBD**

- [ADR-008 — GKE Autopilot as the Kubernetes runtime](#adr-008--gke-autopilot-as-the-kubernetes-runtime)
- [ADR-009 — GCS media offload via WP Offload Media](#adr-009--gcs-media-offload-via-wp-offload-media)
- [ADR-010 — GCP IAP for WordPress admin panel access](#adr-010--gcp-iap-for-wordpress-admin-panel-access)

---

## ADR-001 — Bedrock as the WordPress stack foundation

**Status:** accepted · **Date:** 2026-05-26 · **Decision makers:** Ian Edington and Mark Wong

Composer-managed WordPress core, plugins, and environment-based config, chosen over vanilla WordPress with custom deployment scripts or a bespoke project structure — the standard approach for an immutable-container deployment where nothing installs outside CI/CD; the alternatives mean rebuilding what Bedrock already solves. Plugins must be available as Composer packages (WPackagist, or vendored) since the WP admin updater is disabled in production.

---

## ADR-002 — Separate staging cluster

**Status:** accepted · **Date:** 2026-05-26 · **Decision makers:** Ian Edington and Mark Wong

Chosen: a separate GKE cluster with its own CI/CD pipeline, over a shared cluster split by Kubernetes namespace. A shared namespace lets a runaway staging workload compete with production for resources; a separate cluster gives full isolation and lets infra-level changes (autoscaler thresholds, ingress config) be tested with zero production risk. Staging auto-deploys on merge to `main`; production is triggered manually after review, with no automatic promotion between them.

**Consequences:**

- Staging must be seeded with 20–30+ subsites to meaningfully catch upgrade failures at scale
- Runs as an additional, ongoing cluster cost (see `docs/design-doc-tech-phase-1.md`)

---

## ADR-003 — Subdomain and custom domain URL structure

**Status:** accepted · **Date:** 2026-05-26 · **Decision makers:** Ian Edington and Mark Wong

Chosen: subdomain mode (`<riding>.gpo.ca`) with custom domain mapping support, over subdirectory mode (`gpo.ca/guelph`) — subdirectory URLs read as sub-pages rather than distinct sites and give weak per-riding brand identity. WordPress multisite's built-in domain mapping (4.5+) covers custom domains without an extra plugin; a `*.gpo.ca` wildcard cert covers subdomains, and custom domains get individual certs via cert-manager + Let's Encrypt.

**Consequences:**

- Each new custom-domain riding needs a domain onboarding workflow (DNS change, WP domain mapping, cert verification)
- A runbook for that process must exist before the first custom domain goes live

---

## ADR-004 — Specialty sites on the same network

**Status:** accepted · **Date:** 2026-05-26 · **Decision makers:** Ian Edington and Mark Wong

Chosen: keep specialty/campaign sites (`1997.gpo.ca`, `islandgetaway.ca`) as subsites on the same network, rather than a separate WordPress install — one deployment pipeline and codebase; operational simplicity outweighs the separation benefit at this stage.

**Consequences:**

- Specialty sites must be explicitly typed and excluded from network-wide riding operations (syndication, bulk updates, analytics rollups) in code, not by convention
- The reputational risk of network association (a specialty site's controversy reflecting on riding sites) is a consciously accepted trade-off

---

## ADR-005 — Block theme (FSE) with Meta Box

**Status:** accepted · **Date:** 2026-07-20 · **Decision makers:** Ian Edington and Mark Wong

Chosen: a block theme (FSE — `theme.json` + block templates), over a hybrid or classic PHP-template theme. A hybrid/classic approach was only worth its extra template-system maintenance if Sage (ADR-011's original candidate) supplied the tooling to make it manageable; once Sage was dropped, native FSE is sufficient on its own, core-supported, and well-documented, with no custom PHP template layer to build or maintain.

**Consequences:**

- Custom blocks live in a dedicated blocks plugin (`canopy-blocks`), not the theme, so they stay portable and independently testable
- FSE's documented rough edges with complex multisite layouts and locked templates are an accepted trade-off
- Design tokens live in `theme.json`, not a separate Tailwind config

---

## ADR-006 — PHPUnit + Jest as the testing stack

**Status:** accepted · **Date:** 2026-05-26 · **Decision makers:** Ian Edington and Mark Wong

PHPUnit for backend PHP logic, Jest + @testing-library/react for block components and front-end JS — the standard per-language pairing, each fast enough for immediate feedback (milliseconds). Browser-level end-to-end testing is deferred to a later phase; PHPUnit runs before Jest so the cheaper suite fails fast.

---

## ADR-007 — DDEV for local development

**Status:** accepted · **Date:** 2026-05-26 · **Decision makers:** Ian Edington and Mark Wong

DDEV, chosen over a hand-rolled Docker Compose setup or local Kubernetes (minikube/kind) — purpose-built for Bedrock/WordPress-multisite projects, handling the web server, database, and PHP config automatically where Compose means rebuilding that by hand and local K8s adds overhead most day-to-day work doesn't need. A `bin/setup-local.sh` script automates first-run multisite setup on top of it.

---

## ADR-011 — Vanilla WordPress block theme as the base theme

**Status:** accepted · **Date:** 2026-07-20 · **Decision makers:** Mark Wong

Chosen: a vanilla WordPress block theme (the `biomes` theme — `theme.json`, block templates/parts, `wp-scripts`), over Sage. Sage's Blade templates require Acorn, a Laravel IoC container bolted onto WordPress — a substantial dependency chain to maintain for one theme; native FSE tooling covers what the theme actually needs, is core-supported, and is well-documented.

**Consequences:**

- No Acorn, Blade, or Vite — the theme's front-end build is whatever `wp-scripts` provides
- Design tokens live in `theme.json`, not a Tailwind config
- Custom blocks are still registered in the companion blocks plugin (ADR-005), so child themes still consume them

---

## ADR-012 — pnpm as the front-end package manager

**Status:** accepted · **Date:** 2026-07-23 · **Decision makers:** Mark Wong

Chosen over npm and yarn — pnpm's content-addressable store avoids duplicating shared dependencies across the theme, blocks plugin, and any future front-end package, and its strict `node_modules` blocks phantom imports (undeclared packages) that npm and yarn silently allow.

---

## ADR-013 — TypeScript as the front-end language standard

**Status:** accepted · **Date:** 2026-07-23 · **Decision makers:** Mark Wong

TypeScript over plain JavaScript for new front-end source — compile-time checks on block-component props/APIs instead of runtime browser errors; already standard elsewhere at GPO.

---

## ADR-008 — GKE Autopilot as the Kubernetes runtime

**Status:** proposed · **Date:** 2026-05-26 · **Decision makers:**

GKE Autopilot, chosen over GKE Standard — Google manages node provisioning, scaling, and patching, removing operational burden a single-developer team doesn't need to take on; migration to Standard is available if scaling constraints later demand it. Note: Autopilot enforces a minimum pod resource request (250m CPU / 512 MiB), so all deployment manifests must set requests explicitly.

---

## ADR-009 — GCS media offload via WP Offload Media

**Status:** proposed · **Date:** 2026-05-26 · **Decision makers:**

GCS offload via WP Offload Media, chosen over a shared ReadWriteMany volume or a single-replica deployment — the standard, well-supported approach for stateless, multi-replica WordPress; a shared volume adds operational complexity that doesn't scale under election-day load, and single-replica can't absorb traffic surge. `wp-content/uploads` on the container is ephemeral, so plugins that cache files locally or manage their own upload directories are incompatible.

---

## ADR-010 — GCP IAP for WordPress admin panel access

**Status:** proposed · **Date:** 2026-05-26 · **Decision makers:**

Chosen: GCP IAP, over an IP allowlist or basic HTTP auth, to protect `/wp-admin` — it authenticates via Google account at the ingress level before a request ever reaches WordPress, so there's no IP list to maintain as developers change locations and no extra password to manage; the cleanest fit for a GCP-hosted deployment.

**Consequences:**

- Every developer and content editor needs a Google account and an IAP access grant
- IAP grants must be part of onboarding for every new team member or CA (candidate agent)
