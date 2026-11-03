# Development progress

Date: 26 October 2026. Current scope: Phase 8 local acceptance for elvarqo.uk, in Desktop/ELVARQO. Current results are in the final Phase 8 section and PHASE8-REPORT.md; earlier records below are historical. Stripe/live refunds remain deferred. Phase 9 has not started.

## Implemented

- Inspected the initially empty workspace and available tools. No existing application files or user changes were replaced. Created one main ELVARQO folder under outputs so the complete project is a deliverable.
- Read both supplied PDFs (29-page phase plan, 85-page design); retained unchanged originals in this directory. Architecture decisions were written before scaffolding. Document instructions for later phases did not expand the user's Phase 1 request.
- Created storefront (Next.js App Router / React / TypeScript), admin (Vite / React / TypeScript), and backend (Laravel API). Public rendering choice supports pre-rendering and server rendering. Current foundation home page builds as static HTML.
- Added typed browser health clients with timeout, loading, connection errors and retry. Laravel exposes GET /api/v1/health, database check, safe 503 failure, common error envelope, generated request IDs and UTC response timestamps.
- Explicit CORS allowlist for http://localhost:3000 and http://localhost:5173; no wildcard or credentialed sessions. Frontend ports are fixed; API start command refuses port fallback.
- Configured SQLite, incremental Laravel foundation migrations (users/session infrastructure, cache, queue/failed jobs), database queue, log sandbox mail and private local storage. No catalogue/commerce schema added. Local APP_KEY was generated only in ignored .env.
- Added a guarded local foundation:probe CLI command that dispatches a real queued job, writes sandbox mail to the log and saves a storage receipt. It performs no external delivery.
- Added environment examples, ignore rules, lockfiles, Prettier, Oxlint, TypeScript/build checks, Pint, PHPUnit and GitHub Actions. README supplies exact install/configure/migrate/start/check/outage/queue commands for all three apps.

## Versions

Detected tools: Node 24.21.0, npm 11.19.0, PHP 8.5.0, Composer 2.8.12, Git; PDO SQLite/sqlite3 available. Laravel framework 13.38.0; Next.js 16.4.2, Vite 8.3.5, React 19.3.1 in both apps, storefront TypeScript 5.9.3 and admin TypeScript 6.0.3; Oxlint 1.89.0 and Prettier 3.9.10. Exact transitive versions are in lockfiles. PHP 8.4+ is required by the resolved development dependency set (Symfony 8 / PHPUnit 12), and CI selects 8.5. Node 24 LTS is the project baseline.

## Acceptance evidence

| Check | Result |
| --- | --- |
| Both source documents read and architecture documented before scaffolding | PASS |
| Laravel migrations on development SQLite | PASS: all three migrations ran |
| Laravel foundation suite | PASS: 6 tests, 47 assertions |
| Scoped CORS, preflight, denied origin, request IDs, validation/404/503 envelope | PASS in automated feature tests |
| Actual database queue + log mail + storage | PASS: probe bcb5519a-9629-43a7-8906-03198722a38e completed; receipt exists; no failed jobs |
| Both React formatter/lint/typecheck/production builds | PASS; zero lint warnings |
| Dependency audits | PASS: both npm audits report zero vulnerabilities; Composer audit reports no advisories |
| Browser connection to actual Laravel from both origins | PASS: real Edge/Chromium, no page errors |
| Simulated browser transport failure and retry | PASS for both apps; separately labelled simulation |
| Actual backend process stopped/restarted; same browser pages retry/recover | PASS for both apps |
| Fresh source installation from lockfiles / README | PASS: isolated source copy; composer install, npm ci in both apps, fresh SQLite migrations, all 6 backend tests and both production builds passed; all 3 documented dev commands started successfully; fresh browser connectivity passed |
| Secret and ignored-file check | .env, .env.local and SQLite file confirmed ignored; source secret-pattern scan passed with no findings |
| Hosted CI execution | Workflow added; not run on GitHub (no remote/push configured) |

Browser screenshots and machine-readable results are saved in verification/. Real outage testing stopped and restarted an actual PHP process serving Laravel on port 8000; it did not merely stub an HTTP response. Sandbox email means log transport, not a real email provider integration.

## Resolved issues during verification

- Restricted network initially blocked dependency downloads; approved normal network access completed installation.
- Vite's child process was blocked by sandbox EPERM; rerun with normal process permissions passed.
- Generated Next ESLint dependencies had five high advisories plus incompatible ESLint peer requirements. Replaced that lint stack with Oxlint; final npm audit is clean. Next metadata export is valid, so the Vite-specific fast-refresh export rule is disabled in the Next app only.
- Optimized Composer classmap creation was unusually slow in this Windows environment. Disabled local classmap optimization; regular PSR-4 autoload installation works. Deployment optimization remains a later release decision.
- Initial queue test used a Mailable-only fake assertion with raw log mail. Corrected it to observe the actual MessageSent event. Real sandbox worker verification also passed.

- Clean-source verification caught the removed example Unit suite still being referenced in phpunit.xml; removed that stale suite entry so fresh copies need no empty directory.

## Design coverage and boundaries

Foundation decisions cover design 00 / D02 / D03. Basic diagnostic styling follows the approved palette. No S/M/A commerce screens, dashboard metrics, authentication, product, checkout or payment features are claimed complete. Phase 2 has not started.

## Unresolved merchant inputs / next step

No merchant input blocks local Phase 1. Before dependent work confirm actual catalogue/stock/suppliers, markets/currency/company/tax, brand assets, warehouse/courier/rates, payment provider, reviewed policies/support contacts, customer retention/marketing consent and hosting/backups/recovery ownership. UK-first menswear / GBP / guest checkout primary / optional accounts remain assumptions. Admin currently exposes diagnostics only; private staff access must be implemented in Phase 3 before any staff data or operations.

Historical Phase 1 next step: wait for the Phase 2 request (now received and implemented below). No deployment, remote push or provider credentials configured.

## Repository handoff

New local Git repository initialized for this project. No commit created: this was an empty folder with no existing repository workflow or remote. All source files are available for review in the initialized local repository; no push performed. Original source PDFs, architecture, progress and source-only archive are part of the handoff; runtime secrets/dependencies/databases are excluded from the archive.


## Phase 2 — design system and layouts

Implemented in C:/Users/Muhammad Hassan Ali/Desktop/ELVARQO, the project location selected by the user after Phase 1. Existing uncommitted source was retained; each replaced source file was backed up in the chat workspace before editing. No migrations, backend source, dependency versions, local secrets or provider configuration changed.

### Completed scope
- Canonical shared tokens and React controls (shared/ui) plus repeatable sync hooks for both apps. Approved palette, system editorial/body fonts, 8px spacing, responsive breakpoints and reduced-motion support.
- Buttons, inputs/selects with visible labels/validation, badges, native dialogs/drawers with explicit focus wrap, cards/product previews, tables, pagination, loading/disabled/empty/error states.
- Storefront campaign/discovery composition, desktop/mobile header, shop mega menu, search draft, empty bag/account drawers, footer, static collection preview, agreed informational route placeholders and UI reference.
- Responsive admin navigation/shell, clearly labelled overview fixtures, local demonstration filtering, empty period state, workspace placeholders and shared UI reference.
- Phase 1 API connectivity preserved on /development and ?view=development.
- Concept image assets extracted from the supplied PDF only, with visible provenance/preview labels. No real stock, product claims, reviews or policies invented.

### Coverage and deviations
Design 00 / S01–S03 / D01 guide the shared visual layer; M01–M08 inform mobile controls and shells. A02 is used for demonstration shell composition only. Full M02–M07 shopping/checkout/account and M08 fulfilment functionality are not implemented. Refer to PHASE2-DESIGN.md for explicit differences: empty bag count 0, system font substitutes, preview notices, mobile hero stacking and honest unavailable actions.

### Verification
- Phase 1 prerequisite regression: composer test PASS (6 tests / 47 assertions), composer format:check PASS after the Desktop move.
- Frontend formatting/lint/TypeScript/build: PASS in both apps (exit 0; zero lint warnings). Exact commands/results recorded in verification/phase2/build-results.txt.
- Browser acceptance: 22 grouped checks PASS in isolated Microsoft Edge/Chromium; zero page errors. Screenshots/results saved in verification/phase2. Both apps tested at 390/768/1440px without horizontal overflow.
- Keyboard open, Tab/Shift+Tab wrap, Escape and focus restoration PASS. Additional close-button/backdrop checks PASS in both UI galleries. Search draft preservation, inline error recovery, local-only form validation, fixture pagination/filtering and disabled purchasing PASS.
- Reduced-motion styles PASS. No-JavaScript storefront heading/content and Collections navigation PASS. Both Laravel diagnostic routes connect successfully.
- 200% equivalent reflow PASS at 720 CSS pixels / device scale 2. Supplemental NATIVE browser zoom PASS in an isolated Chromium profile using chrome.tabs.setZoom(2), verified by getZoom returning 2 and layout width changing from 1440 to 720 CSS pixels. Both document scroll widths remained 720px, so no horizontal overflow. Evidence: native-zoom-results.json and native-200-percent screenshots. This supplements the 22-check suite with two actual native zoom checks (24 grouped checks total). An earlier Edge internal-settings attempt failed; the successful isolated Chromium extension approach leaves the user browser untouched.
- Palette text contrast calculations: charcoal/ivory 13.99:1; olive/ivory 7.19:1; muted/white 5.67:1; white/olive 7.86:1; footer muted/charcoal 9.33:1. These are palette checks, not a full WCAG certification or screen-reader audit.
- Visual QA compared rendered desktop/mobile shells with supplied S01/S02/S03 and A02 references. Reviewed screenshots for reflow, clipping and readable controls. No horizontal layout defects remain in tested viewports.
- Both generated UI copies match the canonical shared source; backend source and typed API clients match the Phase 1 snapshot byte-for-byte. Existing Phase 1 hosted CI is updated to run the shared hooks; no remote CI execution or deployment performed.

### Fixes found during checks
Removed a dynamicParams option incompatible with Next cacheComponents; retained unknown-route notFound handling. Corrected shared formatter config, a demonstration email regex, keyboard focus wrapping at the browser boundary and Escape handling for search inputs. Adjusted headline sizing and the admin wordmark after screenshot review. The first image check was corrected to load intentionally lazy images before asserting integrity. One browser run was interrupted by the admin server stopping; the complete rerun passed after restart.

### Remaining inputs and next phase
No feature code from Phase 3 has started. Final brand fonts/logo, genuine photography/product data and merchant policies remain later-phase inputs. Native browser zoom passed; cross-device and assistive-technology checks should continue on target devices. No screen-reader conformance claim is made. Run all apps with the existing README commands, visit the UI references, then request Phase 3 when ready: private staff authentication, two-step verification, server-enforced permissions and audit foundations.

No commit or remote push created; the initially uncommitted project remains reviewable. Source-only Phase 2 archive excludes secrets, dependency folders, local databases and caches.


## Phase 3 — private staff access (24 October 2026)

Completed only Phase 3, following plan pp.9–10 and design A01/A27/A28. Existing changes and the Phase 2 storefront were preserved. Backups of replaced files and development SQLite database were made in the private chat work directory before editing. The additive migration was applied to the development database; no personal staff account or default password was created.

### Delivered
- Separate staff model/provider/guard, database sessions and reset broker; no public registration. Hidden-prompt owner provisioning and operator recovery/role CLI commands.
- Mandatory encrypted TOTP enrollment, fresh-code replay prevention, ten hashed single-use recovery codes, code rotation and five-minute pending challenges.
- HttpOnly, path-scoped SameSite Strict cookie, production Secure setting, explicit CSRF validation, exact admin-origin credentialed CORS, idle/absolute expiry and session revocation.
- Owner/operations/support/finance permissions enforced on the server; capability and audit foundation endpoints. Customer identity cannot authenticate as staff. Capability probes do not implement business operations.
- Rate-limited neutral authentication/recovery feedback. Private local reset mailbox, 30-minute single-use hashed links, reset without MFA bypass.
- Sanitized append-only audit foundation with model and database update/delete guards. Latest 50 actual events visible to audit.view; full management/filtering/export UI remains Phase 10.
- Responsive sign-in/enrollment/recovery/reset/security UI; gated admin shell, restricted navigation and mobile security/sign-out. All business fixtures remain visibly labelled.
- Security architecture, exact owner/setup/testing/recovery commands and reusable isolated browser suite.

### Verification and corrections
- Laravel: 26 passing tests, 176 assertions covering CSRF/origin, enrollment, replay, recovery, expiry, logout, resets, customer isolation, permissions, immutable audits and CLI provisioning/recovery. Pint and strict Composer validation pass; Composer audit reports no advisories.
- Real Edge/Chromium: 17 grouped cases pass with real cookies and a private SQLite database; no browser page errors. Includes desktop/mobile, mandatory MFA, recovery rotation/reuse, expired session/reset, URL token removal, direct CSRF/origin/permission denials and log redaction. Locally generated test OTPs were used; no user's physical authenticator was enrolled.
- Real browser testing found and corrected middleware ordering so staff cookie configuration runs before global session initialization. Corrected Google2FA newer-step handling, rate-limit error rendering and initial unauthenticated UI handling.
- Evidence stored in verification/phase3 with seeds/recovery codes masked. Final frontend/build checks are recorded in build-results.txt. Source ZIP excludes dependencies, environment secrets, private storage and databases. No remote CI, commit, push or production deployment performed.

### Unresolved inputs and limits
The owner must supply their own email/password through staff:create and enroll their authenticator. Real delivery needs SMTP/provider setup; local recovery writes private .eml files. SQLite audit guards are tested; MySQL/PostgreSQL guard branches have not been exercised. Keep APP_KEY with protected database backups; production HTTPS, restricted DB credentials and hosting configuration remain deployment work. Full role/staff/audit management UI is Phase 10. No Phase 4 functionality started.

Instructions: STAFF-ACCESS.md. Security contract: PHASE3-SECURITY.md. Previous Phase 1/2 completion sections above are historical snapshots; their statements about unimplemented staff access are superseded by this section.


## Phase 4 — catalogue and inventory (26 October 2026)

Completed Phase 4 only in the user's Desktop ELVARQO project. Reviewed the phase plan pp.11–12 and supplied A09–A14/A20/A31 and S08–S10 designs, recorded PHASE4-ARCHITECTURE.md before implementation, and preserved backups of existing replaced source plus the SQLite database. Existing environment secrets were not edited.

Delivered live product/variant editor, normalized colour/size options, SKU/barcode uniqueness, GBP integer prices, measurements/fit/care/SEO, saved draft preview and explicit publish/unpublish. Collection/category navigation and product ordering, private original uploads, validated responsive images, alt text/variant linkage and media reordering/archive. New public read APIs filter drafts/archived/inactive content and resolve permanent slug aliases safely.

Inventory now records real locations, balances, reviewed reasoned adjustments, paired warehouse transfers, actor/time history and retry-safe operation UUIDs. Reserved quantities are server-controlled/read-only here. Catalogue writes require catalogue.manage independently of UI and record sanitized Phase 3 audit events. Archive operations retain references/files/ledger; future Phase 6 order items still require immutable price/content snapshots.

Applied additive catalogue migration as batch 3, with no saleable products, test stock, locations or personal accounts seeded into development. Added pinned backend/media Sharp worker because the installed PHP runtime lacks GD/Imagick; Node 24 installation and 8MB upload startup commands documented. CI definition now installs the worker; no hosted CI execution or deployment.

Verification: 46 Laravel tests / 380 assertions PASS; Pint and strict Composer validation PASS; Composer audit no advisories; image-worker npm audit zero vulnerabilities. Both frontend format/lint/type/build checks PASS. Real-browser Phase 4 lifecycle 15 grouped checks PASS and full staff security regression 17 groups PASS, no page errors. Tested desktop/mobile/tablet and equivalent 200% reflow; reviewed rendered editor, inventory and saved-preview screenshots. Evidence under verification/phase4 and verification/phase4-staff-regression.

Corrections during verification: preserved manual collection order across ordinary saves, enforced active-size measurement completeness, deduplicated responsive widths, isolated saved-preview styles, corrected live-data labels and fixed an expired-session initialization race affecting password recovery. Early failed browser attempts are superseded by the successful complete reruns; see PHASE4-REPORT.md.

Remaining: merchant-approved actual data/assets/stock; deployed HTTPS/SMTP/backup configuration; MySQL/PostgreSQL and production load validation, fuller device/assistive-technology checks. Order snapshots/reservations are Phase 6; CSV tooling Phase 10. No hard-delete purge or global DAM work included. The storefront still deliberately shows the Phase 2 concepts; Phase 5 shopping integration has not begun. No commit or push created; all pre-existing uncommitted work retained.

Use PHASE4-SETUP.md for exact setup/run/manual test commands and PHASE4-API.md for endpoint, pricing, image, retry and retention contracts. Prior Phase 1–3 sections are historical snapshots superseded by this current status.


## Phase 5 — storefront shopping (26 October 2026)

Completed Phase 5 only after reviewing phase-plan pp.13–14 and S01–S12/S38/M01–M03. Recorded architecture before changes and saved private source/database backups. Existing environment secrets and development data preserved; no migration or new package dependency. No QA products/stock were seeded into development.

Delivered server-backed home/collection navigation, shop/search and product routes; validated coherent variant filters, stable counts/sort/pagination, contextual facets, GET URL persistence and useful zero-result recovery. Added actual galleries, explicit colour/size selection, current price/stock checks, unavailable combinations, supplier size guides in cm/in, fit/care overrides and shared quick selection. Guest wishlist stores at most 50 IDs/optional variant choices, handles corrupt/blocked storage and cross-tab synchronization, and reloads current published content including renamed/unavailable products.

Selected request-time SSR/no-store strategy, blocking metadata and safe canonical/noindex rules. Verified actual product HTTP 404/308 and no-JavaScript content. Added bounded API failures, image placeholders/retry including errors before hydration, and preserved-input outage recovery. Defined BagHandoffV1 without trusted browser price; selection summary explicitly performs no bag/order/reservation mutation. Campaign concept image remains labelled; genuine assets/reviews/claims are not fabricated.

Verification: 53 backend tests / 437 assertions PASS, strict Composer/Pint PASS, Composer audit no advisories. Storefront formatting/lint/types/build PASS and actual isolated production server browser journey 15 grouped checks PASS; no browser page errors. Native radios/table semantics, modal keyboard focus, responsive 390/768/1440 and equivalent 200% reflow checked. Production browser suite includes real backend shutdown/retry, current-price change, wishlist persistence/storage failures and image failure before hydration. Existing admin build and catalogue browser regression results are saved under verification/phase5-catalogue-regression.

Fixes found during checks: correctly scoped zero-result fixture query, avoided hidden search-form selectors, replaced transient wishlist state with an external store, stopped stale saved cards before refresh and handled pre-hydration image errors. Detailed report, evidence and explicit limits: PHASE5-REPORT.md; startup/manual checks: PHASE5-SETUP.md; bag trust boundary: PHASE5-BAG-HANDOFF.md.

Remaining inputs: actual merchant products, campaign assets and stock; production hosting/TLS/indexing review; wider device/browser/load and physical screen-reader tests. Search is product-only until editorial content exists. Reviews, restock/newsletter signup and account sync remain later phases. No hosted CI, commit, push, external email or deployment performed. Phase 6 bag/checkout/payment/reservation functionality has not started.

Data audit: all 26 non-cache tables match the pre-change backup. The cache table has two additional rows; all original cache entries are preserved. No product, stock, staff or customer records changed.

Final regression: existing admin format/lint/type/build checks PASS; complete Phase 4 catalogue browser suite PASS (15 groups), in addition to 15 new shopping groups.


## Phase 6 — bag, checkout and payments (26 October 2026)

Implemented Phase 6 substeps 6A–6D only in Desktop/ELVARQO. Read the plan pp15–16 and supplied S13–S18, M04–M06 and minimum configuration designs before changes. Recorded PHASE6-ARCHITECTURE.md and made private source/SQLite backups before implementation. The user approved Stripe sandbox, UK/GBP and test-only rates, then explicitly requested finishing with actual Stripe capture pending.

Delivered a persistent guest bag with HttpOnly capability/proxy, server price/quantity/discount/delivery/test-tax validation, stale quote/revision checks, immutable normalized order item/address and aggregate snapshots, atomic expiring reservations, separate order/payment/fulfilment states and an append-only automated stock ledger. Stripe adapter uses hosted Checkout, frozen idempotent requests, verified raw-body signatures, duplicate/late-event safety and authoritative reconciliation. Confirmation/recovery restores saved details; uncertain provider status holds stock. Durable payment-transaction outbox feeds retry-safe private queued confirmation copies. MFA/fulfilment.view protects read-only admin order list/detail and safe configuration summary. No Phase 7 management/dispatch/courier/refund actions were added.

Applied additive commerce migration as batch 4: 11 new tables, all empty in development. Original rows across all 27 existing tables remain intact; only the migrations table has its expected additional record, other 26 tables unchanged. Existing environment files preserved byte-for-byte. No development catalogue/test orders/accounts seeded; no dependency-lock changes, commits, pushes or deployment.

Verification: 83 backend tests / 752 assertions PASS, including 30 new checkout cases; Pint, strict Composer validation and audit PASS. Both frontend format/lint/type/build checks PASS. Phase 6 production browser suite 13 groups PASS, shopping regression 15 groups PASS, catalogue/inventory regression 15 groups PASS; no browser page errors. Independent-process last-unit race PASS. Actual database queue worker produced one private confirmation copy despite repeated outbox dispatch. Reviewed desktop/mobile screenshots; verified 390/768/1440 widths and equivalent 200% reflow. Tests deliberately use isolated databases and clearly labelled provider HTTP fakes.

Corrections during verification: fixed Laravel regex-rule delimiters, staff-test session setup and fake-HTTP failure isolation; froze complete provider request parameters against config changes; normalized validated integer strings; hid unneeded drawer content; restored expired checkout details; corrected live order labels and generic API errors; added native quantity selection. Initial failed checks were fixed and superseded by complete passing reruns.

**Unfinished acceptance:** actual Stripe hosted sandbox purchase, actual signed event delivery/replay and hosted decline/abandonment/expiry tests remain pending because no local test/signing keys are configured and the user elected to defer capture. Do not label synthetic provider tests as a real purchase or Phase 6 fully accepted. Tax/delivery remain labelled test assumptions. External email is disabled; private sandbox .eml copies only. MySQL/PostgreSQL, production/load/TLS/rate-budget handling and wider device/assistive testing remain unverified. No live payment was attempted.

Exact local setup/testing: PHASE6-SETUP.md. API/states: PHASE6-API.md. Acceptance report: PHASE6-REPORT.md. Earlier statements that Phase 6 had not started or the bag is a temporary selection preview are historical and superseded here. Phase 7 has not started.


## Phase 7 — orders, fulfilment and delivery bookings (26 October 2026)

Implemented Phase 7 substeps 7A–7D only. The user explicitly authorized proceeding while deferring actual Stripe integration purchase verification. Reviewed the supplied plan pp17–18 and A03–A06/A25/A29/S19/M08 designs, documented PHASE7-ARCHITECTURE.md before implementation, and backed up source/SQLite privately.

Delivered protected order search/filter/detail, immutable snapshots, append-only internal notes, remaining quantities and timeline/history; manual order quote/review using the shared Pricing/Checkout transaction, unpaid one-use payment invitations and safe unattempted expiry. No staff mark-paid override. Added item/parcel/location/service quotes, explicit sandbox label review, partial allocations, durable booking attempts/receipts, private SVG labels, safe timeout retries, cancellation eligibility and physical dispatch without another stock deduction. Local courier sandbox only, never a real postage purchase.

Guest tracking now requires reference/email plus an expiring one-use private link; a separate order-scoped HttpOnly session exposes progress but no addresses/staff notes/labels. Signed/deduplicated sandbox events preserve monotonic shipment status and financial state. Verified minimal support intake appears to staff. Durable transaction-bound notifications use encrypted link payloads and deterministic private queued .eml copies; external delivery remains disabled. Permission, MFA/session/origin/CSRF boundaries reused.

Verification: 110 backend tests / 976 assertions PASS, including 27 new fulfilment cases; Pint, strict Composer validation and dependency audit PASS. Both frontend format/lint/type/production builds PASS. New Phase 7 production browser lifecycle 14 groups PASS; checkout regression 13, catalogue/inventory 15 and public shopping 15 groups PASS (57 browser groups, no page errors). Independent-process remaining-unit allocation and same-parcel label races PASS; original checkout last-unit race PASS. Actual queue worker exercised private link/dispatch/support copies. Responsive 390/768/1440 and equivalent 200% reflow checked; screenshots and rendered sandbox label reviewed.

Applied additive fulfilment migration batch 5: 14 new empty development tables. Original rows across all 38 existing tables retained; only migrations gains its expected record and other 37 tables unchanged. Existing environment files preserved byte-for-byte. No test merchandise, staff accounts, paid orders, stock or shipments seeded into development; no dependency lockfile changes, commits, pushes or deployment.

Fixes from verification: handle same-page tracking email fragments/development remounts, isolate named rate budgets to avoid progress reads exhausting support, preserve unattempted manual expiry while Stripe is disabled, validate event collisions/order identity/expiry, wrap/atomically save labels and update the checkout regression to open the new stock disclosure. Initial failed runs were corrected and superseded by passing reruns.

Boundaries: actual Stripe capture remains deferred at user request; real courier selection/integration, origin/service rules, external transactional email and merchant-approved tax/delivery/policy content remain outstanding. Paid verification fixtures exist only in guarded isolated tests, not application routes or development. Labels say NOT VALID FOR POSTAGE. SQLite verified; production/load/TLS/per-client rate/retention and wider assistive/device/database validation remain unverified. No Phase 8 accounts/returns/exchanges/refunds begun. Full ticket workflows remain Phase 10.

Exact local commands and failure/retry/guest-link instructions: PHASE7-SETUP.md. State/API contract: PHASE7-API.md. Acceptance/results/remaining work: PHASE7-REPORT.md. Earlier Phase 6 statements that admin orders are read-only are historical and superseded by permission-controlled Phase 7 operations.

## Phase 8 — customer accounts and after-sales (26 October 2026)

Implemented 8A–8D for the user-approved local test scope. Read supplied plan pp19–20 and S20–S27/A07–A08/M07 designs, documented PHASE8-ARCHITECTURE.md before code, and preserved private source/SQLite backups. The user approved test-only 30 days after verified delivery, inspection before restocking and a local refund sandbox; Stripe remains deferred. No Phase 9 content/marketing development started.

Delivered separate verified customer identities/sessions, private one-use email verification/recovery/change/claim outbox, login/logout and revocation, profile and scoped/versioned addresses. Owned paginated orders preserve historical snapshots; guest association requires proof sent to the immutable order email, bound to the requesting verified account. Guest checkout/tracking remain independent. Account saved items merge guest choices without replacing existing selections, hydrate larger lists in bounded 50-ID catalogue batches, preserve failures/overflow, and never put account items into guest storage. Private state resets on identity change; request generations reject delayed previous-account responses.

Delivered guest/account partial item returns with delivered-unit/window eligibility, exact discount/credit allocation, reasons/resolutions and timelines. Claims bind immutable delivered fulfilment-item/local-unit proof independently of bounded original credit slots; cancelling an earlier unshipped parcel cannot make a claimed physical unit eligible again. Rejected inspections release claims while immutable events retain proof. Physical full-quantity inspection records receiving location/outcome/reason; only accepted resellable units restock once in a dedicated immutable ledger. Refund callbacks never change stock.

Exchange review rechecks published same-product variants/current stock and exact price difference. Equal/cheaper replacements have explicit exchange_credit funding and immutable snapshots, commit stock atomically and use existing fulfilment tools. Higher-priced replacements remain unpaid, cannot ship while top-ups are deferred, and expire/release their 30-minute holds. Missing stock or stale price fails safely. Credit allocation prevents spending the same return funds twice; an unissued/expired exchange can explicitly switch to refund review.

Finance-only refunds require exact amount, reason and final confirmation dialog. Durable operation fingerprints and local provider receipts support partial caps, same-key replay, pending/failure/lost-response reconciliation and mismatched-evidence holds. Staff can inspect reason/provider reference; customer views omit internal fields. All outcomes remain sandbox_* and move no real money. The approved test-only return/inspection/exchange/refund policy fails closed outside local/testing.

Verification: **141 backend tests / 1,148 assertions PASS**, including 31 Phase 8 cases. **73 browser groups PASS**: Phase 8 16, fulfilment 14, checkout 13, shopping 15, catalogue 15; no page errors. Real queue workers/private email copies and production Next exercised. Four independent-process SQLite checks PASS: overlapping returns, remaining refund cap, simultaneous replay and last exchange stock. Both frontend format/lint/types/production builds PASS; Pint, strict Composer validation and dependency audit PASS. Reviewed account 390/768/1440 reflow, refund dialog and mobile finance/guest return evidence. Initial failed runs were corrected/superseded by passing runs. Normal .next references restored after QA.

Applied additive migrations 000004 (batch 6) and 000005 (batch 7): 15 new empty development tables plus delivery-proof columns, 67 total. All original rows across 51 non-migration tables unchanged; migrations gains only its two expected records. Existing environments, PDFs and dependency lockfiles preserved; no source removed, no development QA customers/orders/stock/returns/refunds fabricated. No commit, push, deployment or external transaction performed.

Boundaries: actual Stripe purchase/refund provider integration and exchange top-up completion remain deferred. Sandbox receipts are not Stripe evidence. Further returns on credit-funded replacements require staff review; automatic eligibility currently requires original verified paid orders. Merchant live policy/tax/delivery approval, external transactional mail, real courier and production/database/load/retention/assistive validation remain pending. Inspection/exchange/refund status appears in timelines; outbound status mail is not implemented. SQLite verified; MySQL/PostgreSQL unverified. These boundaries remain explicit in PHASE8-REPORT.md.

Exact startup/migration/private email/manual and automated testing commands: **PHASE8-SETUP.md**. Contracts/permissions: **PHASE8-API.md**. Screens/results and unresolved live criteria: **PHASE8-REPORT.md**; evidence: docs/verification/phase8. Next planned phase is **Phase 9: Content & marketing**, not started.

## 2026-10-27 — Database-backed admin Overview

User-requested follow-up; Phase 9 remains unstarted. Replaced Overview's hardcoded example metrics, sales chart, attention counts and sample orders with protected GET /api/v1/staff/overview. Empty stores show zero counts and explicit empty states. Catalogue summaries show non-archived products, published/draft counts, active variants and available units from active locations after reservations. Latest ten real products link to their editor. Actual order counts and recent records use fulfilment.view; recorded gross paid totals are finance.view-only, currency-separated, exclude unpaid/exchange credit, and are explicitly before refunds (sandbox payments remain test records). No net-sales/return-rate claims. Each response section enforces existing staff permissions; MFA/session/origin protections and no-store cache headers apply. API failure is shown as an error, never silently replaced by zero. Initial mount/navigation and Refresh overview load current totals; no automatic background polling.

Verification: 7 isolated backend tests PASS / 40 assertions (empty store, catalogue state changes, stock eligibility/reservations, paid totals/currency separation, role visibility, anonymous and expired sessions); admin formatting/lint/types/production build PASS. Five real-browser checks PASS using a separate SQLite database: MFA sign-in and empty overview, protected product creation and stock adjustment updating counts (1 draft, 10 units), desktop/mobile reflow, actual product editor link, failed refresh retaining previous figures with explicit warning. No browser page errors. No development accounts/products/stock/orders/environment files reset or seeded; no new migration/dependency, no Stripe transaction. Other unfinished workspaces remain clearly labelled previews.

## 2026-10-27 — Catalogue form design refinement

Refined product, collection and category form presentation at the user's request. Scoped admin-only forms.css adds consistent section typography, rounded cards, field spacing, input/focus states, quieter segmented catalogue navigation, and grouped product actions. Collection editing has a distinct heading/status, useful input examples and hints, a clear visibility setting and save footer, plus responsive contextual guidance. Product sections, variant cards, measurement rows, media controls and search preview use the same form styling. Existing shared UI components, labels, permission gates, backend validation, draft/save/upload/publish behaviour and stored data are preserved. No new dependency or database migration; Phase 9 remains unstarted.

Verification: admin format/lint/TypeScript/production build PASS. Existing isolated catalogue browser suite PASS, 15 groups: collection creation/edit/order, four-variant product saving, invalid price/SKU feedback, real image upload/invalid image handling, saved preview, publish/unpublish/redirect, stock adjustments/transfers, archive, CSRF and read-only role protection. No browser page errors. Additional collection screenshots/reflow checked at 1440, 1024 and 390px; product editor at 1440/390px plus 720px equivalent zoom reflow. Reviewed desktop/mobile screenshots. QA uses a separate SQLite database and private runtime; development accounts/products/stock/settings were not changed.

## 2026-10-27 — Filter alignment and admin form icons

Aligned catalogue search input, publication select and Search products action on one desktop baseline with equal 44px heights. Filters stack full-width below 760px. Added local decorative SVG background icons to admin text/search/email/password/number/date/file inputs, selects and textareas, with consistent 40px text inset and control-type icons. Native labels, DOM controls, keyboard behaviour, validation and checkbox/radio affordances are preserved. Forced-colour mode hides background icons and restores standard padding; textareas receive visible keyboard focus. Applies to admin workspace and access forms; storefront/shared canonical components are unchanged.

Verification: admin formatting, lint, TypeScript and production build PASS. Fifteen isolated catalogue browser groups PASS, including product/collection saves, image upload, preview, publication, inventory, archive and permissions. Additional geometry assertions confirm equal filter top coordinates and 44px heights; computed-style checks verify icons and 40px text spacing on product text/file/select/textarea controls. Desktop/mobile collection and filter screenshots reviewed; product keyboard focus checked. No browser page errors. An initial icon assertion incorrectly included collection checkboxes; corrected the test selector and the clean full rerun passed. Existing development data and credentials preserved; no database migration or new dependency.

## 2026-10-27 — Ecommerce typography across storefront and admin

User requested an Amazon-like ecommerce font style. Replaced the shared Georgia/Times editorial family with the shared system sans-serif stack (-apple-system, BlinkMacSystemFont, Segoe UI, Arial, Helvetica, sans-serif). Both body and heading typography now use the same family; headings use stronger weights and adjusted line height/tracking. Reduced oversized empty-state text, and applied consistent sans-serif weights to collection tiles and product previews. Updated canonical shared/ui/tokens.css and synchronized generated copies in both applications. Fonts use installed operating-system families; no external font request, new package or licensed Amazon font is included. Existing colours, icons, controls and data are retained.

Verification: storefront and admin format/lint/TypeScript/production builds PASS. Eight targeted browser checks PASS for admin sign-in/overview, collection desktop/mobile, storefront home desktop/mobile, shopping and customer account. Computed fonts contain no Georgia/Times; layouts fit at 390 and 1440px, with no browser page exceptions. Desktop/mobile screenshots reviewed. Browser admin fixtures use an isolated database; no development data or configuration changed. Full commerce regression was not repeated for this CSS-only change. Phase 9 remains unstarted.

## 2026-10-27 — Simplified collection → product and photos entry

User requested a single product form with image selection before saving. Saved collections now offer Add products to this collection, opening a new product with that group selected. Product images are visible in the initial editor beside the normal product information; multiple files can be selected before a draft exists, previewed, described and removed. Save draft saves the private record then uploads queued photos sequentially using the existing versioned/permission-protected image endpoint. The first saved image remains primary; selected photos start shared, with saved-image per-variant linking/order/archive controls retained. Save & add another clears product-specific fields after successful saving and keeps collection selection for faster repeated entry. New slugs are suggested from the title until manually edited; blank SEO fields derive from title/description. No fabricated stock, product measurements or materials.

Partial upload failure preserves the saved draft and confirmed images, retains unconfirmed/remaining local files, and blocks preview/publication while selected images remain. Retrying definite failed uploads does not repeat confirmed files. Conflict/uncertain responses still require latest-record review; there is no new backend idempotency adapter. Pending image object URLs are revoked on removal/success/unmount, and navigation warns about unsaved files. File type/byte/count checks complement existing server decoding/dimension/pixel/publication validation. Publication remains explicit and requires completed merchant content. Stock continues through the existing Inventory tools. No dependency, migration, API contract, environment or development business data changed.

Verification: admin formatting/lint/TypeScript/production build PASS. Updated permanent shared/tests/catalogue.cjs passes 19 grouped real-browser checks in an isolated SQLite database, including four new cases for collection preselection/upfront multiple photos, partial-upload recovery, duplicate prevention after a definite failed photo and save-and-add-another. Existing full product/variant/media/publication/inventory/permissions/archival checks pass; no browser page errors. Desktop/mobile layouts fit. Initial test setup mixed file paths/buffers and was corrected; a later run waited on an offscreen lazy-loaded image, so only that owned QA process tree was stopped and the harness now scrolls the image into view. Final clean full run passed. Guide: docs/CATALOGUE-ENTRY.md; historical PHASE4-SETUP.md links to the updated workflow. Phase 9 remains unstarted.

## 2026-10-27 — Product row edit/delete icons

Replaced visible Edit/View product-name action text with compact icon-only controls: pencil for editing, eye for read-only roles, red bin for authorised deletion. Added shared canonical edit/trash/eye SVG icons and synced both applications. Each control retains a product-specific accessible name, tooltip, visible focus and 44px target. Delete opens a named confirmation dialog and calls the existing version-protected archive endpoint; it does not hard-delete historical records. Current list refreshes after confirmation; archived rows remain marked Retained archive. Read-only staff never receive the delete control. No product data or credentials in the development database were changed.

Verification: admin format/lint/TypeScript/production build PASS; storefront typecheck PASS for shared icon additions. Five targeted real-browser checks PASS in an isolated database: icon markup and accessible names, pencil editor navigation, cancellation preserving product, confirmed archive removing current row while retaining archive record, support-role view-only controls. Desktop/mobile layouts fit; no page errors; screenshots reviewed. Initial support-row assertion ran before asynchronous data arrived; added an explicit row wait and the clean rerun passed. No migration/dependency/API contract changes; Phase 9 remains unstarted.

## 2026-10-27 — Product IDs, 10-per-page listing and collection filters

Added existing immutable numeric product IDs as the first staff catalogue table column; IDs are not renumbered and no database rows/foreign keys were changed. Staff catalogue pagination now returns 10 products per page. Simplified the product list into All products plus count-bearing collection/category buttons. Selecting a group filters on actual membership and resets to page 1; Add product inherits that selected group. All products requests status=all, presenting draft/published/archived records together. Status selection is tucked inside the expandable Filters panel and composes with collection/search; Clear filters restores All products. Archived rows stay retained without write actions. Public catalogue publication rules remain unchanged. Mobile collection buttons scroll inside their own row; search/filter controls and IDs reflow. Product loads ignore stale prior-filter results using generation invalidation. Optional staff collection_id and status=all query support, plus counts in staff collections response; prior no-status API reads remain current-only for compatibility.

Verification: two new isolated backend cases PASS / 47 assertions for 23-record 10/10/3 paging, stable IDs, search, archival, group membership, status composition, counts, invalid collection IDs and public archive privacy. Admin formatting/lint/TypeScript/production build PASS; PHP formatting PASS. Earlier focused ID/pagination browser run passed four groups; final combined collection-filter browser run passed seven groups with actual 23 isolated records: All products and IDs, collection 10/3 pages, collection-switch page reset/no mixed results, status archive/clear, empty group, mobile fit and combined search retaining ID. No page exceptions; desktop/mobile screenshots reviewed. Existing development products, categories, images, accounts and secrets were not changed. No migration/dependency changes; Phase 9 remains unstarted.

## 2026-10-27 — Storefront collection menu

Added a white single-row uppercase collection/category menu below the main storefront header, matching the user's reference style. Shop all links to the full catalogue; active groups link to their collection pages in admin navigation-position/ID order. Current collection has an underline/aria-current marker. Long menus scroll horizontally within the row; the mobile drawer uses the same active groups. Public GET /api/v1/catalogue/navigation returns an allowlisted, no-store list of active groups, including newly created empty groups. Existing /catalogue/collections discovery remains restricted to groups with eligible published products. Empty active collection pages now resolve via navigation data and render the existing empty listing instead of 404; empty groups remain noindex. Draft/private product visibility is unchanged. Root layout fetches live navigation per request; refresh the storefront after changing active/title/order in admin. API outage retains Shop all and existing recovery messaging.

Verification: full PublicDiscoveryTest suite PASS, 8 tests / 71 assertions, including new active/hidden/empty/order/update navigation checks and draft privacy. PHP formatting PASS; storefront formatting/lint/TypeScript/production build PASS. Six isolated production-Next browser groups PASS: correct active order, empty collection routing/current marker, new/hide changes on refresh, mobile menu/drawer, large-list overflow within menu row and API-down Shop all fallback. No page exceptions. Desktop/mobile screenshots reviewed. QA next-env.d.ts and tsconfig.json restored exactly after separate .next-qa build; normal storefront typecheck passes. Existing development collections/products/accounts/environments were preserved; no migration/dependency changes. Phase 9 remains unstarted.

## 2026-10-27 — Collection → Category → Products hierarchy

User requested only collections in the public menu and categories nested within them. Added nullable self-FK catalogue_collections.parent_id with a restrictive parent-delete constraint. API validation requires categories to have top-level collection parents, rejects self/category parents and root demotion with children, preserves version checks, and locks the group tree in ID order. Category moves retain product memberships. Staff/root collection counts and product filters aggregate direct legacy assignments plus child categories without duplicates. New product editor choices are categories labelled with their parent; selected old assignments remain visible for compatibility. Existing direct collection API memberships remain supported, and unassigned old categories are shown for explicit parent selection rather than guessed. Categories under hidden parents are excluded from public navigation/routes/metadata; published product privacy remains enforced.

Admin group list displays hierarchy/parent, pencil controls and Add category per collection; category editor offers Parent collection and Add products to this category. Only top-level active collections are in navigation, with active categories in a keyboard-operable expandable submenu and nested mobile drawer. Collection and category routes resolve the nested navigation data. Product images, prices, publication controls, IDs and ten-per-page lists remain functional. Updated docs/CATALOGUE-ENTRY.md, architecture notes and permanent catalogue browser journey for the new category-first flow. No dependency changes or Phase 9 work.

Development migration 2026_10_27_000001 applied after a private SQLite online backup. Compared original columns/rows in 62 non-runtime tables: unchanged; parent_id added, all existing parent values remain null pending merchant selection. Normal cache/staff_sessions activity changed while the development apps were open; those runtime tables were not reset. Existing credentials/products/stock/assignments retained. Private backup is kept outside deliverables.

Verification: full backend suite PASS — 153 tests / 1,277 assertions, including parent validation, stale writes, category move, deduplicated aggregation and inactive-parent privacy. Admin and storefront format/lint/types/production builds PASS; PHP formatting PASS. Six isolated production-Next/admin browser groups PASS: collection/category creation, category-preselected product + photos + publication, collections-only top row/category submenu, parent aggregation, mobile nested navigation and moving category while preserving product. No page exceptions; screenshots reviewed. QA frontend source configuration restored; normal typecheck verified. Initial targeted checks found missing default fields on a newly saved group and a legacy orphan-category fixture; refreshed save DTOs and updated the fixture to have its required parent. Final full regression passes.

## 2026-10-27 — Hierarchical filters in the catalogue heading

Replaced Products / Collections & categories browse tabs with All products and top-level collection filters at the requested location. Choosing a collection reveals a separate row containing All in [collection] and only that collection's categories. Choosing a category filters its products; changing collection resets the child selection, search, status and page. All products clears group selection and returns the complete list. Counts, numeric IDs, ten-per-page paging, product actions and existing API hierarchy aggregation are retained. Group editing is accessible separately through Manage collections (View collections for read-only roles). Updated permanent catalogue browser journey labels. No backend/schema/dependency or development-data changes.

Verification: admin formatting/lint/TypeScript/production build PASS. Six targeted real-browser groups PASS using 23 isolated products across two collections/three categories: root-only filters, collection expansion, sibling category isolation, collection switching/10-per-page reset, All products reset and management/mobile fit. No page errors; screenshot reviewed. Existing full backend suite was not repeated for this frontend-only change; its previous 153-test passing result remains historical. Phase 9 remains unstarted.

## 2026-10-27 — Add category directly from selected collection

Added Add category beside the selected collection's category heading, including empty collections. Opens a modal with a fixed/read-only parent collection, title/auto-suggested editable unique slug, description, navigation position and public-active setting. Saves via the existing hierarchy-validated staff API and refreshes category filters in place without navigating to management. Errors preserve modal input; cancel makes no changes. Only catalogue managers see the action; server permissions remain authoritative. No migration, dependency, backend contract or development data changes.

Verification: admin format/lint/TypeScript/production build PASS. Five isolated real-browser checks PASS for fixed selected parent/slug suggestion/cancel, successful create and immediate chip refresh, duplicate-slug preserved-input feedback, parent switching/mobile layout and read-only UI/API denial. No page exceptions; screenshot reviewed. Full backend suite was not repeated for this frontend-only addition; previous 153-test result is historical. Phase 9 remains unstarted.

## 2026-10-27 — Collection selection displays category cards only

Adjusted admin browsing per user clarification: selecting a collection now displays its category cards and Add category, without a product table/search area. Removed All in [collection] action from the category row. Selecting a category displays its product table; All products displays the complete list. Empty collections show an explicit category empty state; inline category creation refreshes cards. IDs, 10-per-page product paging, management and existing assignments remain unchanged.

Verification: admin format/lint/TypeScript/production build PASS. Four isolated real-browser checks PASS: no product controls in collection view, category-to-product navigation with 10/1 paging and IDs, empty collection plus inline creation, and All products reset/mobile fit. No page exceptions; desktop screenshot reviewed. No backend/schema/dependency or development data changes. Phase 9 remains unstarted.

## 2026-10-27 — Edit/delete collections and categories

Added reusable pencil/bin group actions in collection/category management and the catalogue browse view: selected collection heading and category cards. Edit modal supports title, unique slug, description, navigation position, active visibility and category parent moves. Delete requires explicit confirmation and uses a new permission-protected versioned archive endpoint. Non-empty collections must have their categories moved or archived first, enforced by both UI and backend. Groups use additive soft deletion; original group IDs/slugs, memberships and products are retained. Archived groups leave staff browsing/public navigation; raw public discovery/publication paths exclude archived groups. Direct catalogue writes reject new assignments to archived groups. Existing public products, orders, media and stock are not deleted. Only catalogue managers see mutation actions, with backend permissions/CSRF/session protections unchanged.

Migration 2026_10_27_000002 added deleted_at after a private SQLite online backup. Final comparison found 60 non-runtime/business tables unchanged including products, stock and memberships; all 8 original group rows retained. Concurrent group active/version/archive timestamp changes and append-only staff audit events appeared through the running development admin while verification ran; these were left intact. Private database backup stays outside deliverables. No dependency changes; Phase 9 remains unstarted.

Verification: full backend suite PASS, 154 tests / 1,292 assertions, including confirmed archive, non-empty parent rejection, stale/missing confirmation, retained memberships/products, public archive hiding and read-only denial. Admin formatting/lint/TypeScript/production build PASS. Six isolated browser groups PASS: category rename/reparent, non-empty collection deletion block, category cancel/confirm with product retained, collection rename/archive/mobile confirmation, management icons and read-only UI/API protection. No page errors; screenshot reviewed. Initial category-action placement produced a TypeScript error, corrected before the final checks. Group deletion is archival with no restore UI in this change; product assignment history stays retained and products remain in All products.

## 2026-10-27 — Permanent deletion of unused products

User requested full removal instead of retained archive. Product-row bin now opens explicit permanent-delete confirmation, including for archived rows. New protected POST products/{id}/delete requires catalogue.manage, normal staff MFA/session/origin/CSRF, exact version and DELETE confirmation. ProductDeletion locks the product, variants and balances; published records must be unpublished first. Orders, reservations, immutable stock movements, nonzero balances and exchange-return references block permanent deletion. Existing Archive remains available in the product editor for historical products. Unused deletion removes product, variants, options/values, slugs, memberships, media metadata, zero stock balances and customer saved-item references. Image directories are validated as UUID direct children inside the configured media root before deletion, revalidated after transaction commit, and removed; a cleanup failure is explicitly reported instead of hidden. Append-only security audit retains only deletion event/reference. IDs are not reset/reused. Bag/browser-local unavailable identifiers are handled by existing catalogue validation.

Verified full backend suite PASS: 156 tests / 1,318 assertions, including actual media directory cleanup, archived-product removal, stale/missing confirmation rejection and stock history protection. Admin format/lint/TypeScript/production build PASS. Five isolated browser groups PASS: icons/edit, cancel preserves, confirmed archived-product permanent deletion and refreshed list/counts, read-only UI/API behaviour. No browser page errors. Initial test duplicated a collection slug within one fixture; corrected the fixture and the full suite passed.

A private development SQLite snapshot was retained before the requested specific removal. Guarded local operation checked ID 1 and exact title/status; ID 1 was already absent by execution time (the running admin had removed it), so no second deletion occurred. Existing other records were not reset. No migration or dependency changes. Product bin semantics now permanently delete unused records; group deletion still archives. Phase 9 remains unstarted.

## 2026-10-27 — Requested Women categories

Added the ten user-specified categories under existing Women (slug women), ordered 1–10 as in the supplied screenshot: Knitwear, Coats & Jackets, Dresses, Boots, Tops & T-Shirts, All Denim, Trousers, Pyjamas, Skirts, Lingerie. Unique womens-* slugs, short editable category descriptions and active visibility inherited from Women. Used existing validated saveCollection logic in one local database transaction; conflicting existing slugs would abort without partial changes. Private SQLite snapshot retained before the write. No reference images copied, no product photos fabricated, no new products or inventory seeded. Verified live public navigation contains exactly these ten nested categories and original product/variant/membership/stock records match snapshot. No source/dependency/migration change; Phase 9 remains unstarted.

## 2026-10-27 — Requested Men categories

Added the ten user-specified Men categories in screenshot order: Suits, Shirts, T-Shirts & Polos, Jeans, Trousers, Knitwear, Coats & Jackets, Footwear, Underwear, Nightwear. Unique mens-* slugs, editable descriptions, positions 1–10 and active visibility inherited from existing Men collection. Used existing validated group saves in a single transaction after private SQLite snapshot; duplicate/conflicting slugs fail rather than overwrite. Verified all ten nested categories in live public navigation; existing products/variants/memberships/stock unchanged. No source/dependency/migration or image changes. Phase 9 remains unstarted.

## 2026-10-27 — Category image upload and storefront thumbnails

Added an optional category image to quick Add category, category pencil editing and Manage collections. Administrators can upload with the initial category save, preview, replace, remove and edit alternative text. Category cards display saved thumbnails; storefront collection pages show clickable circular category thumbnails with a text fallback when no image exists. Management saves now refresh the top collection filters immediately.

Uploads accept still JPEG/PNG/WebP, at most 8MB and 24MP, with each side at least 200px. Existing Sharp processing creates a 640px WebP thumbnail; only that processed thumbnail is retained in the database, with temporary originals removed. Image bytes are hidden from JSON. Versioned staff/public image routes enforce existing permissions or active category/parent visibility; replaced/removed URLs stop resolving. No new dependencies.

Applied additive migration 2026_10_27_000003 after a private SQLite online snapshot. Preservation comparison: 60 non-runtime/business tables unchanged; all 35 original group rows retained, with only additive image columns. No demo images/products added to the development database. Private backup stays outside deliverables.

Verification: full backend suite PASS — 157 tests / 1,341 assertions; image-specific test covers corrupt upload, visibility, conflict, replacement and removal. PHP formatting PASS. Admin and storefront formatting/lint/TypeScript/production builds PASS. Six isolated browser groups PASS: management create with photo, saved card thumbnail, desktop/mobile storefront links, pencil replacement/alt, removal/fallback and quick creation with photo. No page exceptions; screenshots reviewed. Normal storefront configuration restored and typecheck passes after isolated QA. Phase 9 remains unstarted.

## 2026-10-27 — Category thumbnail rejection follow-up

The supplied screenshot showed the former combined image-worker validation message; the original image was not attached, so its exact rejection cause cannot be confirmed. Relaxed only category thumbnails to a 64px minimum per side (400px recommended). Product photos retain their 200px minimum. Worker failures now distinguish dimensions, unsupported format, animation, decoding and missing processing dependencies; category errors give specific corrective guidance. Existing maximum 8MB/24MP limits remain. No database/data changes.

Admin format/lint/TypeScript/production build PASS. Added regression coverage using real generated 120px category thumbnails and 32px rejection with exact dimension feedback. Initial assertion used the wrong API error envelope; corrected to error.details before the final run.

Final regression PASS: 157 backend tests / 1,343 assertions, including successful 120px category upload and precise 32px rejection. PHP formatting and admin checks passed. Exact user file remains untested because only the error screenshot was supplied.

## 2026-10-27 — Confirmed Windows image worker startup fix

Tested the supplied 06_Knitwear.webp: valid still WebP, 1254 x 1254px; direct Sharp decode/resizing succeeds. A temporary loopback-only diagnostic in the existing PHP server reproduced exit 134 and Node's ncrypto::CSPRNG initialization assertion before image decoding. Node inherited incompatible OpenSSL settings from Herd PHP. Category and product Process invocations now remove OPENSSL_CONF and OPENSSL_MODULES only from the child environment, leaving PHP/global settings unchanged. Runtime crashes are reported as processor unavailability rather than an invalid image.

Verified the exact WebP successfully generates 640/1280/1920 WebP outputs through the same running PHP server with the corrected child environment. Temporary diagnostic routes/entry-point code and output directories were removed immediately; source image and business database unchanged. Full backend suite PASS (157 tests / 1,343 assertions) after production fix. Added explicit conflicting-environment regression for category and product uploads: targeted test PASS (26 assertions). PHP formatting PASS. No frontend/dependency/schema changes. The previous image dimensions adjustment was not the cause of this confirmed runtime failure.

## 2026-10-27 — Complete Laravel Windows worker environment correction

The earlier direct-worker verification did not cover Laravel's bootstrapped environment, so the preceding OpenSSL diagnosis/fix was incomplete. Reproduced CategoryImage::encode failure inside the existing PHP server after full Laravel bootstrap: Node exit 134/CSPRNG assertion. Symfony's default environment filtering omitted SystemRoot/WINDIR. Explicitly preserving those OS variables fixes the startup crash. Both category/product workers now use ImageWorkerEnvironment::values(); child OpenSSL isolation remains, PHP and global environment are unchanged. Private bounded worker failure logging added for future diagnosis.

Verified the user's exact 06_Knitwear.webp through the actual category encoder with Laravel bootstrapped in the running localhost:8000 PHP server: success, processed thumbnail 34,088 bytes. Temporary loopback diagnostic removed and verified absent from public/index.php/routes. Source image/database untouched. Expanded regression removes Windows root variables from $_SERVER/$_ENV while retaining process OS environment and poisons inherited OpenSSL settings; both category and product API uploads succeed. Full backend suite PASS — 157 tests / 1,344 assertions. PHP formatting PASS. No frontend/dependency/schema changes.

## 2026-10-27 — Hover collection dropdown with category images

Storefront collection navigation now opens a full-width dropdown when hovering a collection such as Women. Displays every active child category with its saved circular thumbnail and category link; missing images use a letter placeholder. Includes Shop all collection and Close controls. Moving between menu and dropdown keeps it open; hovering another collection switches contents and leaving closes it. Collection links retain normal navigation. Keyboard focus opens, Escape closes and returns focus to the toggle, focus leaving closes, and touch users can use the existing arrow button. Responsive three-column mobile grid has bounded vertical scrolling. Uses existing public navigation/images; no backend/dependency/schema or development-data changes.

Verification: storefront targeted formatting/lint/TypeScript/production build PASS. Five production-Next/Edge browser groups PASS using read-only existing catalogue data: all ten Women categories and ten uploaded images, pointer movement/switch/leave, keyboard/Escape/toggle/close, category navigation, touch layout/no page overflow and decoded mobile thumbnails. No page exceptions; desktop/mobile screenshots reviewed. Temporary test server stopped; existing servers left running. Phase 9 remains unstarted.

## 2026-10-27 — Remove collection menu arrows

Removed separate arrow icons/buttons beside Women, Men and other collections. Names with categories are now the dropdown controls: hover on desktop, tap/click the name on touch, Enter/Space/ArrowDown by keyboard; Escape closes and returns focus to the name. Shop all in the dropdown opens the parent collection. Collections without categories remain direct links. Maintained matching typography, spacing, responsive grid and public image visibility.

Verification: targeted formatting, lint, TypeScript and production build PASS. Five production browser groups PASS covering hover, switching/leaving, keyboard open/Escape, category navigation and mobile tapping with all ten Women thumbnails decoded. No page exceptions. No backend/data/dependency changes.

## 2026-10-27 — Requested Boys categories

Added ten active categories beneath existing Boys in screenshot order: Sets & Outfits, T-Shirts, Jeans, Sweatshirts & Hoodies, Joggers, Coats & Jackets, Boots, Trainers, Fleeces and Pyjamas. Unique boys-* slugs, editable descriptions and positions 1–10. Used existing validated catalogue saves in one local transaction following a private SQLite online snapshot. No screenshot images extracted, products seeded or stock changed; upload original category images with the existing pencil editor.

Verified the live public navigation returns all ten nested categories in the requested order. Compared existing groups and catalogue/stock/order/return records to the snapshot: unchanged. No source/dependency/migration changes; Phase 9 remains unstarted.

## 2026-10-27 — Requested Girls categories

Added ten active categories beneath existing Girls in screenshot order: Sets & Outfits, Tops, Leggings, Sweatshirts & Hoodies, Joggers, Dresses, Jeans, Coats & Jackets, Boots and Pyjamas. Unique girls-* slugs, editable descriptions and positions 1–10. Existing validated catalogue saves ran in one local transaction after a private SQLite online snapshot. No category images extracted from screenshot; images can be uploaded through the existing category editor. No products or stock seeded.

Verified all ten categories in order through live public navigation. Snapshot comparison: existing group rows and 26 catalogue/stock/order/return tables unchanged. No source/dependency/migration changes; Phase 9 remains unstarted.

## 2026-10-27 — Requested Baby categories

Added ten active categories under existing Baby in screenshot order: Sleepsuits, Bodysuits, Rompersuits, Sets & Outfits, First Shoes, Dresses, Knitwear, Occasionwear, Accessories and Socks & Tights. Unique baby-* slugs, editable descriptions and positions 1–10. Existing validated catalogue saves ran in one local transaction after a private SQLite online snapshot. Images can be uploaded through the category editor; no screenshot images extracted or products seeded.

Verified all ten nested categories in requested order through live public navigation. Existing group rows and 26 catalogue/stock/order/return tables match the snapshot. No source/dependency/migration changes; Phase 9 remains unstarted.

## 2026-10-27 — Requested Beauty categories

Added ten active categories beneath existing Beauty in screenshot order: All Gifting, Fragrance Gifts, Skincare Gifts, Makeup Gifts, Bath & Body Gifts, Electricals & Hair, Gifts for Her, Gifts for Him, Beauty Boxes and Bath & Body Works. Unique beauty-* slugs, editable descriptions and positions 1–10. Existing validated catalogue saves ran in one local transaction following a private SQLite online snapshot. No reference images copied or products seeded; category images remain editable in admin.

Verified all ten categories in order through live public navigation. Existing groups and 26 catalogue/stock/order/return tables match the snapshot. No source/dependency/migration changes; Phase 9 remains unstarted.

## 2026-10-27 — Approved Women collection design with automatic merchandising

Implemented the approved Women landing layout at /collections/women: generated autumn campaign hero with accessible HTML copy/CTA, live category circles, olive editorial strip, Trending now, Your wardrobe refreshed, The finishing touches, campaign story, New arrivals and full filtered/paginated catalogue. Existing header/footer/newsletter preview retained; other collection/category routes retain their layouts. Responsive desktop/tablet/mobile styling with reduced-motion support. Recreated campaign photography through the built-in image generation tool using the approved mockup; saved asset and complete generation prompt documented in WOMEN-COLLECTION.md.

Products, images, prices, stock and group data use the existing public catalogue. Added trending sort: units in confirmed, paid GBP orders during the prior 30 days, newest first-publication date/product-ID fallback. Drafts, archives and inactive hierarchy branches remain excluded; no order data or score leaks. New arrivals uses newest publication dates. Category editorial sections use live admin category images/descriptions and navigation order. Visible pages refresh every 60 seconds and on tab return. No sample products are inserted; empty/error states remain truthful. Campaign asset is fixed, while catalogue sections update automatically; this does not add banner CMS, live newsletter or deferred Stripe setup.

Verification: PHP formatting PASS; full backend suite PASS — 158 tests / 1,352 assertions, including trending expiry, paid-state/cancellation rules, hierarchy scoping, draft privacy and newest fallback. Frontend formatting/lint/TypeScript/production build PASS. Six isolated production-browser groups exercise the approved layout/images, one h1, hero/category links, automatic new-arrival refresh, sales-driven ranking, unpublished exclusion, responsive mobile and empty catalogue. Desktop/mobile screenshots reviewed with isolated fixtures and the actual development catalogue; actual Women currently has zero published products. Business data untouched; temporary test servers stopped, original servers retained. No dependency/migration changes. Phase 9 remains unstarted.

## 2026-10-27 — Collection menu names navigate to landing pages

Corrected arrow-removal interaction: collection names had become dropdown-only buttons, so clicking Women could close its hover panel without opening the new Women page. All collection names are now real Next links. Desktop hover still opens category images; click/tap/Enter opens the parent landing page. ArrowDown opens and focuses the category panel, Escape returns focus to the parent link. No arrows restored. Mobile users can browse categories on the collection page or through the existing shop drawer.

Verification: targeted formatting, lint, TypeScript and production build PASS. Five production-browser checks PASS: arrow-free hover categories, Women click reaches new hero/page, other collection links, keyboard focus/navigation and mobile tap/no overflow. No page exceptions. No backend/data/schema/dependency changes.

## 2026-10-27 — Approved Men collection landing design

Implemented the approved Men mockup at /collections/men. Includes recreated seasonal hero, live category circles, olive strip, Styled for you, Trending now, Style that goes further campaign triptych, What to wear, New arrivals, Good pieces on repeat campaign and full filtered/paginated product catalogue. Reuses the existing editorial CSS, catalogue components and automatic refresh while preserving the Women page. Header/footer/menu links and explicitly labelled newsletter preview retained. Category tiles use actual admin-managed photos rather than fabricated product/editorial photos. Two campaign assets generated with built-in image generation using the approved mockup and saved inside storefront/public/campaigns/men; full prompts and usage in MEN-COLLECTION.md.

Published Men products, prices/stock/photos and active child categories are fetched live. Trending uses the previously implemented last-30-day confirmed paid sales ranking with newest fallback; no manual feature assignment needed. New arrivals shows the newest four. Visible page refreshes every 60 seconds/on tab return. Styled for you links six relevant existing categories; What to wear links Shirts, Knitwear and Suits. Hidden/removed categories are omitted. Campaign artwork remains fixed design content. No demo products or business data changed; actual Men currently has zero published products. No backend, dependency or migration changes; Phase 9 remains unstarted.

Verification: storefront format check, lint, TypeScript and production build PASS. Six isolated production-Next/Edge browser groups PASS: Men menu opens approved layout with decoded banners/ten categories, no draft leakage; hero/category navigation; automatic newly published product refresh; sales-driven rank change/unpublished exclusion; mobile no overflow; truthful empty catalogue. No page exceptions. Desktop/mobile screenshots reviewed with isolated fixture and live merchant catalogue. Initial test expectations retained the Women Boots slug and assumed immediate anchor URL update; corrected the harness to Men Footwear and awaited navigation. Temporary QA processes stopped; merchant data/servers preserved. Backend tests were not repeated for this frontend-only change; prior 158-test result remains historical.

## 2026-10-28 — Matching Boys, Girls, Baby and Beauty landing pages

Implemented four collection-specific editorial pages at /collections/boys, /collections/girls, /collections/baby and /collections/beauty. Shared configurable CollectionLanding component and collection-campaigns settings preserve the Women/Men design language: distinct generated hero, live category circles, olive strip, Trending now, six-category edit, finishing/gifting selection, campaign story, New arrivals and complete filtered/paginated catalogue. Generated four original campaign assets with the built-in image generation tool, saved in storefront/public/campaigns/{boys,girls,baby,beauty}/autumn-hero.png. Exact prompts in COLLECTION-CAMPAIGN-PROMPTS.json; full content rules, paths and local test instructions in COLLECTION-LANDING-PAGES.md. Existing Women/Men and category listing routes retained.

Active categories/images/order/descriptions, product photos/prices/stock and public product scope all come from existing catalogue APIs. Trending retains last-30-day confirmed paid sales ranking/newest fallback, New arrivals selects newest publication dates, and visible pages refresh every minute/on tab return. Editorial category selections omit hidden/removed categories. Empty/error sections remain truthful. Campaign content is fixed; no banner CMS, live newsletter or payment integration added. No backend/dependency/schema/business data changes; QA fixtures used private isolated databases only. Phase 9 remains unstarted.

Verification: frontend format/lint/TypeScript/production build PASS. All 24 isolated production-Next/Edge browser groups PASS (six per collection): menu to landing, decoded campaign/category/product images, one h1, draft/unrelated-product privacy, hero/category links, automatic newly published arrival refresh, sales-driven trending change/unpublished exclusion, mobile no overflow and empty catalogue without fake products. Initial Boys harness retained a transformed Men footwear expectation; corrected the expected category path, then all four journeys passed. No page exceptions. Additional read-only live-catalogue review confirms all four with ten real categories each, plus existing Women/Men routes still intact. Desktop/mobile screenshots reviewed. Temporary QA servers stopped; merchant servers and data preserved. Backend suite was not repeated for this frontend-only change; previous 158-test result remains historical.

## 2026-10-28 — Wise selected for future payments

Merchant changed the planned payment option from Stripe to Wise. Wise implementation is deferred until requested. This supersedes the earlier Phase 6 provider selection. Recorded current decision and pending flow/capability verification in PAYMENTS-DECISION.md. Historical Stripe sandbox scaffolding/tests remain untouched; no payment integration, credentials, live transactions, application code or database changes made in this documentation-only update.


## 2026-10-28 — Wise bank-transfer checkout completed; receiving details pending

User approved manual Wise bank-transfer checkout and requested completion with receiving details pending. Implemented customer GBP instructions, frozen encrypted receiving snapshots, unique references/deadlines, private guest access, inventory reservation/expiry and owner-only receipt confirmation with MFA/CSRF, exact amount/reference checks, unique transaction receipts and idempotent settlement. Admin confirmation records an audit entry; customer refresh reflects staff-confirmed payment. Existing fulfilment remains blocked until paid. Wise callbacks do not depend on Stripe, and Stripe events cannot settle Wise orders.

Default provider is Wise; actual development WISE_ENABLED=false. No merchant bank details were invented or real payment made. Historical Stripe orders retain their provider. Wise-funded orders cannot run simulated refunds; exchange orders now have explicit credit/top-up funding methods. Scheduler expires unpaid Wise orders once. Queue confirmation copies remain private/local.

Verification: backend 167 tests / 1,483 assertions passed, including 9 Wise-specific tests; Pint passed. Both frontends passed formatting, lint, type checking and production builds. Seven isolated production-browser acceptance groups passed (disabled setup, awaiting payment, ownership isolation, mismatch rejection, exact confirmation/stock settlement, support restrictions, expiry/mobile); no page errors or real payments. Synthetic QA data and servers were isolated and cleaned up. The additive migration was applied after a private SQLite backup. Verified 58 non-runtime business tables unchanged and no Wise receipts in development; owner permission addition is finance.confirm only.

Remaining: verified GBP receiving details, activation and real-transfer verification; approved live tax/delivery policy, external email/carrier, real refund reconciliation and exchange top-up payment. These do not block the implemented local workflow, but checkout remains disabled pending configuration as requested. Phase 9 has not started. See WISE-CHECKOUT.md for configuration and step-by-step local acceptance; PAYMENTS-DECISION.md and README updated.


## 2026-10-31 — MySQL/MariaDB preparation and preservation rehearsal

Authorized continuation keeps Laravel and preserves the current SQLite development setup while preparing for Hostinger Unlimited Web Hosting. Architecture and a private consistent SQLite/source/config backup were recorded before source changes. Hostinger documentation confirms its Web/Cloud MySQL interface uses MariaDB. Reused installed XAMPP MariaDB 10.4.32 in a separate loopback-only instance/data directory on port 3317; no existing XAMPP database/service was used or altered.

Implemented a safe consistent database:snapshot-sqlite command and preflight-first database:import-sqlite command. Import refuses nonempty business targets, wrong schemas/keys, missing evidence protections and non-InnoDB tables; preserves IDs, passwords, encrypted MFA/receipts and hierarchy/image data; verifies row hashes and all declared FKs in an atomic transaction; restores import-connection FK checks and leaves source untouched. Source sessions/cache/queue jobs are excluded. Product image files require a separate private media copy.

Corrected a confirmed 66-character generated catalogue unique-index name for MySQL's 64-character limit and return-unit migration rollback ordering (drop FK before its supporting index). Added an additive MySQL/MariaDB migration for 30 missing commerce/fulfilment/after-sales evidence triggers; existing four audit/stock triggers remain enforced. MySQL/MariaDB connection defaults now explicitly use InnoDB and UTC. Added secret-free environment guidance, a guarded PHPUnit MySQL profile, six data-transfer regressions, two true independent-process stock/payment races and an unexecuted GitHub CI matrix for Oracle MySQL 8.4/MariaDB 11.4. No frontend source changes, backend rewrite or Phase 9 work.

Verification: SQLite 167 tests / 1,483 assertions PASS. Full MariaDB profile 174 tests / 1,534 assertions PASS. Final JSON object/array preservation hardening then passed all six affected transfer tests / 40 assertions. PHP Pint PASS. Fresh and rollback migration paths exercised. Actual snapshot imported into isolated copy: 75 groups, one staff account and all existing business records; all 59 transferred table hashes/FKs verified. Original products count was zero. Health/navigation/product APIs and stored WebP image returned 200 with expected storefront CORS. Preservation check: all 60 checked source tables and existing .env unchanged; no real payments or external email.

Staging guide/template prepared in MYSQL-MIGRATION.md and docs/examples/backend-hostinger.env.example; README linked. Remote staging remains pending account-level trigger privileges, PHP/Node Sharp process capability, cron interval/queue validation and secure deployment access. Hostinger's import guide excludes triggers, so do not strip protections to force deployment. Attempted read-only computer-use inspection of the signed-in Hostinger Chrome dashboard; tool stopped because it could not confidently verify the browser URL. No further UI actions, remote deployment, DNS or nameserver changes occurred. Isolated test MariaDB was stopped after testing. Private snapshots and imported account copy remain outside deliverables; source app still uses SQLite and Wise remains disabled.


## 2026-11-01 — Hostinger native images and bounded cron adaptation

Merchant supplied support confirmation: trigger support provisional until a database/user exists; PHP process functions disabled; GD/Imagick available; minute cron allowed with bounded workers. Recorded architecture and private source/config/SQLite backups before implementation. Current Composer lock requires PHP>=8.4.1, so the reported Hostinger PHP8.3 configuration needs changing for both API and cron/CLI.

Added explicit MEDIA_IMAGE_DRIVER=gd adapter for shared hosting, retaining Sharp as the existing local default. Category and product upload APIs share the adapter and preserve the established storage/API contracts. GD validates still JPEG/PNG/WebP, byte/pixel/dimension and memory limits; rejects animated/truncated PNG/WebP containers; handles all eight EXIF orientations without requiring the EXIF extension; preserves alpha and writes validated metadata-free non-enlarged WebP derivatives. Category thumbnails remain database-backed. No schema or frontend changes.

Added hosting:tick, which uses a file lock across in-process maintenance and a limited queue drain (20 jobs, up to30-second worker max-time in a cooperative45-second budget), avoids repeated notification batches while backlog exists and skips overlaps. It invokes no schedule:run/work or process worker. Job timeout guarantees still depend on pcntl/host limits; max-time is cooperative. Existing private notification delivery remains private files/log, not external email. Added hosting:check capability diagnostics; storage probes are tiny private temporary files removed immediately, database checks are metadata-only.

Verification: GD/SQLite177 tests /1565 assertions PASS; GD/MariaDB185 tests /1620 assertions PASS; subprocess-disabled native/API/tick acceptance10 tests /82 assertions PASS. Legacy Sharp runtime177 tests /1499 assertions PASS with six GD-only skips. Eight hosting diagnostics PASS on isolated local MySQL-compatible copy with subprocess functions disabled. PHP Pint PASS. Added CI GD extension coverage but did not run remote CI. One test-fixture auth default and one memory-test setup assumption were corrected before final passing runs. Original Knitwear download no longer exists; generated formats and upload APIs were verified instead.

Preservation: all60 checked source tables and existing .env unchanged. System PHP configuration unchanged; portable official checksum-verified PHP8.4.27 used for QA. Isolated MariaDB stopped. No Hostinger deployment/settings, DNS/nameserver changes, real payments or external email. Current local setup remains SQLite/Sharp and Wise disabled. Updated HOSTINGER-COMPATIBILITY.md, MYSQL-MIGRATION.md, README, .env.example and private-staging template. Staging awaits actual PHP/GD selection, empty MySQL/user with verified grants, secure deployment access and deployed cron/upload acceptance. Existing tax/delivery, email/courier/refund launch blockers remain. Phase9 not started.

## 2026-11-02 — Hostinger staging migration and storefront build compatibility

Verified dedicated SSH host key against hPanel, backed up the existing public placeholder, installed the Laravel release privately and selected PHP 8.5. Production Composer installation/platform checks passed. Imported a consistent SQLite snapshot into the new Hostinger MySQL-compatible staging database: 75 catalogue groups, one staff account and existing supporting records. All imported row hashes and foreign keys verified; existing APP_KEY preserved for MFA/encrypted records. Source SQLite and local environment unchanged. All eight remote hosting checks passed, including GD/WebP and 34 evidence triggers. Private API kernel checks passed for health, navigation, products, storefront CORS and an imported category image. Environment and snapshot files have mode 600. Wise/Stripe disabled, mail remains private/local, no cron installed.

Merchant uploaded the storefront source ZIP through hPanel to the temporary maroon-heron-554439.hostingersite.com application. Initial managed build failed because server glibc 2.28 cannot load Next 16.4.2 native SWC (requires GLIBC_2.29), and the TypeScript config could not load. Prepared replacement storefront-hostinger-compatible.zip: next.config.mjs with the same application settings, build command next build --webpack, and experimental.cpus=2. No dependency versions, lockfile, business logic or local source files changed by packaging. This allows Next's WASM fallback without changing server libraries or disabling security protections.

Verified on the actual Hostinger Node 24.22.1 runtime in a separate private build directory using the installed matching dependencies. First Webpack attempt compiled and type-checked, then failed EAGAIN when Next tried 24 page workers. Final two-worker build passed with exit 0 and all 23 pages generated. Native SWC warnings are expected while WASM fallback is used. Replacement ZIP SHA256: a153eb2349fb022df70133670fbddef8d20e43c7f7928752bb71a4eadbeffa14. The original failing ZIP is superseded; future standalone packages must retain the mjs config, Webpack build and two-worker setting.

Pending: merchant re-upload of corrected ZIP/managed deployment; public API PHP routing and TLS; admin build/deployment; temporary-origin CORS alignment; authenticated end-to-end acceptance and bounded cron validation. Existing public root placeholder, live DNS and nameservers unchanged. Build success alone does not establish live catalogue connectivity or checkout readiness. No real payments, outbound mail or production commerce activation. Existing live tax/delivery, Wise receiving/verification, email, carrier and refund launch issues remain unresolved.


Runtime follow-up: isolated Next production server reported Ready in 257ms and loaded next.config.mjs successfully. A separate SSH curl process could not reach its loopback port; the temporary server was stopped by the test cleanup trap. HTTP runtime acceptance remains pending the managed hPanel deployment; do not report the smoke check as passed.


## 2026-11-02 — Restored domain and connected public Laravel API

Merchant/support restored the parent DNS zone and attached elvarqo.uk to the working Node storefront. Before this restoration the original parent PHP site directory had disappeared; SSH confirmed the private Laravel release and migrated database were intact. Public checks initially returned SERVFAIL; nameservers remained aster.dns-parking.com / helios.dns-parking.com. Merchant/support added the api DNS entry and created the separate api.elvarqo.uk PHP site.

Verified https://elvarqo.uk returns HTTP 200 with ELVARQO content. API PHP site initially used PHP 8.3. Merchant switched it to PHP 8.5 and activated SSL. Temporary, non-sensitive capability probes were removed immediately after use. Actual web runtime verified as PHP 8.5.12 with PDO MySQL and GD; origin HTTPS certificate validated without bypasses.

Backed up the API placeholder privately before connecting public_html/index.php and Laravel rewrite rules to the private 20261101-stage01/backend release. Configuration cached; all eight remote hosting checks passed. Public files include no environment, vendor, source snapshot or application key. Public API and robots response prohibit indexing. Checkout stays disabled; mail remains private/local. No application credentials changed.

Public HTTPS checks passed: /api/v1/health, /api/v1/catalogue/navigation and /api/v1/catalogue/products all HTTP 200; exact Access-Control-Allow-Origin https://elvarqo.uk; noindex header; one actual imported category image returned valid HTTPS image/webp with HTTP 200. Navigation contains six active collections and 60 child categories; database retains other nonpublic groups as imported.

Prepared isolated admin source without environment files, verified canonical UI, installed locked dependencies and passed TypeScript/Vite production build with VITE_API_URL=https://api.elvarqo.uk/api/v1. Initial sandboxed Vite process spawn was blocked; authorized execution outside the process sandbox passed. Prepared admin-hostinger-static.zip with SPA rewrites and noindex/robots. Admin hosting/DNS/TLS and authenticated acceptance remain pending. Local source/data/environment unchanged by these isolated build operations.

Requested merchant update the managed Node app NEXT_PUBLIC_SITE_URL and STOREFRONT_ORIGIN to https://elvarqo.uk and redeploy; awaiting confirmation. Full customer/admin browser acceptance, cron verification, production business policies and actual receiving/payment/email/carrier/refund configuration remain pending. Public storefront deployment is not production checkout readiness. Do not enable search indexing or checkout until the remaining launch checks are completed.


Remote maintenance follow-up: one hosting:tick completed successfully with private local notifications only; holding its lock caused a second invocation to skip as intended (PASS). Cron installation could not run because crontab is absent from the SSH PATH; no scheduled entry was installed, and prior cron state could not be read by this shell command. Minute scheduling must be configured in hPanel. Private backup/log directories were prepared. Command: /opt/alt/php85/usr/bin/php /home/u688505577/elvarqo/releases/20261101-stage01/backend/artisan hosting:tick. Keep this as the only app scheduler; do not add schedule:run or continuous queue workers.


Storefront follow-up: merchant updated the managed Node app's site and commerce origins to https://elvarqo.uk and redeployed. Public homepage and /collections/women both HTTP 200 with correct elvarqo.uk canonical links, no old temporary-domain references and noindex retained. API database health still PASS. Staff /csrf returns 200 with exact admin origin, credential CORS and a secure/HttpOnly/SameSite=Strict session; unauthenticated /overview correctly returns 401. No login/password/MFA state changed. SHA-verified admin static package transferred privately to the release; dedicated admin.elvarqo.uk PHP hosting entry requested from merchant and still pending.


Admin deployment completed: admin.elvarqo.uk DNS/valid HTTPS and new PHP site verified. Original public placeholder backed up privately, checksum-verified static build deployed. HTTP checks passed for root, /products SPA fallback and hashed JS/CSS; exact production API URL present and no localhost API reference. Background browser rendered the sign-in form. Browser verification caught three hardcoded localhost storefront links; introduced VITE_STOREFRONT_URL (local default preserved), updated the source .env.example and both components, and passed targeted formatting/lint plus isolated TypeScript/Vite production build. Rebuilt with https://elvarqo.uk, published assets then index atomically, and verified the browser wordmark now targets elvarqo.uk. Previous assets retained for rollback/cache. Existing staff credentials/MFA unchanged; authenticated acceptance awaits merchant sign-in. Added HOSTINGER-DEPLOYMENT.md with verified URLs, build settings, private layout/backups, local-vs-hosted database distinction, exact pending hPanel cron and remaining commerce launch gates. Checkout and search indexing remain disabled.
