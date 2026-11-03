# Phase 8: local startup and acceptance guide

Project: `C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO`. Accounts, order ownership, returns, exchanges and the approved **test-only** refund sandbox are implemented here. XAMPP is not required: Laravel uses SQLite. Stripe capture, live refunds, real courier services and external transactional email remain deferred. Phase 9 is not started.

## 1. Apply this update

Use PowerShell. Preserve your existing .env and APP_KEY; encrypted staff factors and email payloads depend on that key.

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend'
composer install --no-interaction
npm.cmd ci --prefix media
php artisan migrate
php artisan config:clear
cd ..\storefront
npm.cmd ci
cd ..\admin
npm.cmd ci
```

Two additive migrations apply: `2026_10_26_000004_create_customer_after_sales_tables` creates 15 tables; `2026_10_26_000005_bind_return_units_to_delivery` binds claims to original delivered parcel units. Apply both before creating returns. The second fails safely if pre-existing preview claims need a verified mapping; never delete business claims to bypass it. Do not use migrate:fresh or db:wipe against your working database. No new dependency or secret is required. Fresh installation instructions and environment examples are in the root README; copy examples only when the destination file does not exist. Keep STRIPE_SANDBOX_ENABLED=false while Stripe is deferred. Use localhost consistently.

## 2. Start all applications

Open five PowerShell terminals. If `php` is not on PATH, replace it with `& 'C:\Users\Muhammad Hassan Ali\.config\herd-lite\bin\php.exe'`.

Terminal 1 — API:

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend'
php -d upload_max_filesize=8M -d post_max_size=12M -S localhost:8000 -t public public/index.php
```

Terminal 2 — storefront:

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\storefront'
npm.cmd run dev
```

Terminal 3 — admin:

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\admin'
npm.cmd run dev
```

Terminal 4 — scheduler (reconciliation and expiry):

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend'
php artisan schedule:work
```

Terminal 5 — private local email worker:

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend'
php artisan queue:work --tries=3 --timeout=60
```

Open storefront `http://localhost:3000/account`, guest returns `http://localhost:3000/returns`, staff returns `http://localhost:5173/?view=returns`, and finance `http://localhost:5173/?view=payments`. Health: `http://localhost:8000/api/v1/health`. Stop/restart queue workers after code changes. Existing staff/MFA accounts remain unchanged.

## 3. Test optional accounts

1. Register with a new email and a password of at least 14 characters. Accept terms/privacy explicitly. Guest checkout stays available without registration.
2. Run these once if you are not keeping the scheduler/worker open:

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend'
php artisan after-sales:maintenance
php artisan queue:work --stop-when-empty --tries=3 --timeout=60
```

3. Open the matching `.eml` privately under `backend/storage/app/private/customer-notifications/`. Check its To and Subject. Copy the complete account URL including `#purpose=...&token=...` into the same localhost storefront. Confirm verification; sign in. Tokens are single-use and expire after 30 minutes. They are removed from the URL before submission and never stored in browser localStorage. These are private local test copies, not actual outbound email.
4. Save pieces as a guest, then sign in: the guest list merges with account choices. Sign out and into a second account; the first account's saved choices must not appear. Guest storage is cleared only after the merge succeeds; failed/overflow merges keep the source list and offer Retry.
5. Edit your name; add/edit/delete an address and choose a default. A version conflict asks you to reload. Existing order addresses remain unchanged. Email change requires the current password and a proof link sent to the new address; confirmation revokes sessions. Recovery uses a separate neutral one-use link and revokes old sessions.
6. Enter an order reference under Link a guest order. The proof is sent to the immutable order email, which may differ from the account email. Only the requesting signed-in account can redeem it. A matching typed email/reference never grants access alone. Orders already linked to another account cannot be reassigned.
7. Create a second account and attempt the first order's detail URL/API or address ID. The API returns 404; staff cookies cannot authorize customer routes. Sign out to revoke customer access.

## 4. Test a partial return, inspection and refund

Development starts without fabricated paid/delivered orders. Unpaid manual orders deliberately cannot qualify. For a complete safe demo while Stripe is deferred, use the isolated automated browser harness below; it creates synthetic verified payment evidence only in a guarded QA database. Never edit payment/delivery states in development to fake eligibility.

With a genuinely verified paid/delivered sandbox order available:

1. Link it to your verified account, or open its one-use guest tracking link and visit `/returns`. Guest accounts remain optional.
2. Select one eligible delivered item and a partial quantity, reason, and refund or exchange. Confirm the displayed test policy. The test window is 30 days from each unit's verified delivery. Already claimed quantities cannot be requested again. Only net merchandise is credited; outbound delivery and tax are excluded under this test policy.
3. Sign in as owner/operations at the admin Returns workspace. Confirm receipt of the full quantity in that request, outcome, active receiving location and inspection reason. `accepted_resellable` restores original stock once; `accepted_damaged` and `rejected` do not. Rejected requests release their unit claims. Mixed outcomes should be submitted as separate item/quantity requests.
4. Owner/finance opens Payments. Review remaining inspected credit, enter the exact amount in GBP pence (2000 = £20), reason, local outcome and explicit confirmation. Operations/support cannot approve refunds. All states are labelled `sandbox_*`; no real money moves.
5. Try an amount above the remaining balance: reject without another receipt. Retry a lost response using **Reconcile same refund attempt**, which reuses its original key/receipt. Pending and unknown outcomes hold credit; failed ones release it. Refund reconciliation never changes stock.
6. To deliver an asynchronous result to a pending local receipt:

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend'
php artisan refund:sandbox-event 'ATTEMPT-UUID-FROM-CASE' succeeded
# Or use failed instead of succeeded.
php artisan after-sales:maintenance
```

The CLI accepts only local environment, a pending existing receipt and a terminal outcome. Live adapter/refund verification remains pending; local receipts must never be treated as Stripe evidence.

## 5. Test exchanges

After accepted inspection, review target stock, current replacement total, original credit and signed difference in Returns. A changed price requires fresh confirmation. An unavailable variant fails without consuming credit or creating an order. Staff can switch an unissued/expired exchange to refund with a reason; an issued/pending replacement cannot be silently changed.

Equal/cheaper replacements create immutable orders with `payment_state=exchange_credit`, valid funding links and no paid_at/card-payment fiction. Their normal Orders shipment tools become available. A cheaper replacement leaves only the difference eligible for refund review. Higher-priced replacements stay `awaiting_exchange_payment`, cannot ship, and reserve stock for 30 minutes. Stripe top-ups are deferred; maintenance expires those holds and releases stock/credit. Never manually mark them paid. Replacement orders funded by exchange credit currently require staff review for any further after-sales request; the automatic return policy requires an original verified paid order.

## 6. Repeat verification

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend'
composer test
composer format:check
composer validate --strict
composer audit
cd ..\storefront
npm.cmd run check
cd ..\admin
npm.cmd run check
```

Production browser and independent-process QA require Playwright. On this PC it is already bundled:

```powershell
cd 'C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO'
$env:PHP_BINARY='C:\Users\Muhammad Hassan Ali\.config\herd-lite\bin\php.exe'
$env:PLAYWRIGHT_MODULE='C:\Users\Muhammad Hassan Ali\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\node_modules\playwright'
$env:QA_WORK_ROOT=Join-Path $env:TEMP 'elvarqo-phase8-qa'
$env:QA_OUTPUT_ROOT=Join-Path $env:TEMP 'elvarqo-phase8-results'
node shared/tests/accounts-browser.cjs
node shared/tests/accounts-concurrency.cjs
$env:QA_RACE_KIND='exchange'
node shared/tests/accounts-concurrency.cjs
Remove-Item Env:QA_RACE_KIND
```

Use free ports **3004 / 8010 / 5184**. The harness uses production Next, actual PHP API, Edge headless, real queue worker, random QA-only credentials/key, a separate SQLite database and guarded synthetic provider evidence. Test keys are not real Stripe credentials. QA fixtures refuse to operate outside their private database directory. Email links and credentials stay in the private QA directory; screenshots/results contain synthetic data only. Run one Next browser harness at a time. On another computer install Playwright in ignored `.qa/` and set PLAYWRIGHT_MODULE to its module directory. No development orders, stock, staff or .env is seeded/changed.

Normal verification uses the `.next` build. QA uses `.next-qa`; remove only QA-generated `.next-qa/...` include entries from storefront/tsconfig.json after running QA if Next added them, then format/typecheck normally. This is unrelated to customer data.
