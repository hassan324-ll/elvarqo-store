# ELVARQO Phase 6 implementation report

Date: 26 October 2026. Project: `C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO`.

**Phase 6 code is implemented and locally verified. The phase is not fully accepted yet: an actual Stripe hosted sandbox purchase, webhook delivery/replay and real provider decline/abandonment checks remain pending. The user explicitly requested finishing with sandbox capture pending.** No live payments or Phase 7 work was started.

## Delivered

- **6A — Bag/pricing:** persistent guest bag through a protected same-origin Next proxy; host-only HttpOnly cookie, hashed Laravel capability, guest isolation, quantity/removal/revision checks and a current header count. Laravel controls variant prices, stock availability, test discount, delivery and tax. Final quote hashes prevent stale-price checkout; browser-supplied prices are ignored.
- **6B — Transactions:** validated UK shipping/billing/contact details, atomic multi-location reservations, normalized immutable order items/addresses plus an aggregate pricing snapshot, retry-key fingerprints and separate order/payment/fulfilment states. A bag has at most one active checkout. SQLite IMMEDIATE transactions and conditional updates protect the last unit. A separate append-only automated stock ledger preserves Phase 4 staff history and actors.
- **6C — Stripe sandbox adapter:** hosted card-only Checkout via Stripe REST, test-key-only enable switch, persisted exact provider parameters/idempotency key, raw-body HMAC/timestamp verification, durable deduplicated event IDs and authoritative session reconciliation. Amount/currency/session/order/attempt/mode must match before payment confirmation. No card form or raw card storage exists in Laravel. This adapter has been contract-tested with HTTP fakes; it has **not** completed an actual Stripe transaction.
- **6D — Confirmation/recovery:** paid/pending/failure/expiry/review views; redirects cannot confirm payment; expiry releases stock only with provider evidence; ambiguous failures retain stock. Retry restores owned contact/address/delivery/discount details and requires a fresh quote. A transactionally inserted outbox feeds queued private sandbox confirmations, with a deterministic file/marker preventing repeated jobs from duplicating the local copy. MFA-protected read-only admin orders show states, snapshots, reservations and automated stock events. Safe rate/provider configuration is visible read-only; richer editors and order operations remain later phases.

## Design coverage

S13 bag, S14 contact/address, S15 delivery, S16 review/hosted-payment handoff, S17 persisted confirmation and S18 recovery. M04–M06 responsive bag/checkout/confirmation are implemented. A18/A22/A25/A26/A29 minimum configuration is represented by the minimal test discount/rate/tax/provider configuration and a safe read-only admin summary; full promotion/payment/settings editors are not claimed.

The user approved **Stripe sandbox / UK / GBP / test-only rates**. Test standard £4.95, express £8.95, tax 0, TEST10 gives 10% off merchandise above a £50 minimum. No merchant VAT treatment, real courier promise or live promotion has been inferred. Test totals, currency and assumptions are visible before payment. Newsletter, editorial policies and customer accounts keep their existing later-phase boundaries.

## Verification results

| Check | Result |
|---|---|
| Laravel feature/regression suite | **83 passed / 752 assertions**, including 30 Phase 6 cases |
| PHP Pint | PASS |
| Composer strict validation / security audit | PASS / no advisories |
| Storefront format, lint, TypeScript, production build | PASS |
| Admin format, lint, TypeScript, production build | PASS |
| New production Next browser suite | **13 grouped checks PASS**, no page errors |
| Existing shopping browser regression | **15 groups PASS**, adjusted only for the real add-to-bag replacement |
| Existing catalogue/inventory browser regression | **15 groups PASS**, no page errors |
| Concurrent last-unit checkout | PASS: two independent PHP processes and SQLite connections, synchronized start; one order/reservation, second request rejected, no oversell |
| Actual database queue worker | PASS: one paid-contract-fixture outbox, one private .eml, repeated dispatch creates no duplicate |
| Actual hosted Stripe purchase / actual webhook delivery | **PENDING**, not simulated as a completed integration |

Backend checks cover guest/staff boundaries, stale revisions/quotes, browser price tampering, integer-string normalization, invalid addresses/discounts/delivery/terms, duplicate requests/events, mismatched provider evidence, out-of-order/late events, pending status, creation timeouts, frozen request replay, provider outage, expiry, last-unit contention, multiple locations and preserving earlier reservations. The private email artifact is tested after actual queue execution, but paid evidence in that test is synthetic.

UI checks use isolated SQLite/storage, an actual production Next server and Edge/Chromium. They include mobile 390px, tablet 768px, desktop 1440px, equivalent 200% reflow, guest persistence/isolation, invalid quantities, current totals, disabled capture without keys, a real backend shutdown/restart, restored retry details, and MFA-protected read-only orders. Responsive screenshots were reviewed. Physical screen readers, Safari/device matrix and production load are not verified.

## Acceptance status

1. **Actual sandbox purchase → exactly one correctly priced protected admin order: PENDING.** The synthetic provider-contract path and admin display pass; no real Stripe capture is claimed.
2. **Ignore browser prices / concurrent last unit cannot oversell: PASS locally** on SQLite, including a separate-process race.
3. **Repeated submissions/events do not duplicate orders/payments/stock/email copies: PASS in automated contract/queue checks.** Replay of an actual Stripe event is still pending alongside capture.
4. **Failure/expiry recovery and redirect safety: PASS in isolated checks.** Actual hosted decline/abandonment/expiry must still be exercised with merchant sandbox credentials.

## Database, configuration and preserved work

Applied additive migration `2026_10_26_000002_create_commerce_tables` as batch 4. It adds **11 tables**, all empty in the development database: no test merchandise, orders, bags, stock or staff credentials were seeded there. Every original row across **27 existing tables** is preserved. Only migrations gains its expected new record; the other 26 tables are unchanged. Existing backend/storefront/admin environment files are byte-for-byte preserved. Private before/after SQLite backups and a source backup remain in this chat’s work/phase6 scratch directory; they are excluded from deliverables.

Actual local configuration: sandbox enable switch false, test secret absent, signing secret absent. `.env.example` files now document the required optional settings. Application dependency lockfiles are unchanged; no Stripe SDK installation is needed for the REST adapter. Root README and PHASE6-SETUP.md document exact installation, migration, database, port and startup commands.

The repository already had uncommitted/untracked project source; that work was retained. No commit, push, PR, hosted CI, deployment, external order email or live payment was performed. The CI definition now includes the independent checkout race, but its hosted execution is unverified.

## Test locally

Follow **[PHASE6-SETUP.md](PHASE6-SETUP.md)** for exact PowerShell commands and screenshots under verification/phase6. In brief:

1. Use the existing Desktop ELVARQO folder. In backend run `php artisan migrate` and `php artisan config:clear`; preserve existing .env and APP_KEY. Default database is SQLite, so **XAMPP is unnecessary**.
2. Start Laravel on localhost:8000, storefront with `npm.cmd run dev` on 3000, and admin with `npm.cmd run dev` on 5173 in separate terminals.
3. Also run `php artisan schedule:work` and `php artisan queue:work --tries=3 --timeout=60` in backend terminals.
4. Publish a stocked product if your catalogue is empty. Add a size/colour to the bag, change quantities, enter a UK address, select a test delivery rate/discount and review totals. Sandbox capture is disabled until local test credentials are configured.
5. When ready to finish the pending acceptance, configure only backend ignored .env with sk_test/whsec values, enable sandbox, start Stripe CLI forwarding and use Stripe’s hosted test cards. Detailed success/decline/expiry/replay steps are in the setup guide. Do not paste keys in chat.

## Remaining issues and limits

- Complete real Stripe sandbox purchase and real event replay/decline/abandonment before calling Phase 6 fully accepted. The user requested this remain pending for now.
- Merchant tax treatment, business/policy content and actual delivery rules need approval before any live checkout. Live keys are rejected in this implementation.
- External transactional mail is intentionally disabled; only private sandbox copies are produced. A delivery provider and its retry/idempotency policy remain to be selected.
- Unknown Stripe status retains reservations. Creation unresolved after 23 hours or mismatched/late evidence moves to review; Phase 6 exposes this read-only and does not offer financial overrides/refunds. Resolve provider evidence before changing held stock.
- SQLite is verified. MySQL/PostgreSQL need their own concurrency/immutable-record validation before deployment. Proxy/client rate-budget handling, TLS, retention, production performance and wider assistive/device testing still require release work.

**Next phase after the pending acceptance:** Phase 7, order operations, fulfilment and delivery bookings. It has not been started.
