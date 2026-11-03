# Architecture — Phase 1
Recorded before scaffolding, 23 October 2026. Read the 29-page plan and 85-page design. User authorizes Phase 1 only; embedded later-phase prompts are reference material.

## Applications
Storefront: Next.js App Router, React + TypeScript. Choose server rendering/pre-rendering for future indexable catalogue/editorial routes. Admin: Vite React + TypeScript, local connection probe only; staff protection arrives in Phase 3 before any operations/data. Backend: Laravel 13 API, authoritative business rules and server-side credentials.

## Development infrastructure
SQLite (PDO driver available), database queue/cache, local disk storage and log sandbox mail. Ports/origins: localhost:3000 storefront, localhost:5173 admin, localhost:8000 API. Bind loopback, strict frontend ports, explicitly scoped CORS without credentials. Production hosting, relational database, object storage, email, backups and monitoring are pending choices.

## Contracts
Versioned /api/v1 routes. Health returns data.status/service/database plus meta.request_id/timestamp. Database failure returns 503. Errors use error.code/message/details plus meta. Server generates X-Request-ID. Typed clients have a timeout, useful failure state and retry. Central configuration via Laravel config and frontend API client modules. Store UTC timestamps; business display/reporting Europe/London. Future money uses integer minor units plus currency. Incremental reviewed migrations; no commerce schema finalized. Separate payment/fulfilment/return states and require verified, idempotent provider operations in later phases.

## Design and assumptions
Design 00, D02 and D03 guide foundations. Palette: charcoal #202722, ivory #F6F5EF, olive #475640, sand #B8A17B. Only basic foundation styling here; design system/layouts belong to Phase 2. No S/M/A screens claimed complete. Photos, prices, metrics and policies in PDFs are concepts.
Assume premium menswear, UK-first, GBP, guest checkout primary, optional accounts and courier shipment booking. Confirm before dependent work. Pending merchant inputs: actual products/suppliers/variants/stock, markets/company/tax, logo/fonts/photos, warehouse/courier/rates, payment provider, policies/support contacts, retention/marketing consent, deployment/recovery ownership. None blocks Phase 1.

## Versions and controls
Detected Node 24.21.0, npm 11.19.0, PHP 8.5.0, Composer 2.8.12, Git and PDO SQLite. Use Laravel 13 (PHP 8.3+), current Next/Vite compatible with Node 24; resolved versions in lockfiles/progress. Verify upstream guidance: https://github.com/laravel/docs/blob/13.x/releases.md ; https://nextjs.org/docs/app/getting-started/installation ; https://vite.dev/guide/ . Prettier, Oxlint, TypeScript, production builds, Pint, PHPUnit and CI provide delivery controls. Ignore secrets and runtime files. No deployment or Phase 2 work.

Resolved dev dependencies require PHP 8.4+ (Symfony 8 / PHPUnit 12); use PHP 8.5 locally and in CI. Oxlint replaces the generated Next ESLint stack because its current transitive dependencies report advisories and ESLint peer incompatibilities. Composer optimized classmaps are disabled for local installation; production optimization is a deployment-phase concern.


## Phase 2 amendment — 23 October 2026
The active project is now Desktop/ELVARQO. Shared visual tokens and client-safe React primitives live in shared/ui and are synchronized into both independently installed apps by shared/sync-ui.mjs. No new commerce schema, backend behavior or authentication is added. App-specific shells consume typed concept fixtures isolated from API clients. Next.js pre-renders public layout/content; JS enhances menus and dialogs. Diagnostics remain on dedicated routes. See PHASE2-DESIGN.md for decisions, provenance and coverage.


## Phase 4 extension (26 October 2026)

Catalogue and inventory architecture is recorded in PHASE4-ARCHITECTURE.md; API and retention contracts in PHASE4-API.md. The backend now requires its dedicated Node/Sharp package for private product-image processing. Staff permission/session foundations are reused. Storefront shopping integration remains Phase 5.


## Phase 5 extension (26 October 2026)

PHASE5-ARCHITECTURE.md records request-time storefront rendering and public discovery extensions. PHASE5-SETUP.md documents canonical/indexing and endpoint configuration; PHASE5-BAG-HANDOFF.md defines the untrusted identifier-only bridge to future checkout. No schema migration; no bag writes.


## Phase 6 extension (26 October 2026)

PHASE6-ARCHITECTURE.md documents the guest cookie/proxy trust boundary, authoritative pricing, immutable normalized order snapshots, stock reservation/commit/release ledger, hosted Stripe sandbox adapter, verified reconciliation and durable sandbox confirmation outbox. PHASE6-API.md is the endpoint/state contract; PHASE6-SETUP.md contains local commands. SQLite migration batch 4 adds 11 tables without seeding development data. Actual Stripe sandbox capture remains pending at the user's request; PHASE6-REPORT.md records the acceptance gap. Phase 7 has not begun.


## Phase 7 extension (26 October 2026)

PHASE7-ARCHITECTURE.md and PHASE7-API.md record protected order/manual workflows, location-aware partial fulfilment, durable idempotent local courier sandbox receipts/labels, one-use tracking/payment invitations, independent guest tracking sessions, signed monotonic shipment events and durable private notification copies. Batch 5 adds 14 empty tables; shared pricing/checkout stock rules and staff security remain authoritative. PHASE7-SETUP.md contains exact commands. Phase 6 actual Stripe capture remains explicitly deferred by user; no real courier/email integration or Phase 8 work is claimed.
