# Phase 7 API and state contract

All routes use the existing JSON envelope, request IDs and private no-store headers. Staff routes retain web/session/MFA/origin/CSRF. Read permission: fulfilment.view. Mutation permission: fulfilment.manage. Guest tracking cookies and bag cookies are distinct from staff authentication. Labels remain private.

| Method | Staff route suffix after /api/v1/staff | Purpose |
|---|---|---|
| GET | orders?q=&payment=&fulfilment=&page= | Literal reference/customer-name search, independent state filters, 25/page |
| GET | orders/{order} | Immutable snapshots, remaining allocations, shipments, notes, notifications, support |
| POST | orders/{order}/notes | operation_id UUID, body; append-only, retry-safe |
| POST | orders/manual/quote | items variant_id/quantity, email/address/billing/delivery/discount; shared pricing |
| POST | orders/manual/create | Quote fields + quote_hash, operation_id, confirmed=true |
| POST | orders/{order}/payment-invitation | confirmed=true; eligible manual invitation only |
| POST | orders/{order}/shipment-quotes | Item IDs/quantities, location, service, parcel, instructions |
| POST | orders/{order}/shipments | quote_id, confirmed=true; allocate and book idempotently |
| POST | shipments/{shipment}/retry | confirmed=true; same provider identity |
| POST | shipments/{shipment}/cancel | confirmed=true; eligibility + sandbox void confirmation |
| POST | shipments/{shipment}/dispatch | confirmed=true; no second stock deduction |
| GET | shipments/{shipment}/label | Authenticated attachment, SVG, no-store/nosniff/sandbox CSP |

Quotes contain items [{order_item_id,quantity}], location_id, service sandbox_standard/sandbox_express, parcel {weight_grams,length_cm,width_cm,height_cm}, instructions optional. Bounds: 1–30000g, 1–120cm per dimension, maximum 30 unique item lines. Quantity cannot exceed remaining order units or units committed at the selected location. Destination is always the immutable order address, never a client-supplied replacement. Quote TTL ten minutes, allocation locked transactionally. A repeated consumed quote returns its same fulfilment. An uncertain booking remains allocated and cannot be replaced/cancelled blindly.

Shipment state: draft → booking → booked, unknown or failed. Same-ID retry resolves unknown/failed; booked → dispatched → in_transit → out_for_delivery → delivered. Exception status may interrupt progress but cannot undo delivered. Eligible draft/failed/booked → cancelled; never after dispatch. Booked/cancelled/dispatch operations have durable audit and booking history. Carrier sandbox receipt is unique by fulfilment ID and request fingerprint; no real postage purchase is performed. Allocation includes all noncancelled states. Label cancellation affects allocation only, never financial state or paid stock.

Aggregate order fulfilment: unfulfilled, partially_fulfilled, fulfilled (all dispatched), delivered (all delivered). on_hold is preserved. Courier scans never overwrite order_state/payment_state. Phase 6 stock remains authoritative: payment already committed on-hand; fulfilment does not decrement it again. No full order cancellation, refund or inspection/restock endpoint is added.

## Secure guest access

Next `/api/tracking/{action}` proxies only an explicit allowlist to `/api/v1/tracking/{action}`. JSON POSTs require exact STOREFRONT_ORIGIN. Request: reference/email, always neutral response. Redeem: one-use token, 30 minutes, hash-only lookup, exchanges for a hashed 24-hour order-specific session; Next strips the returned token and sets HttpOnly elvarqo_tracking. Token fragments are removed before POST. GET order returns only public progress and support summaries; no addresses/emails/labels/staff/internal notes. POST support requires that session plus operation_id/subject/message, five/day/order; repeated identical keys replay once. POST logout deletes the session. A bag capability or staff cookie cannot substitute for tracking verification.

POST payment-redeem consumes purpose-specific manual invitation tokens and rotates the order bag capability into the separate guest bag cookie. Existing checkout/resume APIs then apply Stripe's deferred configuration and evidence rules. Manual creation uses shared Pricing/Checkout and no invented payment record. Unattempted manual reservations expire after 65 minutes; provider-attempt ambiguity stays protected. Staff responses never reveal invitation tokens or encrypted mail payloads.

## Courier events and notifications

POST /api/v1/courier/sandbox/webhook verifies raw-body HMAC/timestamp, bounded payload, event ID, known receipt/tracking pair, allowed state and occurred_at. Reject stale signatures (>300s), future event time (>5m) and ID/body collisions. Deduplicate exact replays. Apply only nonregressive/current events for eligible shipments; pre-booking events, late scans and events for cancelled/delivered shipments are recorded as ignored. A valid transit scan can record first dispatch and queue one dispatch notification. The local CLI simulator uses this same signed service, not a real provider webhook.

Notifications use encrypted payloads, unique dedupe keys, transaction-bound creation and deterministic private file delivery through the database queue. Their IDs/kinds/created/delivered status appear to staff. Tracking/payment links stay in private queued email copies, not public JSON/staff detail. No external delivery or generic SMTP exactly-once claim.

Named rate buckets isolate tracking reads, link requests, redemption, support, staff operations and webhook traffic. This fixes Laravel numeric throttle sharing, where reading progress could previously exhaust the smaller support budget. Proxy requests still share the trusted Next server IP; production per-client limiting needs deployment review.

## Tables
order_notes; manual_order_attempts; shipment_quotes; order_fulfilments; fulfilment_items; fulfilment_parcels; courier_booking_attempts; sandbox_courier_receipts; fulfilment_labels; courier_tracking_events; order_access_links; order_tracking_sessions; order_notifications; order_support_requests. SQLite triggers make notes/items/parcels/booking-attempts/labels/tracking-events append-only. Carrier receipts and shipment/session/notification lifecycle records remain mutable under service controls. No table is seeded in development by the migration.
