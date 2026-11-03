# Phase 8 completion report — local test scope

Date: 26 October 2026. Project: `C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO`.

## Implemented

- **8A / S21–S24, S27:** optional customer registration, verified email, sign-in/recovery/logout, profile/name and password-confirmed email change, scoped/versioned address book. Guest checkout remains primary. Independent customer capability/session and private transactional challenge outbox; staff or bag cookies cannot substitute.
- **8B / S24–S26:** paginated owned order history/detail, original address/price snapshots and shipment progress, explicit proof sent to each guest order's immutable email before linking, bound to the requesting verified customer. Account wishlist union preserves existing and guest choices; overflow/failure retains guest source. Identity changes reset private page state.
- **8C / S20, S26, M07, A07–A08:** guest/account partial item requests, actual delivered-unit eligibility and exact integer discount allocation, stable non-overlapping claims bound to original delivered parcel items (safe after earlier parcel cancellation), reasons, refund/exchange resolution and visible timelines. Full-quantity physical inspection with immutable result and receiving location; resellable acceptance restocks once, damaged/rejected never restock. Stock ledger/audit remains separate from refund callbacks.
- **Exchanges:** stock/publication/current-price recheck, explicit signed difference and staff confirmation, immutable replacement order and funding link. Equal/cheaper replacements commit stock under exchange_credit and can use existing fulfilment tools; no paid_at or fictional card payment. Cheaper differences remain eligible for refund review. Higher-priced replacements stay unpaid, cannot ship and expire/release holds after thirty minutes while Stripe top-ups are deferred.
- **8D / A08:** finance.refund permission, exact pence amount/reason review and final confirmation dialog, order/return balance locks, operation fingerprint, durable unique local provider receipt, pending/failure/lost-response handling and authoritative reconciliation. Staff history shows reason/provider reference. Customer views omit internal refund reason/reference. All outcomes say sandbox_*; no real refunded state or external payment is claimed.

## Approved scope and design

Read the supplied development plan pp19–20 and supplied S20–S27/A07–A08/M07 design references before application changes. PHASE8-ARCHITECTURE.md was recorded before scaffolding and private source/SQLite backups were made. User approved **test-only policy and refund sandbox** after explicitly deferring Stripe. Thirty days from verified delivery, inspection before restock, GBP merchandise-only credits excluding outbound delivery/tax. Return/inspection/exchange/refund mutation fails closed outside local/testing. Functional screens adapt the design to the existing applications; A07 export/saved views, wider CRM and full support/reporting are not delivered here. No Phase 9 work started.

## Verification

Final verification results are recorded below from the completed regression runs. The Phase 8 production journey and independent-process race evidence are in docs/verification/phase8 and the supplied evidence directory.

| Check | Result |
| --- | --- |
| Full backend suite | **141 tests / 1,148 assertions PASS**, including 31 Phase 8 cases |
| Phase 8 production browser acceptance | **16 groups PASS**, no page errors; actual private queue worker/email copies exercised |
| Prior fulfilment / checkout / shopping / catalogue browser regressions | **14 / 13 / 15 / 15 groups PASS**; **73 total browser groups** |
| Independent PHP-process/SQLite races | **4 checks PASS**: final return units, remaining refund balance, same-key replay, last exchange stock |
| Both frontend applications | Prettier, Oxlint with denied warnings, TypeScript and production builds **PASS** |
| Backend checks | Pint, strict Composer validation and dependency audit **PASS** |
| Preservation audit | Original business rows, environment files, dependency lockfiles and PDFs preserved; no development QA seed |

Verified two-account order/address isolation, staff/customer guard separation, challenge expiry/replay/recovery revocation, guest proof, wishlist union/overflow and 56-item batched catalogue hydration and editable 500-item overflow recovery, immutable addresses, partial delivered eligibility, cancellation-safe physical unit claims, inspection-only restock, refund caps/replay/pending/failure/provider mismatch, unavailable/stale-price exchanges, unpaid top-up expiry, funded cheaper replacement fulfilment and production-policy denial. A delayed real account API response was held until after logout/login to prove an older identity cannot reappear. Guest tracking opens after-sales without an account. Tablet/desktop/mobile account reflow and refund review dialog screenshots were reviewed.

Initial selector, same-tab verification and timeout failures were corrected or superseded by clean serial runs. Testing also identified the permanent delivery mapping and late identity-response safeguards; dedicated regressions now pass. No real Stripe capture, refund, courier purchase or external email was used. Normal Next type/build references were restored after isolated QA.

## Preserved development state

Applied additive migrations `2026_10_26_000004_create_customer_after_sales_tables` (batch 6) and `2026_10_26_000005_bind_return_units_to_delivery` (batch 7): **15 new empty tables** plus permanent delivery-proof columns, total **67**. Compared all 52 prior tables to the pre-change SQLite backup: only migrations gains its two expected records; the other 51 are unchanged. Existing .env files and all dependency lockfiles are byte-for-byte preserved. No development customers, paid orders, stock, shipments, returns or refunds were fabricated. No original source file was removed. No commit, push, deployment, actual capture, external email, real courier purchase or real refund was performed.

## Remaining boundaries

Actual Stripe purchase verification from Phase 6, real refund provider calls/outcome reconciliation and exchange top-up completion remain deferred at the user's instruction. Local durable receipts prove sandbox behavior, not Stripe processing. Higher-priced exchanges must expire/retry or switch to refund after expiry until top-up integration exists. Credit-funded replacement orders require staff review for further after-sales; automatic eligibility currently requires an original verified paid order. This is an explicit limitation, not a completed live exchange/refund integration.

Merchant-approved live eligibility/refund/tax/delivery content, external transactional email, production service/provider selection, database/platform/load/security deployment validation and wider assistive/device testing remain outstanding. Private local email copies are the current verification/recovery/claim channel; inspection/exchange/refund status is visible through case timelines, without automated outbound status email. SQLite is verified; MySQL/PostgreSQL are unverified. Existing unfinished dashboard business figures remain labelled previews. No test-only policy can run mutations in production.

## Test locally and next phase

Follow **PHASE8-SETUP.md** step by step for migration, the five terminal commands, customer private email verification, two-account isolation, guest links, inspection, refunds, exchange expiry and repeatable isolated QA. **PHASE8-API.md** documents routes, permissions and state/credit contracts. No XAMPP service is needed for SQLite.

The next planned phase is **Phase 9: Content & marketing management**. It has not started and requires a new user request. Deferred payment/policy/provider work remains explicitly outstanding regardless of later phase authorization.
