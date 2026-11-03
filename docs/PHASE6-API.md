# Phase 6 API and state contract

## Guest transport
Browser routes `/api/commerce/*` are a small Next allowlist proxy, not a general proxy. Laravel routes live under `/api/v1/commerce`. POSTs require JSON and exact STOREFRONT_ORIGIN. Backend guest auth is a 256-bit bearer capability hashed in commerce_bags; Next strips creation secrets from JSON and stores them in host-only HttpOnly SameSite=Lax cookies. The 30-day cookie/record lifetime limits guest order access; do not clear the cookie if you need to resume that order. Staff authentication remains separate.

| Method | Laravel suffix | Contract |
|---|---|---|
| POST | bags | Create empty bag; token only consumed by Next |
| GET | bag | Current published lines, prices, available quantities, revision, active order, safe rates/readiness |
| POST | bag/items | variant_id, absolute quantity 0–10, revision; 0 removes, maximum 30 variants |
| POST | quote | revision, email, address, optional billing_address, delivery_method, optional discount_code |
| POST | checkout | Quote fields + quote_hash, UUID attempt_id, terms=true |
| GET | orders/{UUID} | Owning guest capability only; persisted states and immutable snapshot |
| POST | orders/{UUID}/resume | Owning guest only; retry creation or reconcile authoritative Stripe session |

Address keys: first_name, last_name, line1, optional line2, city, postcode and country=GB. Billing defaults to shipping. Postcode validation is syntactic, not a courier deliverability/royal-mail lookup. No postcode exclusions or courier booking is implied by test UK rates. Guest endpoints are bounded/rate-limited; the shared Next server IP currently shares Laravel guest rate budgets. Before distributed production deployment add a trusted, bounded per-client rate identity at the proxy rather than accepting arbitrary forwarded IPs.

All money is integer GBP minor units; browser price/tax/shipping/discount amounts are ignored. Exact quote hashes detect current-price/address/rate changes. Explicit quantity mutations reject stale revisions. Active reservations lock the bag against edits. Payment commits clear only the associated active bag; expiry/failure unlocks without erasing the items. Identical checkout retries return the same order even after its bag has changed. Different payloads cannot reuse an attempt UUID.

## Provider and staff
POST `/api/v1/payments/stripe/webhook` accepts raw signed Stripe payloads with timestamp tolerance 300 seconds and rotating v1 signatures. Supported session completed/expired/async succeeded/failed events trigger a server GET of the linked session. Verify session ID, test mode, payment mode, order reference, attempt metadata, GBP and exact total. Store only event identity/type/session/hash/time; never raw card data or webhook customer payloads. Unique event IDs and a locked monotonic state transition deduplicate effects.

GET `/api/v1/staff/orders` and GET `/api/v1/staff/orders/{UUID}` require MFA-authenticated staff plus fulfilment.view. The list is paginated (25); details contain immutable items/address/prices, reservations, automated stock events and sandbox mail status. All views are read-only. Configuration summary contains safe values only. No generic order mutation endpoint, refund or dispatch endpoint exists in Phase 6.

## States and inventory
Order: awaiting_payment → confirmed, expired, payment_failed or review. Payment: creating → open/pending → paid, expired, failed or review. Fulfilment remains unfulfilled, with on_hold for review. A browser redirect has no authority. Paid/confirmed stock commit and unique mail outbox insert happen in one database transaction.

Normalized commerce_order_items and commerce_order_addresses retain SKU/title/choice/price/quantity and shipping/billing details. Aggregate commerce_order_snapshots retain reviewed totals/rules/contact and the same immutable lines/address. SQLite triggers forbid updating/deleting snapshot, item, address, automated-ledger and event records. DB permissions and equivalent immutable guards for a future MySQL/PostgreSQL deployment still require validation; only SQLite is verified in this phase.

Reservations split deterministically across active locations, preserving all pre-existing reserved stock. SQLite IMMEDIATE transactions, 10-second busy timeout, ordered locks and conditional stock updates prevent overselling. reserve adds reserved; commit subtracts reserved and on_hand; release subtracts reserved only. Each action writes a unique immutable commerce_stock_events entry with post-balances. Phase 4 staff stock_movements remain untouched; automated commerce history appears in the order view. No fictional staff actor is assigned to a sale.

Hosted card-only sessions expire after one hour. Local reservation deadline is 65 minutes. Reconciliation always asks Stripe: a locally old but unknown/pending payment retains stock. Verified expiry or an authenticated async-failure event corroborated by complete/unpaid status releases stock. Later paid evidence after release moves to review and does not fulfil or decrement stock again. Price, catalogue or address edits never rewrite existing order snapshots. Actual provider POST parameters are persisted before first call so origin/rate/configuration edits do not break idempotent retries.

Durable commerce_mail_outbox is inserted only on confirmed payment. Scheduler dispatches retryable jobs; private deterministic .eml writes and an outbox delivered_at marker make repeated sandbox jobs idempotent. Payment and outbox survive a worker crash. This does not claim exactly-once external SMTP delivery.
