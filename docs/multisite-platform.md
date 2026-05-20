# GPO WordPress Multisite Platform
**Status:** Draft — early scoping, 2027 project
**Author:** Ian Edington, Director of Technology
**Audience:** Comms team, executive, riding leads
**Last updated:** 2026-05-08

---

## Table of Contents

1. [Problem Statement](#problem-statement)
2. [Goals and Non-Goals](#goals-and-non-goals)
3. [Users and Tiers](#users-and-tiers)
4. [Proposed Approach](#proposed-approach)
5. [Key Decisions](#key-decisions)
6. [Risks and Open Questions](#risks-and-open-questions)
7. [Out of Scope and Future Work](#out-of-scope-and-future-work)
8. [Rough Phasing](#rough-phasing)

---

## Problem Statement

GPO runs a collection of WordPress sites — riding sites, specialty campaign sites, and the main party site — that have accumulated over multiple election cycles with no shared infrastructure. The honest description of where things stand: most of these sites look bad, perform poorly, and are not properly secured. That's not a criticism of the people who built them; it's the predictable outcome of asking volunteers to independently manage WordPress installs with no central support.

The problems compound each other:

**Maintenance sprawl.** Every separate WordPress install is its own maintenance surface. Core updates, plugin updates, theme updates — each one has to be applied site by site. In practice, most sites fall behind. An outdated WordPress install with unpatched plugins is not a hypothetical risk; it is a compromised site waiting to happen. During an election campaign, a defaced or hijacked riding site is a communications crisis.

**Security exposure.** Most GPO sites are not kept up to date. Outdated plugins are the most common vector for WordPress compromises. With no central patching process and volunteer administrators who may not log in for weeks, the security posture of the current estate is poor. A breach on one site reflects on the party regardless of whether it's a target riding or a lightly managed backwater.

**Cost without value.** Running WordPress sites correctly — maintained hosting, SSL, backups, regular updates, performance monitoring — costs money and time. Doing it for dozens of disconnected installs multiplies that cost without producing any economies of scale. The current approach pays the cost of many sites and gets the quality of none of them.

**Brand and quality inconsistency.** With no shared theme or design system, sites diverge. A voter who visits three different riding sites will encounter three different visual experiences, likely none of them polished. There is no central lever to push a brand update, fix a broken layout, or retire an outdated design across the estate.

**Content silos.** When central publishes a key message, a policy position, or a press release, it lives in one place. Getting it onto riding sites requires manual copy-paste by volunteers who may not know it exists. A development riding lead recently had to ask "who is best to speak with re current key messages?" — that question, asked mid-campaign, illustrates exactly how broken the current content flow is.

**No visibility.** There is no central view of which sites are live, which are stale, which are actively managed, and which have been quietly abandoned. Sites go dark when riding leads burn out or move on, and there is no process to detect it or recover gracefully.

**Onboarding cost.** Every new CA who needs access to their riding's site requires manual setup: credentials, permissions, a walkthrough. There is no standard onboarding path. The cost is low for one site; it is unworkable at scale.

---

This platform is the response to all of the above. A shared multisite infrastructure reduces the maintenance surface to one install, centralises security patching, enforces a baseline of design quality, and creates the connective tissue for content to flow between central and local teams.

It also serves a comms framework that does not yet fully exist. As a long-time GPO communications advisor put it: tech without a comms framework is a waste; tech with a framework can amplify it. DComms is leading work on a comms handbook for CAs this summer. This platform is built to serve that handbook — not to replace it.

### Long campaign vs. short campaign

This platform is designed for the **long campaign**: sustainable pace, two-way comms, mobilisation support. The short campaign — the election period proper, high-velocity, top-down firehose — is a constraint the platform must survive, not the mode it's designed around. The difference matters: design choices that are great for a short campaign (lock everything down, push everything centrally) will break the platform as a long-campaign tool.

---

## Goals and Non-Goals

These are the goals of the overall project. What ships in MVP vs. later phases is defined in [Rough Phasing](#rough-phasing).

### Goals

- Give each target and development riding a maintained, on-brand web presence with minimal burden on the CA
- Give CAs a platform that meets them where they are technically; high capacity ridings get more, but the floor is the same for everyone
- Integrate Qomon forms on riding sites for volunteer sign-ups, canvassing, and local engagement
- Integrate Stripe payments to support donations and fundraising on riding sites
- Enable fundraising and donation features as a first-class part of every riding site
- Enable central to push content — key messages, policy planks, CTAs, stats — to riding sites, with opt-in by the CA
- Generate auto-stubs for ~78 paper-candidate ridings: a minimal "we exist here" landing page at near-zero marginal cost
- Support French-language riding sites for Francophone ridings in Ontario
- Surface local content performance to central as passive signal for issue resonance — which topics are overperforming in which ridings — without requiring CA effort to report it

### Non-Goals

- Writing the comms framework (that's DComms' work; this platform serves it)
- Replacing or replicating NationBuilder, Qomon, or Action Network
- Building for the short campaign as the primary design centre
- Enforcing content parity between riding sites (local voice is a feature, not a bug)
- Accessibility audit and remediation (required before launch, but it's execution, not design)

---

## Users and Tiers

### Central comms team (DComms)
Primary publisher for press releases, key messages, and main page content. Maintains the shared block library, monitors riding sites for brand consistency. Needs tools that are fast to use under campaign pressure and don't require chasing down riding leads for every update.

### Fundraising team
Primary publisher for action campaigns — petitions, donation drives, volunteer sign-ups. Holds the same push-with-approval authority as DComms and publishes independently, without going through comms. Fundraising content has a different cadence and audience than comms content: it is typically time-bounded, conversion-focused, and needs to appear consistently across riding sites without being localised. The shared block library needs to serve both teams, and the two must be able to publish without stepping on each other.

### Riding leads by tier

| Tier | Count | Capacity | Expected platform use |
|------|-------|----------|----------------------|
| Target | 3 | Paid staff | Full CMS, active publishing |
| Development | 13 | Dedicated volunteers | Regular publishing, moderate autonomy |
| Builder | ~30 | Variable volunteers | Opt-in, low-friction onboarding, may go quiet for weeks |
| Paper candidate | ~78 | None | No CMS access; auto-generated stub only |

**A note on the tiers:** In practice, the line between "development" and "builder" ridings is soft. Volunteer capacity changes. The platform should treat these tiers as onboarding guides — what template and defaults you start with — not permission boundaries. A builder riding that gets a strong CA should be able to grow without waiting for a platform admin to unlock something.

### Specialty site editors
Sites like `1997.gpo.ca` and `islandgetaway.ca` live in the same network but have different content norms and governance. These editors are not riding CAs and should not be in the same onboarding flow or content distribution chain.

### Platform admin
The platform cannot require constant dev intervention for normal operations. If central comms needs an engineer to push an update, the design has failed.

---

## Proposed Approach

### Network model

A single WordPress multisite network hosts all sites: riding sites, paper-candidate stubs, and specialty campaign sites.

Within the network, sites are typed:

- **Riding sites** — official, syndication-eligible, CA-managed
- **Stub sites** — auto-generated, no CMS user, minimal template
- **Specialty/campaign sites** — same network, explicitly excluded from riding syndication and governance

### Hosting architecture

The platform runs on Kubernetes (GKE) backed by a GCP-managed Cloud SQL instance (MySQL). This choice directly addresses the short-campaign surge problem: Kubernetes' horizontal pod autoscaler can add capacity under election-day load without manual intervention, and Cloud SQL handles failover and backups without a DBA.

One non-trivial consequence: WordPress is traditionally stateful. It writes uploaded media to disk, and in a multi-replica deployment, all pods need to read from the same filesystem. The standard k8s approach for WordPress is to offload media to object storage — in this case, GCS — using a plugin (e.g., WP Offload Media). This means uploaded files never live on the pod; they go straight to a GCS bucket and are served from there. It is the right architecture for a scaled deployment, but it is a meaningful departure from default WordPress behaviour and needs to be accounted for in theme and plugin development.

Implications for development:
- Plugins that assume local filesystem access for media will not work correctly and must be avoided or patched
- The `wp-content/uploads` directory on the pod should be treated as ephemeral
- Deployment pipelines need to manage WordPress core, plugins, and themes as immutable container images — not via the WP admin updater

### Content distribution *(Phase 2)*

Several ideas are on the table. Rather than list them all, here is a synthesised recommendation and the trade-offs.

**Recommended: shared block library with syndication and local fork**

Central maintains a library of Gutenberg blocks: key messages, policy planks, stats, event CTAs. These are not just templates — they are live-linked components. When a riding site embeds a block, it stays in sync with the central version until the CA forks it.

- **Live-linked**: the CA uses the block as-is. When central updates the key message, every live-linked instance updates automatically. Zero effort for the CA, maximum consistency for central.
- **Fork to localise**: the CA can fork any block to add local context, a local candidate name, a local example. Forking breaks the live link intentionally. Local voice is preserved; the CA owns the result.
- **Push-with-approval**: for time-sensitive content, central can push to all riding sites with CA opt-in (approve or decline, not a forced override). Both DComms and Fundraising hold this authority independently. This is a fallback mode, not the default.

This model is a middle path. Pure push (central controls everything) kills CA ownership. Pure fork (everything is local) means central can't move fast. The shared block library threads the needle: central controls the component, the CA controls whether and how to use it.

**Content calendar visibility** is a low-cost, high-trust add: CAs see what central is planning to publish in the next few weeks. No extra permissions, no new workflow. It reduces the chance of a CA publishing something that undercuts a central announcement they didn't know was coming.

### Analytics and local issue signal *(Phase 2)*

Previous attempts at surfacing local issue resonance have relied on CAs actively reporting upward — what voters are asking about at the door, what topics are generating interest. That model has consistently failed because it adds work to volunteers who are already stretched.

The platform takes a different approach: passive analytics that generate signal from reader behaviour rather than CA effort. Each riding site runs the same analytics setup (GA4 with a shared network property). Content is tagged with a consistent issue taxonomy — housing, climate, healthcare, transit, and so on — applied at publish time. Central gets a single dashboard showing which issues are overperforming on which riding sites relative to the network baseline.

If the housing page in a Northern Ontario riding is getting three times the traffic of the same page in a suburban Toronto riding, that is a local signal. Central didn't have to ask for it. The CA didn't have to report it. The platform surfaced it passively.

The one remaining dependency is process: someone at central needs to look at the dashboard and do something with it. The analytics produce the signal; the comms framework determines who acts on it and how. This is why the feature depends on the handbook, but it's a lighter dependency than a form-based system — the data exists whether or not anyone looks at it today.

**Requirements this creates:** consistent issue tagging across all riding sites. If a CA publishes a housing page without tagging it, it's invisible to cross-site analysis. This needs to be enforced at the template level, not left to CA discretion.

### Paper-candidate stubs *(Phase 3)*

For the ~78 ridings with no active CAs, the platform auto-generates minimal landing pages: candidate name, riding name, a brief GPO message, and a contact form that routes to central. No subsite is created in WP for these — they are static or near-static pages generated from a central database.

The goal is purely discoverability: someone searching for the Green candidate in their riding finds a page. We exist, here is who it is, here is how to contact the party.

Explicit non-goal: CA editing. If a paper-candidate riding gets an active CA, they get a real subsite onboarded through the normal process. The stub doesn't need to grow.

### French language support *(Phase 3)*

French is a hard requirement. Several ridings in the Ottawa area and Northern Ontario are Francophone or bilingual, and a candidate without a French-language presence in those ridings is not competitive.

Two implementation paths, both with real costs:

**Option A: Per-subsite language assignment**
Each subsite is a single language. A bilingual riding (e.g., Glengarry-Prescott-Russell) gets two subsites — one English, one French — sharing a CA. The shared block library needs French-language versions of each block.

*Pros:* Simpler to implement and maintain. No multilingual plugin dependencies. French content is first-class, not a translation layer.
*Cons:* Double the subsites in bilingual ridings. CA manages two sites. Central maintains two versions of every shared block.

**Option B: Multilingual plugin (WPML or Polylang)**
Each subsite supports multiple languages via a plugin. The CA publishes in one language, translates in the other (or uses machine translation with review).

*Pros:* One subsite per riding regardless of language. Cleaner URL structure if desired.
*Cons:* WPML and Polylang both add significant complexity and maintenance overhead. Performance implications in multisite. One more thing to break. For a single maintainer, this is a meaningful operational burden.

This doc leans toward **Option A** but treats it as an open decision. See Key Decisions.

### Tiered onboarding, not tiered permissions

The platform does not lock features behind tiers. Every CA gets the same underlying WordPress install. What differs is the starter template and defaults: a target riding starts with a richer, pre-populated site; a builder riding starts with the minimal viable version. If a builder riding's CA outgrows their template, they ask for an upgrade — it's a support request, not a permission change.

This approach keeps the codebase simple and avoids a situation where a riding can't publish something important because they're on the wrong tier.

---

## Key Decisions

These are the choices that require stakeholder input before scoping can be completed. Each one has a suggested owner and needs a decision before the project can move into detailed design.

| # | Decision | Options | Owner | Status |
|---|----------|---------|-------|--------|
| 1 | Specialty sites in the same multisite network as riding sites? | Same network (current preference) vs. separate installs sharing codebase | Director of Technology + exec | Open |
| 2 | Default syndication behaviour: live-linked or forked? | Live-linked by default (central controls until CA forks) vs. forked by default (CA controls, central is a starting point) | DComms + riding leads | Open |
| 3 | Paper-candidate stubs: build or skip? | Build static stubs (~78 ridings) vs. accept absence for paper-candidate ridings | Exec + Director of Technology | Open |
| 4 | French strategy: per-subsite language vs. multilingual plugin | Option A (two subsites for bilingual ridings) vs. Option B (WPML/Polylang) | Director of Technology + bilingual riding leads | Open |
| 5 | Issue taxonomy: who owns it and how is it enforced? | Central defines tags, enforced at template level vs. CA-defined tags vs. hybrid | DComms + Director of Technology | Open |
| 6 | Central review rights on riding content | Does central have publish/unpublish rights on riding sites? Or advisory only? | Exec + comms | Open |

Decision 6 is the politically charged one. "Can central unpublish something a CA published?" is a question about trust and authority, not just technology. The platform can support either model, but it needs to be decided before build starts, because the answer affects how CAs relate to the platform and each other.

---

## Risks and Open Questions

### Organisational risks

**The comms framework isn't ready yet.**
This platform amplifies a comms framework; it doesn't create one. The MVP should not go live before a minimum viable version of the handbook exists. Launching the tech first and waiting for the process to catch up is how you get a platform nobody uses.

**CA churn.**
Riding leads change, especially during long campaigns. Any riding that relies on a single person with admin credentials is a riding that will go dark when that person burns out or moves. The platform needs to survive months of inactivity — no broken sites, no orphaned content, no stale domains.

**Specialty sites and riding sites sharing a network.**
If islandgetaway.ca runs on the same network as official riding sites and generates media attention (intended or otherwise), there is a non-trivial chance the network association surfaces. "The GPO attack site is hosted on the same server as the riding sites" is a headline that writes itself. This risk should be accepted consciously or mitigated by separation.

### Technical risks

**French support is expensive.**
Either option adds meaningful scope. Option A (two subsites per bilingual riding) doubles the content maintenance burden for those ridings. Option B (multilingual plugin) adds plugin complexity across the entire network. This is the most likely source of scope creep and should be sized explicitly during detailed design.

**Shared block library and paper-candidate stubs together are a significant initial investment.**
Both are the right things to build. Both require meaningful custom development. For a single maintainer, doing both in the same MVP phase is aggressive. Stubs could ship after the riding network if the timeline gets tight.

**Short-campaign traffic surge.**
The k8s/GKE architecture handles this through horizontal autoscaling — additional pods spin up under load without manual intervention. Cloud SQL scales independently. This is a solved problem at the infrastructure level, but it requires load testing before the short campaign begins to validate that autoscaling thresholds are configured correctly and that GCS media offload holds up under concurrent requests.

### Process risks

**Analytics signal is only as useful as the process that acts on it.**
The platform can surface which issues are resonating in which ridings, but someone at central needs to look at the dashboard and close the loop — adjusting messaging, producing more content on the issue, or flagging it to the relevant CA. Without that process, the analytics are noise. This is lighter than a form-based system (the data exists regardless), but it still requires ownership at central.

---

## Out of Scope and Future Work

**Explicitly out of scope for this design:**
- Comms framework and CA handbook (DComms' work)
- Accessibility audit (required before launch, scoped separately)

**Deferred to Phase 2 or later:**
- Content sharing between sites (shared block library, syndication, push-with-approval)
- Content calendar visibility
- Analytics and local issue signal
- Paper-candidate stubs
- French language support
- Builder riding self-serve onboarding
- Specialty site templates (1997.gpo.ca, islandgetaway.ca)
- Fork-to-localise workflow

---

## Rough Phasing

This phasing is indicative. Dates will be set once key decisions are made and the project is properly scoped.

### MVP — before long campaign ramp-up

The MVP ships a functional, integrated riding site platform. Content distribution features are deliberately excluded to keep scope manageable; riding sites need to work reliably before they need to be coordinated.

- Multisite network stood up on GKE with Cloud SQL and GCS media offload
- Riding site templates for target and development ridings
- Qomon forms integration (volunteer sign-ups, canvassing, local engagement)
- Stripe payments integration (donations and fundraising on riding sites)
- Fundraising pages as a standard part of every riding site template

### Phase 2 — mid long campaign

- Central shared block library: key messages, policy planks, CTA blocks
- Content syndication with local fork
- Push-with-approval for DComms and Fundraising
- Content calendar visibility (read-only for CAs)
- Analytics setup: GA4 network property, issue taxonomy enforced at template level, central dashboard
- Builder riding onboarding (self-serve, guided)

### Phase 3 — post-campaign or next cycle

- French language support
- Paper-candidate stubs for ~78 inactive ridings
- Specialty site templates (1997.gpo.ca, islandgetaway.ca), pending exec decision on network model
- Fork-to-localise workflow refinement based on Phase 2 feedback
- Accessibility audit and remediation
