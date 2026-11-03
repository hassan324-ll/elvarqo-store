Current product entry uses a unified details-and-images form. See [Adding products and images together](CATALOGUE-ENTRY.md) for the updated flow; the historical manual acceptance steps below predate it.

# Phase 4 — setup and local verification
26 October 2026. Project: `C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO`.

## Update your existing installation

Preserve existing environment files, APP_KEY and database. PowerShell:

```powershell
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO"
composer install --working-dir=backend
npm ci --prefix backend/media
cd backend
php artisan migrate
cd ..
```

The new additive migration creates catalogue/stock tables without seeding saleable products, collections, locations or quantities. It has already been applied to this development SQLite database. Existing staff accounts remain intact. No XAMPP database service is required. Dependencies in both frontends are unchanged; use `npm ci` in each frontend only for a fresh install or after changing its lockfile. Use `npm.cmd` if PowerShell blocks npm.

The image worker needs Node 24+ and the pinned Sharp dependency in backend/media. No PHP GD/Imagick extension is required. If Node is absent from the backend process PATH, set MEDIA_NODE_BINARY to its absolute executable path in backend/.env. Restart the backend after changing configuration. Keep APP_KEY and private storage together with database backups. The private catalogue image directory must be writable by PHP; the Node process runs under that same trusted application user. Do not expose storage/app/private through a web-server alias.

## Run three terminals

Backend (this command sets upload limits for Phase 4 images):

```powershell
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend"
php -d upload_max_filesize=8M -d post_max_size=12M -S localhost:8000 -t public public/index.php
```

Storefront:

```powershell
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\storefront"
npm run dev
```

Admin:

```powershell
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\admin"
npm run dev
```

Stop your previous backend terminal with Ctrl+C before starting the replacement on port 8000. The native PHP server is for local development only. Existing `php artisan serve --host=localhost --port=8000 --tries=1` also works but inherits PHP's upload limit (2MB on the installed runtime); use the command above for the advertised 8MB limit. A deployed server must configure equivalent PHP/web-server limits independently.

Use localhost consistently: public site 3000, admin 5173, API 8000. Staff CORS, session and CSRF configuration stays as Phase 3; do not substitute 127.0.0.1. Public catalogue reads allow the configured public origins without credentials. Staff writes use the private credentialed API and CSRF token. No new production credentials are needed.

## Sign in

Open http://localhost:5173/?view=products and sign in with your staff account plus authenticator. If no account exists, follow STAFF-ACCESS.md and run `php artisan staff:create YOUR_EMAIL --name="Your Name" --role=owner` in backend. There is no default login. Owner and operations can manage the catalogue; support can read it. Finance has no catalogue access in the current Phase 3 role map.

## Full manual acceptance journey

1. Products → Collections & categories: create a collection or category, assign a unique lowercase slug, choose its navigation position and enable Active in public catalogue. Save it.
2. Add product. Enter your actual product title/slug/type, plain-text description, verified material/fit/care information and SEO title/description. Select the collection.
3. Add four variants for two colours and two sizes. Give each a globally unique uppercase SKU, GBP selling price and low-stock threshold. Barcode and weight are optional. Compare-at price, if supplied, must exceed the selling price. Optional fit/care and measurement overrides are under each variant's disclosure.
4. Add garment measurement rows in cm for every active size (chest, length, shoulder); complete variant overrides may supply individual dimensions. Save draft. Zero price can be saved in a draft, but every active variant needs a positive price before publication. The API stores integer minor units (6800 = GBP 68.00); the form accepts up to two decimal places.
5. Upload an actual JPEG, PNG or WebP with descriptive alternative text. It must be still, at least 200px per side, no more than 8MB and 24MP. SVG, corrupt, animated and unsupported images are rejected. Select a variant for a variant-specific image, or leave it shared. Every active variant needs a shared or matching image. You must save product edits before changing media.
6. Upload another image; change alt text or variant links, move images earlier/later and choose Save image order & alt text. The first saved image is primary. The server retains a private original and three WebP files for 640/1280/1920 target widths without enlargement. Responsive source descriptors omit duplicate actual widths for small originals. Public routes never expose original files or draft derivatives.
7. Preview saved product. Confirm actual saved images, variants/prices, fit/care and measurements. Unsaved product changes disable saved preview/publication until saved; validation failures preserve your entered fields. Preview is a private staff representation. Storefront shopping controls connect in Phase 5.
8. Publish product and confirm. The server checks all publication requirements independently. Open `http://localhost:8000/api/v1/catalogue/products/YOUR_SLUG`. It must return only published content, active variants, permitted media and active collections. The list endpoint is `/api/v1/catalogue/products`.
9. Inventory: add your real warehouse location name/code. Inspect the variant; choose location, enter a signed adjustment (+20 means incoming stock), supply a reason and optional PO/reference/note. Review then confirm. Check on-hand/reserved/available and the immutable history with actor ID. Negative stock below reserved is rejected.
10. Add a second location and transfer a positive quantity. Confirm source and destination quantities and two signed ledger rows with the same operation UUID. A retried identical operation UUID is safe; reusing it with a different payload is rejected. Reserved quantities cannot be set from the admin; checkout reservations arrive in Phase 6.
11. Products → Collections & categories: edit a collection and order its assigned products. Save collection product order. Ordinary product edits retain that ordering; navigation position independently orders collections/categories.
12. Unpublish. Product and public image URLs, including old slug aliases, must return 404. Edit its slug, save and publish again: the old API resource URL returns 301 with Location pointing directly to the current published canonical API URL. No redirect exposes a draft/archived target. Slugs remain permanently reserved; physical storefront product-page redirects belong to Phase 5 integration.
13. Try a duplicate SKU, negative selling price, corrupt PNG and missing stock reason. Expect field-specific messages and preserved form input. Open the same product in two tabs; a stale version must return conflict rather than overwriting another editor's work. Reconcile your input against a fresh copy; the app does not auto-merge it.
14. Sign in with a support account: write buttons are absent and direct staff catalogue writes return 403. Unauthenticated staff reads return 401; unsafe writes without CSRF return 419. The API capability must be checked independently of hidden controls.
15. Unpublish before changing images. Archive an image with confirmation; the original and DB record are retained but public/current catalogue access ends. Archive the product with confirmation: variants, stock ledger, media and IDs remain, and slugs/SKUs stay reserved. There is no hard-delete endpoint. Retention supports future order snapshots; checkout/order records do not exist yet in Phase 4.

Use genuine merchant content for your development catalogue; the test suite uses artificial isolated fixtures and never inserts these in the development DB. Do not copy conceptual PDF prices, measurements, claims or stock as verified business data.

## Automated checks

```powershell
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO"
cd backend
composer validate --strict
composer format:check
composer test
php artisan migrate:status
npm audit --prefix media --omit=dev
cd ../admin
npm run check
cd ../storefront
npm run check
cd ..
npm install --prefix .qa --no-save playwright
node shared/tests/catalogue.cjs
node shared/tests/staff-security.cjs
```

Browser suites use installed Microsoft Edge on Windows. On Linux/macOS install Chromium with `node .qa/node_modules/playwright/cli.js install chromium`. Each suite starts/stops only its own servers and uses separate private SQLite/storage/test accounts under ignored .qa: catalogue ports 8006/5181, staff regression 8005/5180. Those ports must be free. Redacted evidence is saved to docs/verification/phase4 and docs/verification/phase3 respectively. Ignored QA runs contain random test credentials; keep them private and remove when no longer needed. Do not run fixtures directly against your development DB; the helper enforces an explicitly isolated local SQLite path.

CI now installs Node 24 and the image worker before running Laravel tests. No hosted CI run or deployment was performed here. SQLite is the verified database; MySQL/PostgreSQL ledger-trigger branches have not been exercised. Real production load/concurrency and full assistive-technology testing remain release checks. Equivalent 200% reflow is verified in the Phase 4 browser suite; the Phase 2 native-zoom evidence remains historical.
