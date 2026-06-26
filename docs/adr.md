# Architectural Decision Records — Canopy Phase 1

**Format:** [MADR](https://adr.github.io/madr/) (Markdown Architectural Decision Records)
**Status key:** `accepted` | `proposed` | `deprecated` | `superseded`

---

## Index

**Accepted**

- [ADR-001 — Bedrock as the WordPress stack foundation](#adr-001--bedrock-as-the-wordpress-stack-foundation)
- [ADR-002 — Separate staging cluster](#adr-002--separate-staging-cluster)
- [ADR-003 — Subdomain and custom domain URL structure](#adr-003--subdomain-and-custom-domain-url-structure)
- [ADR-004 — Specialty sites on the same network](#adr-004--specialty-sites-on-the-same-network)
- [ADR-005 — Hybrid theme with Tailwind CSS and Meta Box](#adr-005--hybrid-theme-with-tailwind-css-and-meta-box)
- [ADR-006 — Three-layer testing framework](#adr-006--three-layer-testing-framework)
- [ADR-007 — DDEV for local development](#adr-007--ddev-for-local-development)

- [ADR-011 — Sage as the base WordPress theme](#adr-011--sage-as-the-base-wordpress-theme)

**TBD**

- [ADR-008 — GKE Autopilot as the Kubernetes runtime](#adr-008--gke-autopilot-as-the-kubernetes-runtime)
- [ADR-009 — Stateless pods with GCS media offload](#adr-009--stateless-pods-with-gcs-media-offload)
- [ADR-010 — GCP IAP for WordPress admin panel access](#adr-010--gcp-iap-for-wordpress-admin-panel-access)

---

## ADR-001 — Bedrock as the WordPress stack foundation

**Status:** accepted
**Date:** 2026-05-26
**Decision makers: Ian Edington and Mark Wong**

### Context and Problem Statement

WordPress by default manages core, plugins, and themes through the admin panel at runtime. This is incompatible with an immutable container image deployment where nothing can be installed or updated outside of the CI/CD (continuous integration/continuous deployment) pipeline. We need a project structure that manages all WordPress dependencies as code.

### Considered Options

- **Bedrock** — a Composer-managed WordPress stack with environment-based configuration and a clean document root
- **Standard WordPress with custom deployment scripts** — vanilla WordPress with manual scripting to replicate Bedrock's behaviour
- **Custom project structure** — build a bespoke structure from scratch

### Decision Outcome

**Chosen option: Bedrock.**

Bedrock manages WordPress core and all plugins as Composer dependencies, enforces environment-based configuration (no secrets in `wp-config.php`), and separates the document root from application code. The alternatives require reinventing what Bedrock already solves well.

**Consequences:**

- All third-party plugins must be available as Composer packages (most are via WPackagist) or vendored in-repo
- Plugins that are only installable via the WP admin are incompatible with this stack
- The WP admin updater is disabled in production — all updates ship through CI/CD
- Dependabot (or equivalent) should be enabled to open automated PRs for Composer dependency updates, including WordPress core and WPackagist plugins

---

## ADR-002 — Separate staging cluster

**Status:** accepted
**Date:** 2026-05-26
**Decision makers: Ian Edington and Mark Wong**

### Context and Problem Statement

A staging environment is required to test changes before they reach production. The two main options are isolating staging within the same cluster as production (via Kubernetes namespace) or running it on a separate cluster entirely.

### Considered Options

- **Separate GKE cluster** — staging has its own cluster, fully isolated from production
- **Shared cluster with separate namespace** — staging and production share the same cluster, separated by Kubernetes namespace

### Decision Outcome

**Chosen option: Separate GKE cluster with separate CI/CD pipelines.**

A shared namespace means a runaway staging workload can compete for cluster resources with production. A separate cluster gives full isolation and allows infrastructure-level changes — autoscaler thresholds, ingress configuration — to be tested without any risk to production. Staging and production each have their own GitHub Actions workflow file. Staging deploys automatically on merge to `main`. Production is triggered manually via `workflow_dispatch` after the developer has reviewed staging. There is no automatic promotion between environments.

**Consequences:**

- Two GitHub Actions workflow files — staging auto-triggers on merge to `main`, production is manually triggered
- Staging must be seeded with at least 20–30 subsites to meaningfully catch upgrade failures at scale — a staging network with 3 sites will not surface problems that appear at 50+
- Additional cluster running cost; see cost analysis in `docs/design-doc-tech-phase-1.md`

---

## ADR-003 — Subdomain and custom domain URL structure

**Status:** accepted
**Date:** 2026-05-26
**Decision makers: Ian Edington and Mark Wong**

### Context and Problem Statement

WordPress multisite requires a choice of URL structure for subsites. Each riding site needs its own distinct web address. Some ridings will use a subdomain of `gpo.ca`; others may have their own registered domain.

### Considered Options

- **Subdomain mode** (`guelph.gpo.ca`) — each subsite is a subdomain of the main domain
- **Subdirectory mode** (`gpo.ca/guelph`) — each subsite lives under a path on the main domain
- **Custom domains per riding** (`guelphgreens.ca`) — each riding maps its own domain

### Decision Outcome

**Chosen option: Subdomain mode with custom domain mapping support. No subdirectory mode.**

Subdirectory URLs produce weak per-riding brand identity. Subdomain and custom domain support are both enabled — WordPress multisite's built-in domain mapping (WP 4.5+) handles custom domains without an additional plugin.

**Cert strategy:**

- `*.gpo.ca` wildcard cert covers all `<riding>.gpo.ca` subdomains
- Custom domains use individual certs via cert-manager + Let's Encrypt, provisioned automatically once DNS is pointed at the cluster

**Consequences:**

- Each new custom-domain riding requires a domain onboarding workflow (DNS change, WP domain mapping, cert verification)
- A runbook for this process must be written before the first custom domain goes live

---

## ADR-004 — Specialty sites on the same network

**Status:** accepted
**Date:** 2026-05-26
**Decision makers: Ian Edington and Mark Wong**

### Context and Problem Statement

Specialty and campaign sites (`1997.gpo.ca`, `islandgetaway.ca`) need to be hosted somewhere. The question is whether they live in the same WordPress multisite network as official riding sites, or in a separate install.

### Considered Options

- **Same network** — specialty sites are subsites in the same network, excluded from riding governance in code
- **Separate install** — specialty sites run as independent WordPress installs sharing the same codebase

### Decision Outcome

**Chosen option: Same network.**

One network to maintain, one deployment pipeline. Operational simplicity outweighs the separation benefit at this stage.

**Consequences:**

- Specialty sites must be explicitly typed in the network and excluded from all network-wide riding operations (syndication, bulk updates, analytics rollups) — enforced in code, not by convention
- The reputational risk of network association (a specialty site generating controversy reflecting on riding sites) has been consciously accepted

---

## ADR-005 — Hybrid theme with Tailwind CSS and Meta Box

**Status:** accepted
**Date:** 2026-05-26
**Decision makers: Ian Edington and Mark Wong**

### Context and Problem Statement

WordPress offers several theme development approaches. The chosen approach must support a fully customised Gutenberg block editing experience for CAs (candidate agents), serve as the foundation for a Phase 2 shared block library, and be maintainable by a single developer.

### Considered Options

- **Block theme (FSE — Full Site Editing)** — `theme.json` and block templates throughout; no PHP templates for standard pages
- **Classic theme** — PHP templates, `functions.php`, traditional WordPress structure
- **Hybrid theme** — classic PHP template structure with a heavily customised Gutenberg block editor experience

### Decision Outcome

**Chosen option: Hybrid theme.**

FSE is still maturing and has documented rough edges with complex multisite layouts and locked templates. Classic themes are heaviest to maintain as Gutenberg evolves and make the Phase 2 shared block library significantly harder to build. Hybrid gives full control over templates while keeping the block editor as the primary CA content interface.

**Stack:**

| Layer | Technology | Role |
| --- | --- | --- |
| Styling | Tailwind CSS | Utility-first CSS; shared between theme templates and block styles |
| Block editor | Gutenberg (custom blocks) | All content components built as custom blocks |
| Custom fields | Meta Box | Structured content fields, custom post types, custom taxonomies |

**Consequences:**

- Custom blocks live in a dedicated blocks plugin (name TBD), not the theme — blocks are the core product CAs use to build their sites and should be portable and independently testable
- Tailwind's output must be scoped to avoid collisions with wp-admin styles
- Block styling changes require a coordinated update to both the blocks plugin (block markup) and the theme (Tailwind config) if design tokens change

---

## ADR-006 — Three-layer testing framework

**Status:** accepted
**Date:** 2026-05-26
**Decision makers: Ian Edington and Mark Wong**

### Context and Problem Statement

The project has three distinct types of code to test: PHP backend logic, JavaScript/React block components, and full user flows in a browser. No single testing tool covers all three well.

### Considered Options

- **PHPUnit only** — covers PHP but not JavaScript or browser flows
- **PHPUnit + Jest** — each layer tests what it is best suited for

### Decision Outcome

**Chosen option: PHPUnit + Jest.**

| Layer | Tool | What it tests |
| --- | --- | --- |
| Backend | PHPUnit | Plugin PHP logic, API endpoints, hooks |
| Frontend unit | Jest + @testing-library/react | Block components and JavaScript logic in isolation |

Each layer catches a different class of bug. PHPUnit and Jest run in milliseconds — fast feedback on logic errors. Browser-level end-to-end testing is deferred to a later phase.

**Consequences:**

- Tests run in order: PHPUnit → Jest — cheap tests fail fast

---

## ADR-007 — DDEV for local development

**Status:** accepted
**Date:** 2026-05-26
**Decision makers: Ian Edington and Mark Wong**

### Context and Problem Statement

Developers need a local environment that runs WordPress multisite with Bedrock. The environment must be easy to set up and consistent across machines.

### Considered Options

- **DDEV** — a Docker-based local development tool purpose-built for PHP projects with first-class Bedrock and WordPress multisite support
- **Docker Compose (custom)** — a hand-rolled Docker Compose configuration
- **Local Kubernetes (minikube/kind)** — mirrors the production environment most faithfully

### Decision Outcome

**Chosen option: DDEV.**

DDEV handles the web server, database, and PHP configuration automatically and is purpose-built for this type of project. A custom Docker Compose setup requires manual configuration of everything DDEV provides out of the box. Local Kubernetes mirrors production most faithfully but adds substantial overhead for day-to-day development work.

**Consequences:**

- Local Kubernetes is not used; infrastructure and deployment-specific changes are tested against the remote cluster directly
- A `bin/setup-local.sh` script is needed to automate first-run multisite setup (network creation, subsite creation, seed content import) on top of DDEV

---

## ADR-011 — Sage as the base WordPress theme

**Status:** accepted
**Date:** 2026-06-26
**Decision makers: Mark Wong**

### Context and Problem Statement

ADR-005 established a hybrid theme with Tailwind CSS. A base starter theme is needed as the foundation — one that gives main theme developers unrestricted CSS freedom (complex components, transform animations, custom Gutenberg blocks) and allows sub-sites to consume the custom blocks those developers build.

### Considered Options

- **Sage (Roots.io)** — classic PHP + Laravel Blade templates, Vite, Tailwind CSS; part of the Roots/Bedrock ecosystem
- **Underscores (_s)** — Automattic's canonical blank-canvas starter; no build toolchain
- **FSE themes (Frost, Twenty Twenty-Five, Blockbase)** — Full Site Editing block themes driven by `theme.json`
- **Framework themes (Astra, Kadence)** — lightweight parent themes with large plugin ecosystems
- **Faust.js** — headless WordPress with a Next.js front end
- **Tonik** — Tailwind + Webpack WordPress starter

### Decision Outcome

**Chosen option: Sage.**

Sage is already the natural companion to Bedrock (ADR-001) — both are Roots projects designed to work together. It ships Vite + Tailwind v4 preconfigured and uses Laravel Blade templates, giving developers full styling freedom without fighting a pre-existing CSS layer. Custom Gutenberg blocks registered in the parent Sage theme (or the companion blocks plugin established in ADR-005) are available to all child themes on the network, satisfying the sub-site consumption requirement.

Underscores was a strong alternative as a blank canvas, but it receives infrequent updates and ships no build toolchain — the Vite + Tailwind configuration Sage already provides would need to be built from scratch.

**Consequences:**

- Developers must learn Laravel Blade templating; standard PHP template conventions do not apply inside Sage
- The Acorn package (Laravel IoC container for WordPress) is an additional managed dependency
- Child themes for individual sub-site variants override only the templates and CSS tokens they need; the block library is inherited from the parent

### Why the other options were rejected

**FSE themes (Frost, Twenty Twenty-Five, Blockbase)**
`theme.json` and Tailwind's utility classes compete for the same design tokens. In FSE, global styles are controlled through `theme.json` and the block editor's global styles UI — any Tailwind utility that touches typography, spacing, or colour must either be duplicated in `theme.json` or will conflict with what the editor generates. Complex layout components (combo-boxes, image overlays with transform animations) require writing CSS that works around the block editor's wrapper markup and style injection, rather than freely composing utilities.

**Astra and Kadence**
Both ship a substantial base CSS layer that loads on every page. Tailwind's preflight reset and utility classes produce specificity conflicts with this existing CSS. Developers end up writing `!important` overrides or fighting cascade order rather than building components cleanly. Neither theme is designed to have its base styles stripped or bypassed.

**Faust.js**
A headless architecture moves rendering to a separate Next.js application — custom Gutenberg blocks built in WordPress do not transfer to sub-sites through the standard block registration mechanism. Sub-sites would need their own front-end deployments, adding significant operational and hosting complexity that is out of scope for this project.

**Tonik**
Tonik satisfies the Tailwind freedom requirement but uses a Webpack build pipeline, which is a generation behind Vite in developer experience (hot module replacement speed, configuration simplicity, ecosystem momentum). Its community is small, documentation is sparse, and there is no established pattern for the parent-to-child-theme block inheritance this project requires.

---

## ADR-008 — GKE Autopilot as the Kubernetes runtime

**Status:** proposed
**Date:** 2026-05-26
**Decision makers:**

### Context and Problem Statement

The platform runs on Google Kubernetes Engine (GKE). GKE offers two modes: Autopilot, where Google manages node provisioning and scaling automatically, and Standard, where the team manages the underlying server pools. For a platform maintained by a single developer, the operational overhead of each mode is a significant factor.

### Considered Options

- **GKE Autopilot** — Google manages nodes, scaling, and patching automatically
- **GKE Standard** — full control over node pool configuration and machine types

### Decision Outcome

**Chosen option: GKE Autopilot.**

Autopilot removes the operational burden of managing node pools. Standard mode's additional control over server configuration is not needed at this stage. If specific scaling constraints arise that Autopilot cannot handle, the migration path to Standard is available.

**Consequences:**

- Pod resource requests (CPU and memory) must be explicitly set in all deployment manifests — Autopilot enforces a minimum of 250m CPU and 512 MiB memory per container and will reject pods that don't specify them
- Node-level configuration (custom machine types, taints) is not available

---

## ADR-009 — Stateless pods with GCS media offload

**Status:** proposed
**Date:** 2026-05-26
**Decision makers:**

### Context and Problem Statement

WordPress by default writes uploaded media to the local filesystem (`wp-content/uploads`). In a multi-replica Kubernetes deployment, each pod has its own ephemeral filesystem — a file uploaded to one pod does not exist on the others. All pods need access to the same media files.

### Considered Options

- **GCS (Google Cloud Storage) offload via WP Offload Media** — all uploads go directly to a GCS bucket; the local filesystem is never used for media
- **Shared persistent volume** — a ReadWriteMany volume mounted to all pods, simulating a shared filesystem
- **Single-replica deployment** — avoid the problem by never running more than one pod

### Decision Outcome

**Chosen option: GCS offload via WP Offload Media.**

A shared persistent volume adds operational complexity and does not scale cleanly under election-day load. Single-replica deployment cannot handle traffic surge. GCS offload is the standard approach for scaled WordPress deployments and is well-supported.

**Consequences:**

- `wp-content/uploads` on the container is ephemeral and must never be relied upon
- Every plugin added to the stack must be vetted — plugins that cache generated files or manage their own upload directories are incompatible
- Local development uses a real `canopy-dev` GCS bucket rather than an emulator, keeping dev behaviour consistent with production

---

## ADR-010 — GCP IAP for WordPress admin panel access

**Status:** proposed
**Date:** 2026-05-26
**Decision makers:**

### Context and Problem Statement

The WordPress admin panel (`/wp-admin`) must be protected from public access in production. Several options exist for adding an authentication layer in front of it.

### Considered Options

- **GCP IAP (Identity-Aware Proxy)** — authenticates via Google account at the ingress level before the request reaches WordPress
- **IP allowlist** — restricts access to a list of known IP addresses at the ingress level
- **Basic HTTP auth** — server-level username and password prompt

### Decision Outcome

**Chosen option: GCP IAP.**

IAP verifies a valid Google account before the request ever reaches WordPress. No IP lists to maintain as developers work from different locations, no extra passwords to manage. It is the cleanest fit for a Google Cloud-hosted deployment.

**Consequences:**

- All developers and content editors need a Google account and the appropriate IAP access grant
- IAP access grants must be part of the onboarding process for every new team member or CA (candidate agent)
