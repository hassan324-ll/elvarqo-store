# Phase 7 local setup and test guide

Project folder: `C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO`. Phase 7 covers order operations, manual orders, partial fulfilment, local courier sandbox labels, secure guest tracking and minimal order support. **Stripe purchase verification remains deferred by the user's instruction.** No live payment, real carrier booking or external email is enabled.

## 1. Apply the update

Open PowerShell:

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend'
composer install --no-interaction
npm.cmd ci --prefix media
php artisan migrate
php artisan config:clear
```

Preserve existing .env files and APP_KEY. Do not run `migrate:fresh`, `db:wipe` or isolated QA fixtures against development. The additive migration is `2026_10_26_000003_create_fulfilment_tables`. Default database remains SQLite, so **XAMPP is not needed**. The code adds no new application dependencies. PHP 8.4/8.5, Node 24, Composer and npm must be available; this PC's PHP is also at `C:\Users\Muhammad Hassan Ali\.config\herd-lite\bin\php.exe`.

For a fresh checkout only, follow the root README first-install instructions, copy .env.example only when .env does not exist, generate an APP_KEY once, create the SQLite file and install dependencies in storefront/admin. Never regenerate the key on the existing installation: it encrypts staff factors and private notification payloads.

## 2. Configuration

Existing configuration is preserved. Optional backend .env settings are documented in .env.example:

```dotenv
COURIER_SANDBOX_SCENARIO=normal
COURIER_SANDBOX_WEBHOOK_SECRET=
```

The courier implementation is **local sandbox only**. Its service quotes are fixtures, references start with SBX-, tracking starts with TEST, and downloadable SVG labels say **NOT VALID FOR POSTAGE**. No carrier API or paid postage is called. The £3.95/£6.95 base label costs are test operator costs, separate from the customer's Phase 6 £4.95/£8.95 test delivery charges; weight above 1kg adds a test 50p per started kg. No tax/courier policy approval is implied.

To test signed courier events, generate a random secret locally:

```powershell
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Put that value in **backend/.env** as COURIER_SANDBOX_WEBHOOK_SECRET. Do not paste it into chat/source control. Leave STRIPE_SANDBOX_ENABLED=false while deferring Stripe. Run `php artisan config:clear` and restart the API after configuration edits.

Storefront .env.local server settings remain:

```dotenv
NEXT_PUBLIC_API_URL=http://localhost:8000/api/v1
CATALOGUE_API_URL=http://localhost:8000/api/v1
COMMERCE_API_URL=http://localhost:8000/api/v1
STOREFRONT_ORIGIN=http://localhost:3000
```

Backend STOREFRONT_ORIGIN must match. Admin remains at localhost:5173. Public guest APIs use same-origin Next proxies and separate HttpOnly cookies; no expansion of credentialed public Laravel CORS is required. Use localhost consistently, not a mixture of 127.0.0.1 and localhost.

## 3. Start the system

Keep each command in a separate terminal:

**API**

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend'
php -d upload_max_filesize=8M -d post_max_size=12M -S localhost:8000 -t public public/index.php
```

**Storefront**

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\storefront'
npm.cmd run dev
```

**Admin**

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\admin'
npm.cmd run dev
```

**Scheduler**

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend'
php artisan schedule:work
```

**Queue worker**

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend'
php artisan queue:work --tries=3 --timeout=60
```

Open `http://localhost:5173/?view=orders` for staff and `http://localhost:3000/track` for guests. Existing staff accounts/authenticator setup are reused; no default account or new customer account system was added.

## 4. Order management and manual orders

1. Sign in as owner or operations. Search an order reference/customer name and independently filter payment and fulfilment state. Support role can read but cannot manage.
2. View immutable order items, prices, shipping/billing address, remaining quantities, booking attempts, notes, stock history, notification history and support requests. Add an internal note; it never appears in guest tracking.
3. Choose **Create manual order**. Find a published product/SKU and add active variants. Enter quantities, guest email and UK shipping/billing details, test delivery rate and optional TEST10 discount. Review the server total before confirming.
4. Confirm **Create unpaid order and invitation**. The shared checkout transaction reserves stock and creates immutable snapshots. A private one-use payment invitation is queued. It does **not** record payment and cannot be dispatched. Repeating the same creation request does not create another order/reservation/invitation.
5. Open its private payment-link .eml from `backend/storage/app/private/order-notifications/`. Open the `/pay-order#token=...` link in the storefront and click **Open secure payment invitation**. The token disappears from the URL, is consumed once, and customer access binds to that order. With Stripe deferred, the order stays unpaid; no fake success is shown. Phase 6 guide explains actual sandbox capture setup when you choose to do it.
6. An unattempted manual reservation expires after 65 minutes. `orders:maintenance` releases it only if no provider creation was attempted. A disabled Stripe refresh does not falsely count as an attempt. Ambiguous attempted payment outcomes retain Phase 6 safeguards; do not edit balances manually. Requesting another invitation requires an eligible unexpired unpaid manual order and a five-minute gap.

## 5. Paid order → two shipments

Because Stripe is deferred and no paid demo order was inserted into development, this workflow requires an existing genuinely verified paid order **or the isolated browser suite in section 8**. The application deliberately has no staff mark-paid shortcut. The isolated suite creates contract-test paid fixtures only in its separate database and never claims real capture.

1. View a confirmed paid order. Select quantities for the first parcel, an active dispatch location, service, weight (grams), length/width/height (cm), and optional instructions. A location can allocate only units committed there at payment; the app does not silently invent a transfer.
2. Choose **Get shipping quote**. Review exact items, destination, origin, parcel and test label cost. Quotes expire after ten minutes before allocation; a prepared uncertain booking retains its frozen request for safe retry.
3. Confirm the sandbox label booking. Download the protected SVG label. It is printable test material only; no real carrier accepts it.
4. Click **Dispatch parcel** and confirm. Order becomes partially_fulfilled when units remain. No stock deduction happens here because payment already committed it.
5. Repeat for the remaining units. The order becomes fulfilled once all units are dispatched, and delivered only when all dispatched quantities have verified delivered scans.
6. Repeated booking/dispatch requests reuse the existing receipt and notification. You cannot allocate more than the ordered quantity, use another order's item/quote, or dispatch an unpaid order.

## 6. Courier failure and cancellation tests

In backend .env set one scenario, clear config and restart API:

| Scenario | Behaviour |
|---|---|
| normal | Returns a local sandbox receipt/label |
| timeout_before_once | First attempt times out before receipt; retry same booking succeeds |
| timeout_after_once | Receipt exists but first response times out; retry same booking retrieves that receipt |
| reject | Booking is rejected; no receipt/label |

An unknown outcome holds the item allocation. Use **Retry same booking**, not a new parcel. Booking history shows each outcome. Unknown bookings cannot be cancelled until reconciled by a retry. Draft/rejected bookings or a booked label before dispatch can be cancelled. Sandbox cancellation confirms receipt voiding, releases fulfilment allocation, and never refunds or restocks a paid order. Dispatched/transit/delivered parcels cannot be cancelled through this workflow. Returns/refunds are Phase 8.

Return scenario to normal after testing. These are deliberate local simulations, not a carrier outage integration.

## 7. Guest tracking, events and notifications

1. At `/track`, enter an order reference and the exact order email. Every valid-shaped request returns the same neutral message; wrong details expose nothing. Five tracking links per order/hour and independent public request limits apply.
2. Run the scheduler/worker, or catch up manually:

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend'
php artisan orders:maintenance
php artisan queue:work --stop-when-empty --tries=3 --timeout=60
```

3. Open the private tracking-link .eml under `backend/storage/app/private/order-notifications/`. Links are one-use and expire after 30 minutes. Opening the link removes the token fragment before redemption. Click **Verify tracking access**. The resulting HttpOnly cookie lasts 24 hours and authorizes only that order. A typed reference alone grants no access; replaying a consumed link in another browser fails.
4. View sandbox parcel items/status/tracking and event timestamps. Street addresses, email, internal notes, provider receipts/labels and staff information are never returned by this portal. **Close tracking access** revokes the session.
5. From verified tracking, submit a support subject/message. It is saved on that order and visible to authorized staff. This is intake only; full support-ticket workflows remain Phase 10.
6. Generate a local signed sandbox event using a shipment UUID from admin:

```powershell
php artisan courier:sandbox-event 'SHIPMENT-UUID' in_transit
php artisan courier:sandbox-event 'SHIPMENT-UUID' out_for_delivery
php artisan courier:sandbox-event 'SHIPMENT-UUID' delivered
```

This command requires APP_ENV=local and the configured sandbox secret. It runs the signature-verified event service locally, not a real carrier callback. Actual HTTP ingestion endpoint: POST `/api/v1/courier/sandbox/webhook`, header `X-Sandbox-Signature: t=UNIX_SECONDS,v1=HMAC_SHA256(secret, timestamp + '.' + raw_body)`. Payload keys: id, provider_reference, tracking_number, state, occurred_at (ISO timestamp). Signature tolerance is 300 seconds; states allow in_transit, out_for_delivery, delivered and exception. Same event ID/body is deduplicated; same ID with different body is rejected. Stale/regressive scans are recorded as ignored, and never undo delivery/payment.
7. Dispatch, tracking update, invitation and support notifications have durable traceable rows. Workers save deterministic private .eml files; repeated jobs do not create duplicate copies. **No external email is sent.** Delayed delivery can make a link expire; request a new eligible link instead of editing its token.

## 8. Verification commands and isolated demonstration

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend'
composer validate --strict
composer format:check
composer test
cd ..\admin
npm.cmd run check
cd ..\storefront
npm.cmd run check
cd ..
node shared/tests/fulfilment-concurrency.cjs
```

On this PC, browser tests can use the bundled Playwright runtime:

```powershell
$env:PHP_BINARY='C:\Users\Muhammad Hassan Ali\.config\herd-lite\bin\php.exe'
$env:PLAYWRIGHT_MODULE='C:\Users\Muhammad Hassan Ali\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\node_modules\playwright'
node shared/tests/fulfilment-browser.cjs
```

On another machine install Playwright in a separate QA directory, point PLAYWRIGHT_MODULE to it, and install Edge/Chromium as required. The suite uses **isolated SQLite/storage**, random credentials and ports **3003/8009/5183**. It builds a separate `.next-qa`, runs an actual production Next server, logs in with MFA, fulfils in two parcels, tests timeouts/cancellation, downloads a sandbox label, verifies one-use tracking/support/manual invitations and runs the real database queue worker. It does not touch development data or call real payment/courier services. QA_WORK_ROOT and QA_OUTPUT_ROOT can redirect scratch and evidence. Only its own processes are stopped.

Additional regressions: `node shared/tests/commerce-browser.cjs`, `node shared/tests/catalogue.cjs`, `node shared/tests/storefront-shopping.cjs`. Each uses its own database. Do not run two Next QA builds simultaneously because they share `.next-qa`. Remove any generated `.next-qa/types` includes from storefront/tsconfig.json afterwards and run a regular storefront build before packaging/deploying.

## Deployment limits

Real courier selection, origin addresses/service mapping, carrier credentials/webhook policy, genuine labels, external email, approved delivery/tax rules and the deferred Stripe capture remain outstanding. Only SQLite concurrency/immutable guards were verified. Public proxy IP rate budgets, HTTPS, database-specific locking/guards, production retention/performance and wider assistive/device testing require release work. No Phase 8 customer accounts/returns/refunds were implemented.
