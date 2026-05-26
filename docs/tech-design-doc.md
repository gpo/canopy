# Canopy — Technical Design

**Status:** Draft - Phase 1
**Author:** Mark Wong, Software Developer - Co-op
**Audience:** Tech team
**Last updated:** 2026-05-26

---

## Table of Contents

1. [Context](#context)
2. [Architecture](#architecture)
3. [Risks](#risks)
4. [Key Decisions](#key-decisions)
5. [Repository Structure](#repository-structure)
6. [WordPress Multisite Configuration](#wordpress-multisite-configuration)
7. [Local Development](#local-development)
8. [Theme Development](#theme-development)
9. [Plugin Strategy](#plugin-strategy)
10. [CI/CD Pipeline](#cicd-pipeline)
11. [Integrations](#integrations)
12. [Security](#security)
13. [Open Decisions](#open-decisions)

---

## Context

Background, problem statement, goals, and user tiers are in [`docs/multisite-platform.md`](multisite-platform.md). This document covers implementation decisions, risks, and architecture.

**MVP scope:** Kubernetes infrastructure on GKE (cluster, Cloud SQL, GCS media offload, ingress, cert-manager, HPA), WordPress multisite network stood up and stable, Qomon forms integration, Stripe donations. Riding site templates are out of MVP — the goal is a running, deployable network first.

---

## Architecture

```
                        ┌─────────────────────────────────────┐
                        │              GKE Cluster             │
                        │                                      │
  Internet ──► GCP LB ──► WordPress Pods (N replicas, HPA)    │
                        │         │                            │
                        │         ▼                            │
                        │   Cloud SQL (MySQL)                  │
                        │                                      │
                        └─────────────────────────────────────┘
                                  │
                                  ▼ media read/write
                             GCS Bucket
```

**Pods are stateless.** Every uploaded file goes directly to GCS via WP Offload Media — `wp-content/uploads` on the container is ephemeral. This is a hard constraint that flows through every plugin and theme decision in this doc.

| Component          | Technology               | Notes                                                                                                |
| ------------------ | ------------------------ | ---------------------------------------------------------------------------------------------------- |
| Runtime            | GKE Autopilot            | HPA handles short-campaign traffic surge; Google manages node provisioning and scaling automatically |
| Database           | Cloud SQL — MySQL 8.x    | GCP-managed failover and backups                                                                     |
| Media              | GCS via WP Offload Media | Pods never own uploads                                                                               |
| Container registry | Artifact Registry        | Images are immutable at deploy time                                                                  |
| Secrets            | Secret Manager           | Injected as env vars at pod start                                                                    |

**WordPress core, plugins, and themes ship in the container image.** The WP admin updater is disabled. All updates go through CI/CD.

---

## Risks

These are the areas most likely to cause scope growth, integration failure, or production incidents. Each has a mitigation strategy, but they need active attention — they are not solved by choosing the right library.

---

### Qomon plugin fork scope

**What:** The Qomon integration depends on a WordPress plugin currently being forked. The scope of that fork is not fully defined.

**Why risky:** Fork scope tends to grow. A plugin that works for a single test form on one site may need significant additional work to be network-activated, correctly scoped per subsite, configurable per riding, and resilient to Qomon API outages. None of that is guaranteed by the existence of the fork.

**Impact if it bites:** MVP blocked or shipped with reduced Qomon functionality. Pressure to scope-creep the integration mid-build.

**Mitigation:** Before any other integration work begins, define the minimum required feature set for MVP in writing:

- Which form types are needed at launch?
- Does the plugin activate per-site or network-wide?
- What is the expected behaviour if Qomon's API is unreachable?
- What data, if any, writes back to WordPress?

Treat everything outside that spec as out of scope until the fork proves it.

**Cross-cutting impact:** Theme templates, onboarding flow, and CA documentation all depend on knowing what Qomon forms actually look like and how they're embedded. Don't design those until the plugin's output is known.

---

### Plugin compatibility with multisite

**What:** WordPress plugins are predominantly built and tested against single-site installs. Multisite introduces network vs. per-site activation, blog-scoped vs. network-scoped options, and table prefix differences (`wp_2_posts`, `wp_3_posts`, etc.) that many plugins handle incorrectly or not at all.

**Why risky:** A plugin that works correctly on a single site can silently misbehave in multisite — writing options to the wrong site, failing to scope queries by `blog_id`, or requiring per-site activation that makes network management unworkable. The failure mode is often subtle and only surfaces with real data.

**Affected plugins:** GiveWP, Qomon fork, Meta Box, and any plugin added later.

**Mitigation:** Multisite compatibility is a required verification step before any plugin is confirmed for the stack — not an assumption. Test with multiple subsites active and verify: activation model (network vs. per-site), option scoping, and query behaviour. WP Offload Media is known-good in multisite and does not need re-verification.

**Cross-cutting impact:** Plugin compatibility failures discovered late could require replacing a confirmed plugin mid-build, which cascades into theme work, templates, and integration tests.

---

### WordPress upgrades across a large network

**What:** Upgrading WordPress core or a plugin in a multisite network is not equivalent to upgrading a single site. Database upgrade routines run per-subsite. At 130+ sites, a partially-applied upgrade (failed mid-run) leaves the network in an inconsistent state that is difficult to recover from.

**Why risky:** The immutable image model ensures the code upgrade is atomic, but the database upgrade is not. WP-CLI's `wp core update-db --network` runs per-site sequentially. A failure mid-run means some sites are on the new schema, some are not. WordPress does not have a rollback mechanism for DB upgrades.

**Mitigation:**

- The migration job (init container in CI/CD) must run `wp core update-db --network` and treat a non-zero exit code as a deploy failure — not a warning.
- Test upgrade paths in staging with a full-size network before applying to production.
- Take a Cloud SQL snapshot before every production upgrade. GCP-managed snapshots make this low-friction.

**Cross-cutting impact:** Staging must mirror production's site count closely enough to catch upgrade timing and failure issues. A staging environment with 3 sites will not expose problems that appear at 50+.

---

### Custom domain attachment per riding

**What:** Each riding can have its own domain (e.g. `ottawawestgreens.ca` rather than `ottawawest.gpo.ca`). WordPress multisite domain mapping, DNS configuration, and SSL cert provisioning are all required for each custom domain, and each is a potential failure point.

**Why risky:** This is a recurring operational workflow that will happen dozens of times, often under deadline pressure when a riding is launching. Cert provisioning via cert-manager is automatic once DNS is pointed correctly, but DNS propagation, mapping configuration in WP, and ingress rules must all align. A misconfiguration takes a riding site offline with no obvious error for the CA.

**Mitigation:** Define and document the full domain onboarding runbook before the first custom domain goes live. The runbook should cover:

1. DNS change the riding makes (A record / CNAME pointing to the cluster's ingress IP)
2. WP-CLI command to add the domain mapping
3. How to confirm cert provisioning completed
4. How to verify the site resolves correctly

Automate steps 2–4 where possible. This runbook is an operational dependency of the platform launch, not an afterthought.

**Cross-cutting impact:** Riding onboarding documentation, CA support workflow, and the cert-manager/ingress configuration are all tied to this process being defined and reliable.

---

### Donations and Stripe

**What:** Several unknowns in the donations flow that need resolution before build starts.

**Surface-level risks:**

- **GiveWP in multisite:** GiveWP's multisite support exists but needs explicit validation. Per-subsite vs. network activation, campaign isolation between riding sites, and donor record scoping are all behaviours that need to be tested, not assumed.

- **Stripe webhook routing:** Stripe sends webhooks to a single endpoint. With a centralised account and donations happening across many riding sites simultaneously, webhook events need to be routed to the correct subsite's handler. This is a custom implementation concern that GiveWP may or may not handle out of the box.

- **Riding attribution and compliance:** Donations are made on a per-riding site but flow through a centralised Stripe account. The attribution of each donation to the correct riding for reporting purposes needs to be explicitly implemented and verified against compliance requirements before launch.

**Mitigation:** Run a focused spike on GiveWP + centralised Stripe in a multisite environment before committing to the stack. The spike should answer: Does GiveWP handle per-site campaign isolation? How are webhooks routed? What data does GiveWP record per donation that would satisfy attribution reporting?

**Cross-cutting impact:** If GiveWP fails the spike, the donations stack needs to be reconsidered, which impacts the plugin list, theme templates, and the fundraising team's onboarding.

---

## Key Decisions

Decisions that are confirmed, with rationale and cross-cutting impact noted where relevant.

---

### GKE Autopilot as the Kubernetes runtime mode

**Decision:** Run the cluster on GKE Autopilot rather than Standard mode.

**Rationale:** Autopilot has Google manage node provisioning, scaling, and patching automatically. Standard mode gives more control over the underlying server pool, but that control comes with ongoing operational overhead that isn't justified for a solo maintainer. Move to Standard only if specific scaling constraints arise that Autopilot cannot accommodate.

**Cross-cutting impact:** Pod resource requests must be explicitly set in deployment manifests — Autopilot enforces minimum resource requirements (250m CPU, 512 MiB memory per container) and will reject pods that don't specify them.

---

### Separate staging cluster

**Decision:** Staging runs on its own GKE cluster, separate from production.

**Rationale:** A shared namespace puts staging and production on the same physical servers — a runaway staging workload can compete for resources with production. A separate cluster gives full isolation and allows infrastructure-level changes (autoscaler thresholds, ingress configuration) to be tested without any risk to production. Given that the platform will eventually reach this setup anyway, starting there avoids a disruptive migration mid-project.

**Cross-cutting impact:** The CI/CD (continuous integration/continuous deployment) pipeline needs to target two clusters. Staging must be seeded with at least 20–30 subsites before it can meaningfully catch upgrade failures at scale.

---

### GCP IAP for WordPress admin panel access

**Decision:** The WordPress admin panel (`/wp-admin`) is protected by GCP IAP (Identity-Aware Proxy) at the Kubernetes ingress level.

**Rationale:** IAP verifies a valid Google account before the request ever reaches WordPress — no IP allowlists to maintain as developers work from different locations, no extra passwords to manage. It's the cleanest fit for a Google Cloud-hosted deployment.

**Cross-cutting impact:** All developers and content editors need a Google account and the appropriate IAP access grant. This must be part of the onboarding process for every new team member or CA (candidate agent).

---

### Playwright for end-to-end browser testing

**Decision:** Playwright is the end-to-end (E2E) browser testing framework.

**Rationale:** Playwright has first-class TypeScript support and handles multi-page flows — like a Stripe payment redirect and return — cleanly out of the box. It's the natural fit for a TypeScript-heavy custom block development workflow.

**Cross-cutting impact:** E2E tests run as a third stage in the CI/CD pipeline after unit tests. Minimum test cases at launch: a candidate agent logs in and publishes a post, a donation checkout completes in Stripe test mode, and a Qomon form submits successfully.

---

### Bedrock as the WordPress foundation

**Decision:** Use [Bedrock](https://roots.io/bedrock/) as the project structure.

**Rationale:** Bedrock manages WordPress core and all plugins as Composer dependencies, which is the only workable approach for an immutable-image deployment. It also enforces environment-based config (no secrets in `wp-config.php`) and separates the document root from application code. The alternatives — standard WordPress with a custom deployment script, or a fully custom structure — both require reinventing what Bedrock already solves well.

**Cross-cutting impact:** All third-party plugins must be available as Composer packages (most are via WPackagist) or vendored in-repo. Plugins that are only installable via the WP admin are not compatible with this stack.

---

### Stateless pods — media to GCS

**Decision:** All media offloaded to GCS via WP Offload Media. `wp-content/uploads` is treated as ephemeral.

**Rationale:** Required for multi-replica Kubernetes deployment. Without this, uploaded files only exist on the pod that received them. Any plugin or theme that writes to `wp-content/uploads` and then reads back from it will break in a multi-replica environment.

**Cross-cutting impact:** Every plugin added to the stack must be vetted against this constraint. Plugins that cache generated files, store thumbnails locally, or manage their own upload directories are incompatible and must be avoided or patched.

---

### Centralised Stripe account

**Decision:** All donations flow through a single centralised Stripe account. No Stripe Connect per riding.

**Rationale:** Stripe Connect per riding adds significant operational overhead — each riding needs its own connected account, onboarding flow, and payout management. For a centralised fundraising model where funds route through the party, a single account is the correct choice. Riding-level attribution is handled in the application layer.

**Cross-cutting impact:** Webhook routing and per-riding donation attribution must be explicitly implemented. See the [Donations and Stripe](#donations-and-stripe) risk above.

---

### Subdomain and custom domain URL structure, no subdirectories

**Decision:** Each riding gets its own domain. Subdomains of `gpo.ca` (e.g. `ottawawest.gpo.ca`) and fully custom domains (e.g. `ottawawestgreens.ca`) are both supported. Subdirectory mode is not used.

**Rationale:** Subdirectory URLs (`gpo.ca/ottawawest`) are operationally simpler but produce weak per-riding brand identity. Custom domains per riding are standard for this type of network and WordPress multisite's built-in domain mapping (WP 4.5+) handles this without an additional plugin.

**Cert strategy:**

- `*.gpo.ca` wildcard cert covers all `<riding>.gpo.ca` subdomains
- Custom domains use individual certs via cert-manager + Let's Encrypt, provisioned automatically once DNS is pointed at the cluster

**Cross-cutting impact:** Every new custom-domain riding requires a domain onboarding workflow. See the [Custom domain attachment](#custom-domain-attachment-per-riding) risk above.

---

### Central review rights on riding content

**Decision:** Network admins (DComms, Fundraising) have publish/unpublish authority on any riding site.

**Rationale:** Required by the comms governance model. Central needs a technical mechanism to act on riding content without waiting for the CA.

**Cross-cutting impact:** Implemented via WordPress super-admin or a custom network-level capability — not a per-site role grant. This affects the user roles and permissions model for the entire network and must be designed before onboarding begins.

---

### Specialty sites on the same network

**Decision:** `1997.gpo.ca`, `islandgetaway.ca`, and similar specialty/campaign sites live in the same multisite network as riding sites.

**Rationale:** Operational simplicity — one network to maintain, one deployment pipeline. Specialty sites are explicitly excluded from riding syndication and governance at the application level.

**Cross-cutting impact:** Specialty sites must be typed in the network and excluded from any network-wide riding operations (syndication, bulk updates, analytics rollups). This needs to be enforced in code, not by convention.

---

### Syndication default: push-with-fork, live immediately

**Decision:** _(Phase 2)_ When central pushes content to a riding site, it goes live immediately as a CA-owned copy. The CA can edit it. Changes to the central version do not propagate after the initial push.

**Rationale:** Live-linked syndication (central changes auto-update all riding copies) is powerful but removes CA ownership of their site's content. Push-with-fork balances central reach with local control: central can move quickly, CAs can localise without asking permission.

**Cross-cutting impact:** Phase 2 block library design must support forked copies as first-class objects. The theme needs to accommodate content that was pushed from central but may have been locally modified.

---

### Issue taxonomy owned by central

**Decision:** _(Phase 2)_ The issue tag taxonomy is defined and maintained by central (DComms). Tags are enforced at the template level — CAs cannot create new tags.

**Rationale:** The analytics signal (which issues are resonating in which ridings) is only useful if tags are consistent across all riding sites. CA-defined tags break cross-site analysis entirely.

**Cross-cutting impact:** DComms owns a content governance responsibility, not just a publishing one. The taxonomy must be defined before Phase 2 templates are built, since tags are enforced at the template level.

---

## Repository Structure

Bedrock layout. All third-party dependencies managed via Composer; custom code lives in `themes/` and `plugins/`.

```
canopy/
├── config/
│   ├── application.php          # wp-config.php equivalent
│   └── environments/
│       ├── production.php
│       ├── staging.php
│       └── development.php
├── web/                         # Document root
│   ├── app/                     # wp-content equivalent
│   │   ├── mu-plugins/          # Network-wide, always-on (in-repo)
│   │   ├── plugins/             # Composer-managed + in-repo plugins
│   │   ├── themes/              # In-repo themes
│   │   └── uploads/             # Ephemeral — do not rely on this
│   ├── wp/                      # WordPress core (Composer, do not edit)
│   └── index.php
├── composer.json
├── composer.lock
├── Dockerfile
└── .env.example                 # Template; .env is never committed
```

---

## WordPress Multisite Configuration

The network hosts three site types:

| Type            | Examples                          | Syndication eligible | Notes                                                 |
| --------------- | --------------------------------- | -------------------- | ----------------------------------------------------- |
| Riding sites    | `ottawawestgreens.ca`             | Yes                  | CA-managed; target or development tier                |
| Stub sites      | Auto-generated                    | No                   | No CMS users; minimal template                        |
| Specialty sites | `1997.gpo.ca`, `islandgetaway.ca` | No                   | Same network; excluded from riding governance in code |

**Domain mapping** is handled via WordPress multisite's built-in mapping (WP 4.5+). No additional plugin required.

**Network-wide mu-plugins** handle: GCS media offload enforcement, disabling WP admin plugin/theme installers, network-level auth hooks, and ensuring specialty sites are excluded from riding-scoped operations.

---

## Local Development

**Stack: Bedrock + Docker Compose**

Full local Kubernetes (minikube/kind) mirrors prod most faithfully but adds substantial overhead for day-to-day WordPress development. Docker Compose is sufficient for theme, plugin, and integration work. Reserve local k8s testing for infra and deployment changes.

```yaml
# docker-compose.yml (illustrative)
services:
  wordpress:
    build: .
    environment:
      DB_HOST: db
      DB_NAME: canopy
      DB_USER: wp
      DB_PASSWORD: wp
      WP_ENV: development
      WP_HOME: http://localhost:8080
    ports:
      - "8080:80"
    volumes:
      - ./web/app/themes:/var/www/html/web/app/themes
      - ./web/app/plugins:/var/www/html/web/app/plugins
  db:
    image: mysql:8
    environment:
      MYSQL_DATABASE: canopy
      MYSQL_USER: wp
      MYSQL_PASSWORD: wp
      MYSQL_ROOT_PASSWORD: root
```

**Local media storage:** A real `canopy-dev` GCS bucket. Local development behaviour matches production exactly — same API, same URLs, same edge cases. Each developer needs a GCS service account credential file stored in `.env`. No emulators.

> **TODO:** Write `bin/setup-local.sh` — WP-CLI commands to create the network, create subsites, and import seed content. A new dev should be up in one command.

---

## Theme Development

**Approach: Hybrid** — classic theme structure with a fully customised Gutenberg block editing experience. Classic PHP templates handle layout and routing; `theme.json` governs design tokens; custom blocks (registered in the custom plugin — see [Plugin Strategy](#plugin-strategy)) handle all content components.

**Stack:**

| Layer         | Technology                | Role                                                               |
| ------------- | ------------------------- | ------------------------------------------------------------------ |
| Styling       | Tailwind CSS              | Utility-first CSS; shared between theme templates and block styles |
| Block editor  | Gutenberg (custom blocks) | All content components built as custom blocks                      |
| Custom fields | Meta Box                  | Structured content fields, custom post types, custom taxonomies    |

**Why hybrid over FSE:** FSE is still maturing and has documented rough edges with complex multisite layouts and locked templates. Hybrid gives full control over templates today while keeping the block editor as the primary CA content interface — which is what Phase 2 block library work requires.

**Tailwind in WordPress:** Tailwind's JIT output must be scoped to avoid collisions with wp-admin styles. Prefix Tailwind or use a build step that isolates theme CSS to the front end only.

**Cross-cutting impact:** Custom blocks live in the plugin, not the theme. The theme consumes block styles via shared Tailwind classes. This means block styling changes require a coordinated update to both the plugin (block markup) and the theme (Tailwind config) if design tokens change.

---

## Plugin Strategy

**Hard rules flowing from the stateless pod constraint:**

- No plugin that assumes `wp-content/uploads` is persistently writable
- No plugin installs or updates via WP admin — everything ships in the container image via Composer

**Confirmed MVP plugins:**

| Plugin                   | Purpose                                             | Source                                                       |
| ------------------------ | --------------------------------------------------- | ------------------------------------------------------------ |
| WP Offload Media         | GCS media offload                                   | Composer (Delicious Brains) — multisite verified             |
| WP-CLI                   | Automation, migrations, seed scripts                | Included in image                                            |
| Meta Box                 | Custom fields, post types, taxonomies               | Composer — multisite compatibility TBD (see [Risks](#risks)) |
| GiveWP + Stripe add-on   | Donations / payments                                | Composer — multisite compatibility TBD (see [Risks](#risks)) |
| Qomon WP Plugin (forked) | Forms + Qomon integration                           | In-repo — scope TBD (see [Risks](#risks))                    |
| `canopy` (custom)        | Custom Gutenberg blocks + custom REST API endpoints | In-repo — built by Director of Technology                    |

**Custom plugin (`canopy`):**

The core in-house plugin has two responsibilities:

- **Custom Gutenberg blocks** — all riding site content components (hero, key message, donation CTA, event listing, etc.) registered here. Blocks are built with React and styled with Tailwind CSS classes shared with the theme. This is the foundation for the Phase 2 shared block library.
- **Custom REST API endpoints** — extends the WP REST API for any functionality not covered by core: Qomon webhook handling, per-riding data queries, and future Phase 2 syndication endpoints.

Separating this from the theme keeps block logic portable and independently testable.

**Explicitly avoid:**

- Any plugin with its own file-based cache writing to `wp-content/uploads`
- Plugins that use WP admin update notifications (suppress via mu-plugin or `DISALLOW_FILE_MODS`)
- WPML / Polylang — Phase 3 only

---

## CI/CD Pipeline

**Tooling:** GitHub Actions

```
push to main / PR merge
        │
        ▼
  ┌─────────────┐
  │  Build      │  composer install --no-dev
  │             │  docker build
  │             │  docker push → Artifact Registry
  └─────┬───────┘
        ▼
  ┌─────────────┐
  │  Test       │  PHPUnit (unit + integration)
  │             │  PHP_CodeSniffer (WPCS ruleset)
  │             │  Playwright (end-to-end browser tests)
  └─────┬───────┘
        │ main only
        ▼
  ┌─────────────┐
  │  Deploy     │  kubectl set image / helm upgrade
  │             │  WP-CLI migration job runs first (init container)
  └─────────────┘
```

**Database migrations** run as a Kubernetes init container before new pods start. The migration job runs `wp core update-db --network` and any custom `dbDelta()` calls via WP-CLI. A non-zero exit code is a deploy failure — not a warning. See [Upgrades risk](#wordpress-upgrades-across-a-large-network).

**Environment promotion:** `main` → staging automatically. Production requires manual approval in the Actions workflow.

**Staging environment:** Separate GKE cluster from production. Full isolation — a runaway staging workload cannot affect production, and infrastructure-level changes (autoscaler thresholds, node configuration) can be tested without risk. Staging must mirror production's site count closely enough to catch upgrade failures at scale — seed it with at least 20–30 subsites before running any upgrade tests.

> **TODO:** Document secrets bootstrap for new developers — how to populate `.env` locally. A `bin/bootstrap-secrets.sh` pulling from Secret Manager (with appropriate IAM role) is the cleanest approach.

---

## Integrations

### Qomon

Integration via a forked WordPress plugin in active development. The plugin lives in-repo under `web/app/plugins/`. Document all local modifications clearly to make tracking upstream patches manageable.

**Scope is the primary risk here — see [Qomon plugin fork scope](#qomon-plugin-fork-scope) in Risks.**

> **TODO:** Define MVP feature spec for the plugin before fork scope grows further.

### Stripe

Centralised Stripe account — all donations across all riding sites flow through one account. Riding-level attribution is handled in the application layer.

**Multisite behaviour and attribution are the primary risks here — see [Donations and Stripe](#donations-and-stripe) in Risks.**

> **TODO:** Run the GiveWP + centralised Stripe spike before committing to this stack.

---

## Security

- **`DISALLOW_FILE_MODS = true`** — disables plugin/theme installs and updates from WP admin
- **`DISALLOW_FILE_EDIT = true`** — disables the WP admin theme/plugin file editor
- Secrets (DB credentials, Stripe keys, Qomon tokens) injected via env vars from Secret Manager — never in the image or version control
- GKE network policy restricts pod egress to Cloud SQL and GCS only
- WP admin (`/wp-admin`) sits behind GCP IAP (Identity-Aware Proxy) — authenticates via Google account before the request reaches WordPress. No IP lists to maintain, no extra passwords. Configured at the Kubernetes ingress level.

> **TODO:** Document the secrets rotation procedure. Secret Manager supports versioning; rotation should be tested before launch.

---

## Open Decisions

No open decisions remain. All technical decisions have been resolved and are documented in [Key Decisions](#key-decisions) above.
