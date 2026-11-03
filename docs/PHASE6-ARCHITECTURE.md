# Phase 6 architecture — 6A–6D only

Read both supplied PDFs. Scope: plan pp15–16, designs S13–S18, M04–M06 and minimal configuration A18/A22/A25/A26/A29. The user approved Stripe sandbox, UK/GBP and test-only rates on 26 October 2026. Merchant tax and real delivery rules remain undecided. No live payments or Phase 7 operations.

## Trust boundaries
Next route handlers proxy guest commerce to Laravel. A random 256-bit bearer capability stays in a host-only HttpOnly SameSite=Lax cookie; Laravel stores only its SHA-256 hash. Browser POSTs require exact storefront Origin and JSON. Guest credentials cannot authorize staff routes. Orders are readable only by their bag capability or authenticated staff with fulfilment.view. Private responses are no-store; no customer details in URLs or local storage. Keys remain in ignored backend .env.

## Money and checkout
Integer GBP minor units only. Laravel reads active published variants, quantity limits and active inventory. Bag mutations are absolute quantities with optimistic revision checks. Quote fingerprint covers current catalogue prices, validated address, discount, delivery and tax; checkout rejects changed quotes. Default labelled fixtures: TEST10 = 10% off merchandise, standard £4.95, express £8.95, tax 0 (test assumption, not merchant tax treatment). Configurable code window/minimum and rate settings; no production policy claimed. Address and item/price snapshots persist at order creation. Staff sees a read-only list/detail and safe configuration summary.

## Inventory and state
SQLite IMMEDIATE transactions and conditional balance updates serialize competing last-unit reservations. On other databases row locks plus conditional updates protect stock. Reservations can span active locations in deterministic order. A separate append-only commerce stock ledger records automated reserve/commit/release without impersonating staff or rewriting the Phase 4 staff ledger. Immutable normalized item and shipping/billing address rows plus an aggregate reviewed snapshot are stored separately from mutable state. Order, payment and fulfilment states are separate.

Checkout UUIDs have request fingerprints. A bag has one active order; replay resumes the same attempt and Stripe idempotency key. Ambiguous network failures retain stock and retry the same provider request. Hosted card-only Checkout expires after one hour. Stock is released only after verified provider expiry/failure, or local expiry when no provider creation was attempted. Unknown provider status retains stock for reconciliation. Never release solely because a browser redirects or a local timer passes. Creation retries stop before Stripe’s 24-hour idempotency retention boundary; unresolved cases require review.

## Payments and messages
Stripe REST over HTTPS, test keys only, explicit enable switch off by default, no raw card fields. Verify raw webhook HMAC, timestamp and event identity; retrieve authoritative session before state transitions, match provider ID/order metadata/amount/currency/test mode. Durable unique event IDs, order CAS and unique outbox rows prevent replay effects. Scheduled reconciliation recovers missing webhooks and expires abandoned sessions. Paid is monotonic; terminal events cannot undo it. Inconsistent evidence is held for review without fulfilment.

Confirmation mail uses a durable database outbox inserted in the payment transaction and a scheduled queued worker. Phase 6 sandbox mail is written atomically to a private deterministic .eml path, so repeated jobs do not duplicate it. External SMTP delivery is deliberately disabled until a delivery provider with idempotency is selected; generic SMTP cannot guarantee exactly-once delivery after a crash.

## Verification plan and limitations
Add isolated SQLite feature tests for pricing tampering, ownership, revision/quote changes, idempotency, signature validation, out-of-order payment states, reservation recovery, outbox and staff permission boundaries. Use separate SQLite processes for last-unit contention. Verify frontend production builds and an isolated browser bag/checkout flow. Provider HTTP fakes and signed synthetic events are contract tests, never proof of an actual Stripe purchase. Actual hosted sandbox capture requires the merchant’s local sk_test and whsec configuration; report this acceptance criterion as pending until performed.


## Implementation references consulted
- [Stripe Checkout session creation](https://docs.stripe.com/api/checkout/sessions/create)
- [Stripe webhook signature and delivery handling](https://docs.stripe.com/events/manage-webhook-endpoints)
- [Stripe idempotent requests](https://docs.stripe.com/api/idempotent_requests)
- [Stripe session expiry](https://docs.stripe.com/api/checkout/sessions/expire)
- [Stripe API versioning](https://docs.stripe.com/api/versioning)

These documents informed the adapter, identity checks and frozen retry policy. They do not replace a successful integration run with merchant sandbox credentials.
