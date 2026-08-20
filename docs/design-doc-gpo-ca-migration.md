# Migrating gpo.ca to Canopy

Status: Draft for discussion

## Context

gpo.ca today is a standalone WordPress install on legacy hosting, running a bespoke theme and a large plugin stack, updated through wp-admin. Canopy is the target platform: a Bedrock-based multisite network on GKE Autopilot with Cloud SQL, GCS media offload, stateless pods, immutable container images, and IAP-guarded admin (see `design-doc-tech-phase-1.md` and `adr.md`).

This document covers moving gpo.ca onto the Canopy deployment: what moves as-is, what gets replaced, and how cutover happens without losing content, URLs, or search ranking.

### Current state inventory (2026-08-17, from the live site)

- WordPress 6.9.5, `en_CA`, America/Toronto
- Theme: `gpo-web` 2.1.0 (custom, classic theme, not on Canopy's theme stack)
- Content: 3,956 published posts, 534 drafts, plus pages, TablePress tables, and contest content
- Media: ~8,300 items (~8,000 images, 139 PDFs, a handful of video/audio/docs), currently on local uploads
- 23 active plugins, grouped by disposition below

## Goals

- gpo.ca served by the Canopy network with no content loss and no URL breakage
- All media moved to GCS; site runs correctly with stateless multi-replica pods
- Plugin stack reduced to what the platform provides or genuinely needs
- Editors keep an equivalent or better publishing workflow
- Rollback possible at every step until DNS cutover is confirmed healthy

## Non-goals

- Redesign of gpo.ca (theme port is like-for-like unless separately decided)
- French content (Phase 3)
- Migrating riding sites (separate workstream; this doc is the flagship site only)

## Network position: subsite

gpo.ca joins the network as an ordinary subsite with `gpo.ca` mapped as a custom domain (per ADR-003). The network's main site stays a reserved operational site with no public traffic. This keeps gpo.ca un-special-cased: same domain mapping, media paths (`sites/N/`), and provisioning as every other site, so nothing learned on riding sites breaks on the flagship. No strong reason to make it the main site; the main-site slot is hard to change later, and binding it to gpo.ca would couple network-wide settings to one site's lifecycle.

## Plugin disposition

| Disposition | Plugins |
|---|---|
| Platform replaces | Redis Object Cache (platform-managed), WP Mail SMTP (platform mail config), Sucuri (GKE/IAP/Cloudflare posture instead), Enable Media Replace and Phoenix Media Rename (incompatible with GCS offload assumptions; verify) |
| Carry forward | ACF Pro, The SEO Framework (+ Extension Manager), Redirection, TablePress, Simple History, Custom GPO Blocks, GPO Action Blocks, GPO Documents |
| Carry forward | Gravity Forms (+ Turnstile, Webhooks) — kept for one-off and admin-facing forms; Qomon handles lead capture, petitions, and campaign forms |
| Decide | Popup Maker; PublishPress Revisions Pro; TotalContest Pro; Meta pixel; Royal MCP |

All carried plugins ship in the container image via Composer; licensed plugins need a private package source.

## Theme

`gpo-web` is a classic custom theme; Canopy's theme direction is a vanilla WordPress block theme (`theme.json`, block templates, `wp-scripts`) with Meta Box for structured fields (ADR-005, ADR-011). Options:

1. Port `gpo-web` into the repo as-is and run it unchanged on the network (fastest, keeps two theme stacks alive)
2. Rebuild gpo.ca on the Canopy theme system (slow, couples migration to a redesign)

Recommendation: option 1 for cutover; treat rebuild as future work.

## Migration approach

1. **Repo work.** Add `gpo-web` theme and carried plugins to the Canopy Composer/image build; resolve any local-filesystem or single-server assumptions in custom plugins.
2. **Content.** Database export from the legacy site, imported into the network site's tables with `wp search-replace` for domain and path changes. Trial-run into staging first; diff URL samples.
3. **Media.** Rsync uploads to GCS, then WP Offload Media bulk-link existing attachments. Verify `srcset` and PDF links.
4. **Forms and integrations.** Migrate Gravity Forms definitions, entries, and webhooks intact; stand up Qomon forms for lead capture and campaign flows. Re-point Meta pixel, mail, and any external consumers.
5. **URLs and SEO.** Import Redirection rules, verify permalink structure matches, crawl staging against a production URL list, keep The SEO Framework meta intact through the DB import.
6. **Cutover.** Edit-only freeze (public site stays fully up; editors pause publishing); final DB and media delta sync; DNS to the GKE ingress with a short TTL and TLS issued in advance, so visitors see no interruption; legacy host kept read-only for rollback.

## Content scope

Migrate everything: published content, drafts, private posts, and full revision history. Trash is excluded.

## Decommission

Legacy host is decommissioned two weeks after cutover. Long enough to catch anything catastrophic, short enough that going back stops being imaginable. After day 14 the legacy DB is snapshotted to cold storage and the host is destroyed.

## Risks

- Custom plugins (GPO Blocks, Action Blocks, Documents) may assume single-site or writable uploads; needs a code audit before anything else, since findings could change the plan
- Gravity Forms entry history and in-flight webhooks during the freeze window
- Multisite import subtleties: user roles, `post_author` mapping, upload path rewriting (`/wp-content/uploads/` vs multisite `sites/N/`)
- Licensed plugin distribution through the immutable image pipeline
- Admin access change (IAP) is a workflow change for every editor; needs comms and a dry run with DComms and Fundraising

## Open questions

1. Who owns the freeze window and editor comms?
2. Disposition of the remaining "decide" plugins (Popup Maker, PublishPress Revisions Pro, TotalContest Pro, Meta pixel, Royal MCP)?

Feature requests for the new site are tracked separately in [`feature-requests.md`](feature-requests.md), not here.
