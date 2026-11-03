# ELVARQO Phase 7 implementation report

26 October 2026. Project: `C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO`.

**Phase 7 substeps 7A–7D are implemented and verified locally with a clearly labelled courier sandbox.** The user explicitly requested proceeding while deferring the actual Stripe sandbox purchase. That Phase 6 acceptance gap remains. No real courier, postage purchase, live payment or external notification delivery is claimed. No Phase 8 work started.

## What changed

**7A — Order operations.** Protected search by reference/customer name, independent payment/fulfilment filters, item/price/contact/address detail, remaining quantities, traceable internal notes and booking/notification/support history. Internal notes are immutable and retry-safe. All actions retain MFA/session/origin/CSRF enforcement; fulfilment.view reads and fulfilment.manage writes. Support staff can inspect but cannot manage. Manual entry searches published variants, reviews current server totals, reuses Pricing/Checkout's stock transaction, persists immutable snapshots and queues a one-use secure payment invitation. It creates an **unpaid** order; no mark-paid shortcut or invented payment is provided.

**7B — Dispatch and shipment booking.** Staff selects order item quantities, their committed dispatch location, service, weight/dimensions and optional instructions. Server validates remaining/location quantities and issues a ten-minute immutable quote; staff reviews destination/origin/items/cost before confirming. Fulfilment/items/parcels and booking attempts persist. The local SandboxCourier has a unique durable receipt/fingerprint by fulfilment identity, private deterministic SVG labels, provider reference and tracking number. Labels explicitly say **NOT VALID FOR POSTAGE** and contain no usable carrier barcode. They are an allowed adapter sandbox, not a real carrier integration.

**7C — Exceptions.** Partial shipments account for all allocated units including uncertain attempts. Separate-process tests prove competing allocations cannot exceed one remaining unit and concurrent same-parcel bookings create one receipt/label. Timeout-before, timeout-after-once and provider-reject scenarios expose failure/retry history. Retrying the same booking retrieves the same receipt. Unknown outcomes retain allocations and cannot be cancelled blindly. Eligible draft/rejected/booked labels can be cancelled after local sandbox void confirmation; dispatched/transit/delivered parcels cannot. Cancellation frees allocation without refunding/restocking. Dispatch never deducts stock twice because payment already committed inventory.

**7D — Secure customer progress.** Guest reference/email requests return a neutral response; one-use 30-minute links exchange for an independent order-scoped 24-hour HttpOnly tracking capability. Tokens are hashed in access records, encrypted in private notification payloads, removed from URL fragments before redemption and absent from staff/public responses. Tracking exposes parcel progress/items/events, never addresses/emails/internal notes/provider labels. A consumed/expired link or another reference cannot grant cross-order access. Verified guests can save idempotent minimal order support requests for staff review. Signed raw-body/timestamp sandbox events are verified/deduplicated, reject identity/body collisions and ignore regressions/late scans without altering payment/order financial state. Dispatch and progress changes queue durable traceable notifications; actual database jobs save one deterministic private .eml copy per notification.

## Design coverage

Reviewed both supplied PDFs before implementation. Plan pp17–18, A03 orders, A04 detail/notes/fulfilment, A05 manual order, A06 parcel/quote/review, A25/A29 minimal courier/configuration status, S19 verified guest tracking and M08 mobile operations informed the implementation. No full shipping/settings editor, bulk order finance operations, customer accounts, returns or refunds is claimed. Courier booking is shipment booking, not appointments.

Existing Phase 6 Stripe adapter remains deferred/disabled. Manual payment invitations work locally and bind the recipient to the unpaid checkout; actual payment capture remains a later verification task. Delivery/tax rates remain labelled test assumptions. Real dispatch-origin addresses, service mapping and merchant/courier rules require configuration when a real carrier is chosen.

## Test results

| Check | Result |
|---|---|
| Laravel feature/regression suite | **110 passed / 976 assertions**, including **27 Phase 7 cases** |
| Pint / strict Composer validation | PASS |
| Composer audit | No security vulnerability advisories |
| Storefront format/lint/TypeScript/production build | PASS |
| Admin format/lint/TypeScript/production build | PASS |
| New production Next Phase 7 browser lifecycle | **14 grouped checks PASS**, no page errors |
| Existing checkout browser regression | **13 groups PASS** |
| Existing catalogue/inventory browser regression | **15 groups PASS** |
| Existing public shopping browser regression | **15 groups PASS** |
| Concurrent remaining fulfilment unit | PASS — two independent PHP processes/connections, one eligible allocation |
| Concurrent same-parcel booking | PASS — one sandbox receipt and one label |
| Existing last-stock-unit checkout race | PASS — no oversell |
| Real Stripe / real carrier / external email | Not performed; deferred/not configured |

Total browser coverage: **57 grouped checks**. Browser tests use isolated SQLite/storage, random staff credentials, actual MFA, an actual production Next server and Edge/Chromium. New lifecycle includes a timeout-after-receipt, same-ID retry, protected label download, cancellation/void, two one-item dispatches, no second stock deduction, mobile/tablet/desktop and equivalent 200% reflow, neutral wrong-email tracking response, link replay rejection, HttpOnly/session scope, signed delivered/late scans, verified support intake, unpaid manual review/invitation and support-role restrictions. The real database queue worker saves private link/dispatch/support copies. Screenshots and rendered SVG label were visually reviewed.

Paid fixtures use provider HTTP contract fakes confined to guarded isolated CLI/test databases. They are not an actual Stripe purchase or real carrier booking. No default paid demo record was seeded into development, and the application cannot convert an unpaid order to paid by a staff action.

## Acceptance checks

1. **Paid order → sandbox label → customer tracking: PASS locally** using an isolated verified-contract paid fixture and the permitted local courier sandbox. Real provider capture/carrier delivery is still outstanding.
2. **Partial fulfilment bounds / duplicate booking safety: PASS**, including independent-process races and uncertain-response retries.
3. **Verified, deduplicated events / isolated state transitions: PASS for the sandbox event protocol.** A future carrier adapter needs that carrier's signature/schema/void/reconciliation tests.
4. **Guest privacy without verified tracking access: PASS.** Wrong details, replayed/expired tokens, cross-order reference attempts, missing sessions and sign-out are covered. Even verified tracking omits street addresses and staff data.

## Preserved data and configuration

Applied additive migration `2026_10_26_000003_create_fulfilment_tables` as batch 5. **14 new tables remain empty in development.** All original rows across **38 pre-existing tables** are preserved; only migrations gains the expected record, the other 37 tables are unchanged. Existing backend/storefront/admin environment files are byte-for-byte preserved. No test products, stock, staff credentials, paid orders or shipments were added to development. Private before/after SQLite and source backups remain under the chat work/phase7 scratch folder and are excluded from delivery archives.

No application dependency or lockfile update was required. The verification workflow now includes the fulfilment race, but no hosted CI execution, commit, push, PR or deployment was performed. Existing uncommitted/untracked project work remains preserved.

Corrections during verification: exact event replay now reuses the same timestamp; tracking handles an already-open page's email fragment and development remounts; deferred Stripe refresh cannot falsely mark an unattempted manual reservation as attempted; invitation expiry and label wrapping/atomic writes were tightened. Browser testing exposed numeric throttle bucket sharing between tracking reads/support; named action-specific rate budgets and a regression test fix this. The older checkout browser test now opens the new inventory disclosure instead of looking for its hidden table. Initial failures are superseded by complete passing reruns.

## Test on your PC

Read **[PHASE7-SETUP.md](PHASE7-SETUP.md)** for exact commands and failure/retry scenarios.

1. In the existing Desktop ELVARQO/backend folder run `php artisan migrate` and `php artisan config:clear`; preserve .env and APP_KEY. SQLite is configured, so **XAMPP is unnecessary**.
2. Start API localhost:8000, storefront localhost:3000 and admin localhost:5173 in separate terminals. Also run `php artisan schedule:work` and `php artisan queue:work --tries=3 --timeout=60` from backend terminals.
3. Open admin `/?view=orders`. Inspect/search saved orders, add notes with an allowed role, or review/create an unpaid manual invitation. No paid record is invented if your database has none.
4. To demonstrate the full paid-order dispatch workflow while deferring Stripe, run `node shared/tests/fulfilment-browser.cjs` with the Playwright/PHP settings in the guide. It creates its own paid contract fixture/database, fulfils two parcels, tests timeout recovery, secure tracking/support and manual invitations without touching development.
5. For an existing genuinely paid order, review parcel quotes, book sandbox labels and dispatch. At `/track`, request a link and retrieve its private .eml after the worker runs. Use `courier:sandbox-event SHIPMENT-UUID STATE` with a configured local sandbox secret to exercise verified progress. These remain local test labels/events; no real postage/notification is sent.

## Remaining work and next phase

- **Deferred by user:** actual hosted Stripe sandbox purchase and real signed provider replay/decline/abandonment verification from Phase 6. Live payment remains disabled and unsupported.
- Select/configure a real courier, real origin addresses/service mapping, genuine labels, callback signature/reconciliation and cancellation policy before claiming a live carrier integration.
- Configure real transactional email delivery; this phase deliberately saves only private .eml copies. Tracking-link delivery is therefore a local manual test until an email provider is connected.
- Merchant-approved tax, delivery/policy content, HTTPS, per-client proxy rate strategy, database-specific concurrency/immutability, retention and production performance need release work. Only SQLite and the tested Edge/Chromium environments are verified; physical screen readers/wider device matrix remain untested.
- Full support workflows are Phase 10; no refund, inspection/restock or customer-account work was added. Shipment cancellation does not cancel/refund the paid order.

**Next development phase: Phase 8 — optional customer accounts and after-sales (returns/exchanges/refunds). It has not started.**
