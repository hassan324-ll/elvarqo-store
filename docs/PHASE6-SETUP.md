# Phase 6 — local setup and sandbox test guide

Project: `C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO`. Phase 6 implements bag, checkout, reservations, Stripe sandbox adapter and read-only admin orders. The user requested finishing with real Stripe sandbox capture **pending**. Automated provider responses are contract fakes; they are not evidence of a Stripe purchase. Phase 7 has not started.

## 1. Update this existing installation

Open PowerShell. Do not overwrite existing `.env` files or regenerate an existing APP_KEY.

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend'
composer install --no-interaction
npm.cmd ci --prefix media
php artisan migrate
php artisan config:clear
```

The Phase 6 migration is additive. Keep a private database backup before migrations. Do not run `migrate:fresh`, `db:wipe` or QA fixture scripts against development data. Existing local SQLite remains the database; **XAMPP and MySQL are not required**. PHP and Composer must be on PATH. On this PC PHP is also available at `C:\Users\Muhammad Hassan Ali\.config\herd-lite\bin\php.exe`.

For a fresh source download only, first copy `backend/.env.example` to `backend/.env`, create `backend/database/database.sqlite` and run `php artisan key:generate`, then migrate. Install React dependencies with `npm.cmd ci` in each application. Node 24 and PHP 8.4/8.5 are required by the lockfiles. Full first-install instructions remain in the root README.

## 2. Environment settings

Add missing lines to the existing **backend/.env**; secret values stay only in that ignored file:

```dotenv
STOREFRONT_ORIGIN=http://localhost:3000
STRIPE_SANDBOX_ENABLED=false
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
```

Keep `APP_ENV=local`, `APP_DEBUG=false`, `DB_CONNECTION=sqlite`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`. No live key is supported. Leave Stripe disabled to test the bag and reviewed totals without credentials.

Add missing lines to **storefront/.env.local**:

```dotenv
NEXT_PUBLIC_API_URL=http://localhost:8000/api/v1
CATALOGUE_API_URL=http://localhost:8000/api/v1
COMMERCE_API_URL=http://localhost:8000/api/v1
STOREFRONT_ORIGIN=http://localhost:3000
STOREFRONT_INDEXING=false
```

`COMMERCE_API_URL` is server-only. Never expose Stripe secrets with NEXT_PUBLIC_ or VITE_. Staff origin remains `http://localhost:5173`; staff cookie/CSRF/CORS isolation is unchanged. Guest commerce uses same-origin Next route handlers; it does not need credentialed public Laravel CORS.

## 3. Run the applications

Use five PowerShell terminals, leaving each running:

**Terminal A — API**

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend'
php -d upload_max_filesize=8M -d post_max_size=12M -S localhost:8000 -t public public/index.php
```

**Terminal B — storefront**

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\storefront'
npm.cmd ci
npm.cmd run dev
```

**Terminal C — admin**

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\admin'
npm.cmd ci
npm.cmd run dev
```

**Terminal D — scheduled payment reconciliation / outbox dispatch**

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend'
php artisan schedule:work
```

**Terminal E — database queue worker**

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend'
php artisan queue:work --tries=3 --timeout=60
```

Open storefront `http://localhost:3000`, admin `http://localhost:5173/?view=orders`, health `http://localhost:8000/api/v1/health`. Use `localhost` consistently. A different storefront port must match STOREFRONT_ORIGIN in both applications. The scheduler is necessary for abandoned payment reconciliation; keep it running during checkout testing.

## 4. Test without Stripe keys

1. Sign into admin using your existing staff account and authenticator. Create and publish a real product and an active variant; add stock through Inventory if your catalogue is empty. No demonstration products or stock were added to development.
2. In the storefront choose a colour and size, then **Add to bag**. Open **View bag**; reload to verify persistence. Change quantity or remove a line. Invalid or excessive quantities show a recoverable error.
3. Choose **Checkout as guest**, enter a valid UK address and continue. Invalid postcode/email/delivery choices are rejected by Laravel; input stays in the form.
4. Pick standard or express. Optionally use **TEST10** for 10% off merchandise with a £50 minimum. These are unlimited test discounts, valid 2026-01-01 through 2029-12-31 UTC; they are not launch promotions.
5. Review the full server total. For one £68 item + TEST10 + standard: £68 − £6.80 + £4.95 + £0 test tax = **£66.15**. Express is £8.95. All rates and 0% tax are test assumptions, **not an approved UK VAT treatment or courier service**.
6. With sandbox disabled the payment button stays disabled and no order/reservation/payment is created. This is the expected current default. Bag contents do not reserve stock.
7. Stop API Terminal A and refresh your bag to see recovery. Restart it and refresh again. Use an incognito window to check guest isolation.

## 5. Complete the pending real Stripe sandbox acceptance

This section is **not yet executed**. It requires your Stripe sandbox account. Do not paste keys into chat or source control.

1. Install the Stripe CLI using its official Windows installation instructions: https://docs.stripe.com/stripe-cli/install . Authenticate the CLI with your sandbox account using `stripe login`.
2. In another terminal run:

```powershell
stripe listen --events checkout.session.completed,checkout.session.expired,checkout.session.async_payment_succeeded,checkout.session.async_payment_failed --forward-to http://localhost:8000/api/v1/payments/stripe/webhook
```

3. Put that listener's `whsec_...` signing secret and your sandbox `sk_test_...` secret in backend `.env`; set `STRIPE_SANDBOX_ENABLED=true`. Run `php artisan config:clear` and restart the API. Keep the listener, scheduler and worker running. The adapter pins Stripe API version **2025-06-30.basil**. A deployed webhook endpoint should use the same API version; do not enable live mode.
4. Review a stocked item again, accept the sandbox terms and continue. An order is created with immutable price/address/item snapshots and stock reserved. The status page provides **Continue hosted payment**, which opens Stripe Checkout. Card details go only to Stripe.
5. Use the official Stripe success test card **4242 4242 4242 4242**, a future expiry and any three-digit CVC on Stripe's hosted page. See https://docs.stripe.com/testing for current test cards. Never use a real card.
6. Return to the status page and select **Refresh verified payment status**. Confirm paid/confirmed/unfulfilled, one order in admin, reserved quantity returned to its previous value and on-hand decreased once. The private confirmation is queued transactionally.
7. Test decline with **4000 0000 0000 9995**. An open hosted session allows another card attempt on the **same** order. Browser returns, `?paid=true` or modified browser totals never confirm payment.
8. Abandon a hosted session and let it expire (one hour; reconciliation has a five-minute local safety margin). Keep the scheduler running, or run the commands below. Stock is released only after verified Stripe expiry. The bag survives. **Review bag and retry** restores saved contact/address/delivery details, but obtains a fresh quote and attempt.
9. Replay the actual event from Stripe Workbench or use `stripe events resend <evt_id> --webhook-endpoint=<we_id>` for a registered test endpoint. Check that order/payment/stock/outbox counts do not grow. CLI `stripe trigger` events for unrelated fixtures are not an ELVARQO purchase and should be rejected.

For manual catch-up:

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend'
php artisan commerce:reconcile
php artisan commerce:mail
php artisan queue:work --stop-when-empty --tries=3 --timeout=60
```

Mail is saved only to `backend/storage/app/private/commerce-mail/<order UUID>.eml`, one deterministic file per paid order. **No external order email is sent**, regardless of MAIL_MAILER. External email delivery needs a later explicitly configured provider; generic SMTP cannot promise exactly-once delivery after a crash.

If Stripe is unreachable, stock stays reserved. Creation retries use the same frozen provider request and idempotency key. After 23 hours of unresolved creation, the order is held for review instead of risking another charge. Mismatched amounts/currency/mode/identity and paid evidence after an already released reservation also require review. Phase 6 admin is intentionally read-only; no automatic refund, dispatch or manual financial override is provided. Do not edit paid snapshots or stock balances directly.

## 6. Verification commands

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend'
composer validate --strict
composer format:check
composer test
cd ..\storefront
npm.cmd run check
cd ..\admin
npm.cmd run check
cd ..
node shared/tests/commerce-concurrency.cjs
```

The concurrency command creates its **own** temporary QA database. Two independent PHP processes race for one unit. HTTP provider fakes are confined to guarded CLI fixtures/tests; application routes never simulate a payment.

Browser suites require Playwright and Edge/Chromium. Set PLAYWRIGHT_MODULE to an installed Playwright package and PHP_BINARY if PHP is not on PATH, then run:

```powershell
node shared/tests/commerce-browser.cjs
node shared/tests/storefront-shopping.cjs
node shared/tests/catalogue.cjs
```

They use isolated databases and ports 3002/8008/5182 (Phase 6), 3001/8007 (shopping), 5181/8006 (catalogue). QA_WORK_ROOT and QA_OUTPUT_ROOT can redirect their private scratch and redacted evidence. They start/stop only their own processes. A QA Next build may add `.next-qa/types` to tsconfig includes; remove that generated include after QA and run the regular storefront build before packaging/deploying. No hosted CI or deployment has been run.
