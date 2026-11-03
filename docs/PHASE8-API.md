# Phase 8 contracts and states

All API paths below have `/api/v1/` prefix. Existing `data/meta` and `error/details` envelopes apply. Minor currency units are integers in GBP. Private endpoints send no-store/private and no-referrer headers. Next proxies use exact-origin JSON POSTs, route allowlists and bounded bodies; customer cookies are host-only HttpOnly, SameSite=Lax, Secure under HTTPS. Staff requires existing MFA/session/CSRF/origin and explicit permissions. No default Laravel user guard or staff session authorizes a customer.

## Customer

GET `customer/me`, `orders?page=1`, `order?id=UUID`, `addresses`, `saved`.

POST `customer/register` (name/email/password/terms), `login` (email/password), `recover` (email), `redeem` (purpose/token and password for reset), `logout`, `profile` (name), `email` (new email/current password), `claim` (reference), `addresses`, `saved`, `returns`.

Registration/recovery/claim have neutral responses. Login returns a capability only to the Next server; the proxy removes it from browser JSON and sets `elvarqo_customer`. Sessions store SHA-256 token hashes, auth version, two-hour idle and fourteen-day absolute expiry. Challenges store only token hashes and expire in thirty minutes. Claim/email redemption also requires the original customer session. Email changes bind the original auth version. Reset revokes sessions and outstanding reset links. Address save/delete is customer-scoped and requires confirmation/version for an existing record; maximum ten addresses. Separate profile edits cannot rewrite order snapshots.

Saved items: `{action:merge|save|remove,items:[{product_id,variant_id:null|integer}]}`. Up to 50 input rows, 500 stored choices, per-customer transaction; merge preserves existing selections. Overflow rolls back atomically. Existing account choices still load and remain editable; the guest source is retained for explicit retry after capacity is freed. Public catalogue hydration batches account IDs into at most 50 per request without truncating the saved list. Product publication filtering remains in the catalogue's saved-products read API.

Return input: operation_id UUID, order_item_id, quantity, reason (size/changed_mind/damaged/wrong_item), resolution (refund/exchange), optional exchange_variant_id, comments, confirmed. Account route adds order_id and verifies explicit ownership. Guest GET/POST `tracking/returns` requires the separate verified tracking capability. Bag or customer tokens cannot substitute for it.

## Staff

- GET `staff/after-sales/returns`: fulfilment.view; GET `refunds`: finance.view. Paginated 20 cases, server-computed remaining credit, inspection, stock choices, refund/exchange history and timeline.
- POST `{return}/inspect`: fulfilment.manage; full received_quantity, accepted_resellable/accepted_damaged/rejected outcome, active location_id, reason, confirmed. Same immutable inspection payload can replay; changed evidence conflicts.
- POST `{return}/exchange`: fulfilment.manage; operation_id, variant_id, expected_difference_minor, confirmed. Active published same-product variant/stock checked again. Failed stock/price review creates nothing.
- POST `{return}/resolution`: fulfilment.manage; reason, confirmed; switch unissued/expired exchange to refund review only.
- POST `{return}/refund`: finance.refund; operation_id, amount_minor, reason, scenario (succeed/pending/fail/timeout_after_once), confirmed. The UI then opens a final dialog with the frozen exact amount and reason. Staff history includes the durable provider reference and reason; customer views omit these staff fields.
- POST `{attempt}/retry`: finance.refund; confirmed; same immutable attempt/receipt. No alternate amount field or browser success override.

## Evidence and invariants

Immutable item IDs order aggregate discount allocation; cumulative integer fractions allocate exact line and per-unit net credit. Unique immutable fulfilment-item/local-unit proof prevents overlapping physical returns, independently of bounded original credit slots. Cancelling earlier unshipped parcels cannot renumber existing claims. Requested events retain the proof immutably even if rejection later releases a claim. Inspection and after-sales stock events are append-only. Rejected claims release units; accepted claims retain them. Outbound shipping/tax excluded under user-approved test policy. Non-paid or unverified/non-delivered/old/claimed units cannot be requested.

Exchange states: issued (replacement funded from credit), awaiting_top_up (unpaid; cannot ship), expired (hold released). Replacement stock uses the shared atomic reservation/settlement primitives. Orders with exchange_credit can ship only with an issued funding link. Existing Order API/UI exposes funded_exchange and filters exchange states. Replacement credit reduces original refundable balance. Higher-priced payment completion and automatic returns of credit-funded replacements remain deferred.

Refund attempt starts sandbox_unknown; durable receipt validates operation/order/amount/currency/provider reference before sandbox_pending, sandbox_succeeded or sandbox_failed. Pending/unknown/succeeded amounts reserve credit; only verified failed releases it. Per-order and per-return locks/balance checks prevent overspend; retries fingerprint original actor/payload and use the same operation. Responses lost after a committed receipt remain unknown until reconciliation. Provider mismatch holds credit for review. No stock changes on refund callbacks. Test-only return/inspection/exchange mutations and the refund adapter fail closed outside local/testing; no Stripe refund is sent or real refunded state recorded.

CLI: after-sales:maintenance queues private account .eml jobs, reconciles uncertain local refunds, expires unpaid exchange holds. refund:sandbox-event accepts only pending existing local receipts and succeeded/failed. Scheduler runs maintenance every minute. Customer outbox payloads are encrypted at rest; deterministic files are private and written atomically by a queue job. Existing order notifications cover initial return requests. Detailed case timelines are authoritative in account/guest/admin views; outbound inspection/refund/exchange status mail is not implemented.
