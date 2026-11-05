# ELVARQO — Phase 8

React storefront, React admin and Laravel API for elvarqo.uk. The current implementation covers Phases 1–8: protected staff/catalogue/inventory tools, public shopping and guest checkout, order/fulfilment operations, optional customer accounts and after-sales. New checkout now uses Wise bank transfer with owner receipt confirmation. Receiving details remain pending by request, so checkout is disabled locally. Campaign concept imagery and unfinished dashboard figures remain labelled previews.

Phase 8 adds optional verified customer accounts, protected order linking/history, address books and wishlist sync, item-level returns, inspection/restocking, explicit exchange funding/price differences and permission-controlled **local refund sandbox**. Current commands and manual/automated checks: [Phase 8 setup](docs/PHASE8-SETUP.md). Architecture/API and completion evidence are in docs/PHASE8-ARCHITECTURE.md, PHASE8-API.md and PHASE8-REPORT.md. The user approved the test-only 30-day policy; live Stripe refunds/payments, real courier and external email remain deferred. Phase 9 has not started.

## Current payment setup — Wise bank transfer

See [Wise checkout setup and testing](docs/WISE-CHECKOUT.md). Implementation is complete; your verified GBP receiving details and a real transfer test remain pending. Add WISE_ACCOUNT_HOLDER, WISE_SORT_CODE and WISE_ACCOUNT_NUMBER to the existing ignored backend/.env. Keep WISE_ENABLED=false until the details are checked; then set it to true, run php artisan config:clear and restart backend workers. No API token is needed.

Customers receive an exact GBP amount, unique reference and payment deadline. Orders stay unpaid until the owner checks the settled receipt in Wise and uses the protected confirmation form in admin Orders. Scheduler command commerce:wise-maintenance releases expired unpaid reservations. The local refund simulator is blocked for Wise orders. Tax/delivery rates remain test-only; external email, real courier integration and exchange top-up collection remain deferred. Earlier Stripe sections below are historical and are superseded for new checkout by this Wise guide.

## Hostinger / MySQL staging preparation

Local MySQL-compatible preparation and preservation tests are complete. Laravel remains the backend and the current development .env still uses SQLite. Use [MySQL migration and staging guide](docs/MYSQL-MIGRATION.md) for private snapshots, the guarded transactional import, disposable database tests and Hostinger compatibility gates. The secret-free staging template is [backend-hostinger.env.example](docs/examples/backend-hostinger.env.example). Remote staging is not deployed; nameservers are unchanged. Hostinger support confirmed disabled proc_open and minute cron support. Use [native GD uploads and bounded cron setup](docs/HOSTINGER-COMPATIBILITY.md), with PHP >=8.4.1. Actual trigger grants and deployed runtime/cron behavior remain to be verified.

## Requirements

Node.js 24 LTS, npm 11, PHP 8.4 or 8.5, Composer 2, Git. Enable PHP extensions required by Composer including PDO SQLite, sqlite3, mbstring, XML/DOM, fileinfo, curl, openssl and zip. This lockfile resolves Symfony 8 and PHPUnit 12, so PHP 8.4+ is needed for the development dependency set even though Laravel itself supports PHP 8.3.

On Windows use PowerShell and `npm.cmd` if script execution policy blocks `npm`. Commands below use `npm`, which works on macOS/Linux and with the usual Windows execution policy. Start in this ELVARQO directory. Each application has its own lockfile and dependencies. Do not run the Laravel skeleton's frontend npm commands; the API uses no bundled frontend build.

## First installation

Backend:

```powershell
cd backend
composer install --no-interaction
npm ci --prefix media
Copy-Item .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
cd ..
```

Copy environment files only on first setup; preserve existing `.env` values on subsequent installs. On macOS/Linux substitute `cp` for `Copy-Item`. SQLite defaults to `backend/database/database.sqlite`; no database service or credentials are needed. For a custom database file set `DB_DATABASE` to its absolute path. Never use destructive reset commands against a database you need to preserve. Subsequent schema changes use `php artisan migrate`.

Storefront:

```powershell
cd storefront
npm ci
Copy-Item .env.example .env.local
cd ..
```

Admin:

```powershell
cd admin
npm ci
Copy-Item .env.example .env.local
cd ..
```

Frontend environment variables are public configuration, never secrets. Both examples target `http://localhost:8000/api/v1`. Restart frontend servers after changing environment variables; rebuild production assets after changing public configuration. Laravel secrets belong only in the ignored `backend/.env`. Generate your own `APP_KEY`; no application key is supplied in tracked examples.

## Run locally

Open three terminals at ELVARQO:

```powershell
# Terminal 1
cd backend
php -d upload_max_filesize=8M -d post_max_size=12M -S localhost:8000 -t public public/index.php
```

```powershell
# Terminal 2
cd storefront
npm run dev
```

```powershell
# Terminal 3
cd admin
npm run dev
```

Visit [storefront](http://localhost:3000), [admin](http://localhost:5173) and [health JSON](http://localhost:8000/api/v1/health). Use `localhost` consistently: `127.0.0.1` is a different browser origin and is deliberately absent from CORS. Frontends must occupy their documented ports. Stop a conflicting process instead of accepting an alternative port.

The health JSON reports API/database connectivity, not overall commerce readiness. The Phase 2 home pages show the visual preview. Open http://localhost:3000/development and http://localhost:5173/?view=development to see “Connected to Laravel. Database is ready.” and a request ID. The admin now requires a privately provisioned staff account and mandatory two-factor verification. Catalogue, inventory and staff security records are real; other business dashboard figures remain demonstrations. Follow [staff setup and testing](docs/STAFF-ACCESS.md) to create your owner and enroll your authenticator.

## Queue, sandbox mail and storage

In a fourth terminal:

```powershell
cd backend
php artisan foundation:probe
php artisan queue:work --stop-when-empty --tries=1 --timeout=30
php artisan queue:failed
Get-ChildItem storage/app/private/foundation
Get-Content storage/logs/laravel.log -Tail 40
```

The queued probe ID appears in its storage receipt and the log mail subject/body. Email goes to the log only; no external message is sent. `queue:failed` should be empty. For continuous processing use `php artisan queue:work --tries=1 --timeout=30`. The probe refuses to run outside local/database-queue/log-mail configuration. Files are private local storage, not publicly linked uploads.

## Verification

```powershell
cd backend
composer validate --strict
composer format:check
composer test
php artisan migrate:status
cd ../storefront
npm run check
cd ../admin
npm run check
cd ..
```

Use `composer format` in backend and `npm run format` in each React app to fix formatting. `check` includes formatting, lint, TypeScript and a production build. Storefront production smoke: `npm run build`, then `npm start` on port 3000. Admin: `npm run build`, then `npm run preview` on 5173. Stop dev servers before testing those same ports.

Manual outage/recovery check:

1. Open the diagnostics routes above in both React applications and verify the connected state.
2. Stop the API terminal with Ctrl+C.
3. Click **Retry connection** on each page. Within five seconds, each should explain that Laravel/database connectivity must be checked; controls become available again.
4. Restart Laravel using the command above and click **Retry connection** again. Both pages should recover without reloading or changing configuration.
5. In browser Network tools confirm requests go directly to port 8000 and `Access-Control-Allow-Origin` matches the calling frontend. An unapproved origin must receive no allow-origin header. CORS is a browser policy, not API authentication.

GitHub Actions runs backend and both frontend checks from lockfiles, migrates SQLite, and exercises the real sandbox queue/mail/storage probe. Local test evidence and remaining inputs are in [docs/PROGRESS.md](docs/PROGRESS.md); architecture and version sources are in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md). Original PDFs are retained in docs. No provider accounts, production credentials or production deployment are needed for Phase 1.


## Phase 2 routes and shared UI

The storefront home and collections use published catalogue records. Product choices add to a persistent guest bag; checkout provides server-calculated totals and configured Wise bank-transfer instructions with manual owner receipt confirmation. Optional verified accounts, protected order linking, address books and wishlist sync are available at /account. Newsletter signup remains unavailable. Editorial/help/policy destinations are honest route placeholders, not approved business content.

The admin home is a labelled demonstration overview. Sidebar links open workspace placeholders using ?view=orders, ?view=products etc. Filter demonstration orders locally or select the 30-day period to inspect an empty state. No action writes operational records. At narrower widths, open the navigation drawer from the menu button.

- Storefront UI reference: http://localhost:3000/design-system
- Admin UI reference: http://localhost:5173/?view=design-system
- Storefront API diagnostics: http://localhost:3000/development
- Admin API diagnostics: http://localhost:5173/?view=development

shared/ui/ is the canonical token/component source. Each app synchronizes it into src/ui before dev, build, format and check. Do not edit generated src/ui copies. After changing shared source while dev is running, run npm run sync-ui in both apps or restart them. The root shared folder must stay alongside both applications. The existing npm ci installation commands and lockfiles still apply; no new production dependencies are needed.

## Phase 2 manual checks

1. Check both home pages at 390, 768 and 1440 CSS pixels. Confirm no horizontal page scrolling and readable cards/tables.
2. Set browser zoom to 200%, check navigation and content reflow, then reset to 100%. The base browser suite uses equivalent viewport reflow; the supplemental Chromium test also verified actual browser zoom factor 2 in both apps without horizontal overflow. Repeat on your target devices.
3. Focus Shop (or the mobile menu) with the keyboard and press Enter. Tab/Shift+Tab stay inside; Escape, Close and backdrop dismissal return focus to the trigger. Repeat for search, bag, admin navigation and the UI reference dialog.
4. In the UI reference submit an empty email, correct it and validate again. Confirm errors are labelled, the entered value is preserved and no request is sent. Exercise pagination and disabled/loading states.
5. Enable reduced motion and confirm decorative transitions/animations disappear. Disable JavaScript and confirm the storefront still provides meaningful HTML and navigable Collections/editorial links; interactive overlays require JavaScript.
6. Confirm every sample price, image and dashboard figure is visibly identified as a concept/fixture. Keep existing backend health checks working from the diagnostic routes.

## Optional repeatable browser checks

With all three applications running, start at the ELVARQO root. Playwright is installed into an ignored QA folder, not into either production application:

```powershell
npm install --prefix .qa --no-save playwright
# Windows uses installed Microsoft Edge. On Linux/macOS, install Chromium:
# ./.qa/node_modules/.bin/playwright install chromium
node shared/tests/staff-security.cjs
```

The previous Phase 2 acceptance.cjs suite is historical because its admin checks predate sign-in. The current staff-security.cjs suite runs isolated services on 8005/5180. The historical Phase 2 suite ran both apps at the documented ports, saves screenshots and results in docs/verification/phase2, tests keyboard containment/recovery, labels/errors, reduced motion, fixtures, no-JavaScript public navigation, and retained API connectivity. The base suite tests equivalent 200% reflow; the supplemental native-zoom script verifies actual browser zoom independently. Screenshot review and manual assistive-technology checks still complement automation. See docs/PHASE2-DESIGN.md for reference coverage, assets and deliberate deviations.


Supplemental native zoom test (optional, isolated Chromium and a test-only local extension):

```powershell
$env:PLAYWRIGHT_BROWSERS_PATH = Join-Path (Get-Location) '.qa/browsers'
node .qa/node_modules/playwright/cli.js install chromium
node shared/tests/native-zoom.cjs
```

On macOS/Linux use `export PLAYWRIGHT_BROWSERS_PATH="$PWD/.qa/browsers"` before the same Node commands. The test browser/profile/extension stay in ignored .qa; the user's browser profile is never opened. It sets each test tab to 200%, asserts actual browser zoom and reflow, saves evidence, resets zoom and closes the isolated browser.


## Phase 3 staff access

Before your first admin sign-in, run `php artisan staff:create YOUR_EMAIL --name="Your Name" --role=owner` in backend and choose your own password at the hidden prompts. Then enroll an authenticator and save recovery codes. No default account exists. See [step-by-step staff setup, recovery and verification](docs/STAFF-ACCESS.md) and [security architecture](docs/PHASE3-SECURITY.md). Phase 3 backend checks pass 26 tests / 176 assertions; real browser security checks pass 17 grouped cases. Full staff management is Phase 10; This Phase 3 section is a historical completion snapshot; Phase 4 is now implemented as described below.


## Phase 4 catalogue and inventory

The project is in `C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO`. Existing installations: run `npm ci --prefix backend/media` at the root, then `php artisan migrate` inside backend. Preserve your current environment files and APP_KEY. The development SQLite migration is already applied. Node 24+ powers the dedicated Sharp image worker; no XAMPP/GD extension is required. See [Phase 4 setup and manual testing](docs/PHASE4-SETUP.md).

Sign in at http://localhost:5173/?view=products to create collections and draft products, add colour/size variants and verified measurements, upload images, preview and publish. Use http://localhost:5173/?view=inventory for locations, reasoned stock adjustments, transfers and history. Public reads: http://localhost:8000/api/v1/catalogue/products. The storefront connects to these catalogue APIs through the Phase 5 shopping pages.

No saleable products, stock quantities, locations or personal owner credentials were seeded. Published products must be explicitly unpublished before editing. Archive operations retain historical references and private originals. See [API and retention contract](docs/PHASE4-API.md), [architecture](docs/PHASE4-ARCHITECTURE.md) and docs/PROGRESS.md. Run `node shared/tests/catalogue.cjs` for isolated Phase 4 browser acceptance. General CSV tooling remains Phase 10. Checkout reservations are implemented in Phase 6; see the current setup guide below.


## Phase 5 storefront shopping

Home, /collections, /collections/{slug}, /shop, /search, /products/{slug} and /wishlist now use the real published catalogue. No Phase 5 dependency install or migration is needed on this configured PC. Restart the existing three applications with the commands above. If the catalogue is empty, publish your real products in admin first; no test merchandise is seeded.

Follow [Phase 5 startup and testing](docs/PHASE5-SETUP.md), [completion report](docs/PHASE5-REPORT.md) and [Phase 6 bag handoff](docs/PHASE5-BAG-HANDOFF.md). Public pages use current request-time rendering, with explicit no-store API access and intentional 404/308 product responses. NEXT_PUBLIC_API_URL is unchanged; optional server origin, canonical site URL and safe noindex defaults are in storefront/.env.example.

Run `node shared/tests/storefront-shopping.cjs` for isolated production-mode browser acceptance on ports 3001/8007. It creates private test data and a separate ignored .next-qa build, leaving your normal services and database intact. Prior Phase 2–4 sections above are historical snapshots; their statements that storefront shopping is not connected are superseded here. Phase 6 supersedes the temporary selection-only handoff with a real bag and checkout. Actual Stripe sandbox capture remains pending local credentials; no live purchasing is enabled.


## Phase 6 — bag, checkout and sandbox payments

Read [Phase 6 setup](docs/PHASE6-SETUP.md) before testing. Apply `php artisan migrate` in backend, then run the API (8000), storefront (3000), admin (5173), `php artisan schedule:work` and `php artisan queue:work --tries=3 --timeout=60`. XAMPP is unnecessary for the default SQLite setup. Existing environment files and APP_KEY must be preserved.

Stripe sandbox is disabled by default. Test keys/signing secret belong only in ignored backend .env; no live key is supported. Tax and delivery prices are labelled test assumptions awaiting merchant approval. Order confirmation copies are saved under backend/storage/app/private/commerce-mail; no external email is sent. Do not treat the HTTP-fake contract/browser fixtures as an actual Stripe purchase. Phase 7 now adds protected order operations, unpaid manual invitations, partial shipments, local courier sandbox labels, secure guest tracking and minimal support intake. See docs/PHASE7-SETUP.md; real courier/email and Stripe capture remain deferred.

API/state reference: docs/PHASE6-API.md. Architecture: docs/PHASE6-ARCHITECTURE.md. Acceptance status and remaining work: docs/PHASE6-REPORT.md and docs/PROGRESS.md. Independent last-unit contention test: `node shared/tests/commerce-concurrency.cjs`; isolated production UI test: `node shared/tests/commerce-browser.cjs` (Playwright required).


## Phase 7 — order operations and courier sandbox

Use [Phase 7 setup and testing](docs/PHASE7-SETUP.md), [API/state contract](docs/PHASE7-API.md), and [report](docs/PHASE7-REPORT.md). Apply the additive migration in backend and keep the API, both React apps, scheduler and queue worker running. `orders:maintenance` expires eligible unattempted manual reservations and queues private notifications.

Admin `http://localhost:5173/?view=orders` supports filters, notes, manual order review, item/parcel quotes, reviewed sandbox label booking, safe retries, cancellation eligibility and partial dispatch. Staff permissions are enforced independently by Laravel. Guest tracking at `http://localhost:3000/track` requires a one-use verification link, then an order-scoped HttpOnly session. Minimal support intake is available after verification.

Courier labels are **local sandbox SVGs, not valid for postage**. No real carrier or postage purchase exists. External email is still disabled; private .eml copies live in backend/storage/app/private/order-notifications. Manual orders remain unpaid until provider evidence; no staff mark-paid shortcut was added. The user explicitly deferred the Phase 6 Stripe purchase test. Phase 8 now supplies customer accounts, returns, exchanges and the local refund sandbox; use the current Phase 8 guide.

Verification: `node shared/tests/fulfilment-concurrency.cjs` and `node shared/tests/fulfilment-browser.cjs` (Playwright required). Both create isolated databases and clearly labelled paid contract fixtures instead of seeding development or claiming real Stripe capture. The browser suite uses ports 3003/8009/5183. Full local instructions and limitations are in the setup guide.


## Phase 8 — optional accounts and after-sales

Start with [Phase 8 setup and testing](docs/PHASE8-SETUP.md), [API/permissions](docs/PHASE8-API.md), and [completion report](docs/PHASE8-REPORT.md). Accounts: http://localhost:3000/account; guest returns: http://localhost:3000/returns; staff returns: http://localhost:5173/?view=returns; finance sandbox: http://localhost:5173/?view=payments. All earlier phase sections are historical context; this guide is current.

The additive customer/after-sales migration creates 15 tables. Keep the API, both React applications, scheduler and queue worker running. `after-sales:maintenance` queues account notifications, reconciles local refund evidence and releases expired unpaid exchange holds. Private verification/recovery/claim links are in backend/storage/app/private/customer-notifications; no external email is sent. Preserve .env and APP_KEY.

The approved policy is test-only: thirty days after verified delivery, inspection before restocking, and merchandise-only local refund credits. Resellable accepted units restock once; refund callbacks never restock. Finance approval requires an exact amount, reason and final review dialog. Higher-priced exchanges remain unpaid while Stripe top-ups are deferred. Mutation under this test policy is disabled outside local/testing. No live Stripe refund or approved live merchant policy is claimed. Phase 9 has not started.

## Bulk catalogue importer

Owner/operations staff can open Products → Bulk Import to preview JSON and an optional image ZIP, review supplier/category matches and create drafts with per-row retry reports. See [installation, schema, security, conversion and import instructions](docs/BULK-IMPORT.md). The prepared first batch is in docs/imports/women-knitwear-first-batch; open PREVIEW.html before importing. Unknown prices/options/material/measurements remain empty, publication checks are unchanged, and no supplier order fulfilment is provided. Apply the new additive migration locally before using the feature. This feature has not been deployed or imported into the real store.
