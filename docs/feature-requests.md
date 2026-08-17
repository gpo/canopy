# Feature Requests — Post-Migration

Captured ideas for the Canopy-hosted gpo.ca and the wider network. This is intake, not commitment: items here have no priority or phase until they graduate into a design doc or ADR. Cutover-related work stays in [`design-doc-gpo-ca-migration.md`](design-doc-gpo-ca-migration.md).

## FR-001 — Supporter portal (view-only)

A logged-in, view-only portal where members, donors, and volunteers can see their own contributions: donation history, membership status, and volunteer activity.

**Notes and open questions**

- Source of truth is likely Stripe (payments) and Qomon (contacts, volunteer activity), not WordPress; the portal is a read-only view over those systems
- Authentication model: WP accounts for tens of thousands of supporters is a new posture (today only editors log in, behind IAP); magic-link or Qomon-backed auth may fit better than WP users
- Privacy: contribution data is sensitive; needs per-person isolation, audit logging, and a look at Elections Ontario and PIPEDA obligations before design
- Tax receipts could be a natural follow-on but are not in this request
