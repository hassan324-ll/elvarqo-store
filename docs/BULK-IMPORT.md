# Bulk product import

Implemented locally on 4 November 2026. No deployment, GitHub push, real-store import or publication was performed for this feature. The existing local SQLite database and environment were preserved. Hosted products/accounts were not accessed or changed.

## Install in the existing project

Use Node.js 24, PHP 8.4.1 or newer, Composer, PDO for your configured database, fileinfo, mbstring and the **zip extension**. For the shared-host image adapter, enable GD with JPEG/PNG/WebP. The existing Sharp adapter also remains supported. No new npm or Composer package is required.

Back up the configured database and private media before applying migrations. Preserve the existing `.env` and `APP_KEY`. Do not replace a populated hosted database with a local SQLite snapshot.

In PowerShell, from the existing project:

```powershell
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend"
composer install
php -r "echo extension_loaded('zip') ? 'ZIP enabled' : 'Enable PHP zip';"
php artisan migrate
php artisan config:clear
```

The new additive migration is `2026_11_04_000001_create_catalogue_import_tables.php`. It creates four tables only; existing products, groups, accounts and publication checks are retained. No migration was applied to the merchant's real local or hosted database during implementation. Run `migrate` yourself before opening Bulk Import in your normal local app. If `php` uses Herd without GD, retain the existing Sharp configuration and install its dependencies:

```powershell
npm ci --prefix media
```

For GD, set `MEDIA_IMAGE_DRIVER=gd` in the private backend environment and use a PHP binary with GD/WebP. For Sharp, retain `MEDIA_IMAGE_DRIVER=sharp` and a working `MEDIA_NODE_BINARY`. This is an existing project: do not run `key:generate` or overwrite its environment file.

Set PHP's active web configuration to at least:

```ini
upload_max_filesize=32M
post_max_size=40M
memory_limit=512M
```

Restart the local API after changing PHP configuration. The first image ZIP is about 27.6 MiB and fits these limits. Confirm the actual web PHP configuration, since CLI and web runtimes can use different INI files. On Hostinger PHP must remain at least 8.4.1 and GD/zip must be enabled; deploying there is a separate future action.

Install and start the admin in another terminal:

```powershell
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\admin"
npm ci
npm run dev
```

Run the existing backend and storefront in separate terminals:

```powershell
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend"
php artisan serve --host=localhost --port=8000
```

```powershell
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\storefront"
npm ci
npm run dev
```

Keep the admin's local `VITE_API_URL=http://localhost:8000/api/v1` and the backend's existing local staff origin `http://localhost:5173`. These private files were not changed by the feature. Local admin sign-in uses the local account/password, which may differ from a later password reset on the hosted database.

## Import workflow

1. Sign in at http://localhost:5173 with a staff account authorised for `catalogue.manage` (owner/operations by the existing roles).
2. Open **Products → Bulk Import**. The current catalogue filter does not silently assign a category: assignments come from the JSON and are displayed for review.
3. Download the example template or choose your own product JSON. Replace all sample content and the deliberately synthetic example AliExpress ID before use.
4. Optionally choose an image ZIP. If JSON references images, those exact filenames must be present in the ZIP. An image ZIP is unnecessary for records with `images: []`.
5. Click **Preview import**. Review titles, resolved group IDs and parentage, supplier IDs/URLs, complete/pending variant counts, thumbnails, file matches, warnings and errors. Preview creates a private staging batch and report, not catalogue products.
6. Check the explicit confirmation of category assignments and provisional image-to-supplier matches, then click **Import eligible products as drafts**.
7. Review the row statuses: `ready`, `invalid`, `duplicate`, `failed`, `imported`. Only ready/failed rows are eligible. Each product commits separately; one failure does not roll back other products. Each failed product rolls back its product, supplier, variants, media and audit writes, and cleans up media created by that attempt.
8. For a lost connection, reopen Bulk Import and choose the recent batch, or refresh its report. Completed rows are idempotent. Use **Retry this product** after correcting an image processor/storage outage. A moved/renamed collection or category also requires a fresh preview. A changed JSON or ZIP needs a new preview; choosing new files clears the previous preview confirmation. Invalid/duplicate rows cannot be forced through.
9. Download the detailed report. Open each created draft from its report link to review/edit normal product data. Supplier information is a separate read-only manager panel, not part of the customer description.

Previews expire after 48 hours. The latest 25 batches for the signed-in staff account are listed; a staff account cannot read another account's batch. There is an application limit of ten active previews per staff account. Already imported products and supplier identities remain after expiry. An expired preview cannot be executed; upload a new preview and duplicate IDs remain protected.

Manual staging cleanup, with no product deletion:

```powershell
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend"
php artisan catalogue:prune-imports
```

This removes expired private preview directories, including source JSON and staging images, while retaining database reports and supplier duplicate reservations. No cleanup cron was installed. If a process terminates abnormally before cleanup, private orphaned media may need operator review; never blindly delete media belonging to a product.

## JSON contract

The downloadable example is `backend/resources/imports/products.example.json`. Version 1 accepts a root object with `schema_version: 1` and a `products` list (one to fifty products). JSON is limited to 1 MiB and depth 32. Unknown product/supplier/image/variant keys are rejected rather than silently changing publication or inventory state.

Each product accepts:

| Field | Meaning / limits |
|---|---|
| `title`, `slug` | Title required, max 160 characters; slug max 180, lower-case hyphenated words. Omit/null slug to derive it from the title. Slugs must be available. |
| `product_type`, `description` | Existing catalogue fields, max 120 / 12,000 characters. Plain text. |
| `material`, `fit`, `care` | Nullable, max 4,000 characters each. Empty unknown values remain empty. |
| `measurements` | Existing verified rows: size, chest_cm, length_cm, shoulder_cm; values 1–400. Use `[]` when unknown. Do not invent missing measurements. |
| `seo_title`, `seo_description` | Nullable, max 70 / 160 characters. |
| `groups` | Select by `{collection_slug, category_slug}`. The category is optional, but if provided it must be a non-deleted child of that top-level collection. Resolved IDs are shown in preview. Hidden groups are labelled. |
| `collection_ids` | Optional explicit existing group IDs, validated in the destination database. Prefer slugs for portable files. |
| `variants` | Up to 100 rows; use `[]` if sizes/options/prices are unknown. Fields follow the catalogue writer: SKU, barcode, colour, size, price_minor, compare_at_minor, currency, weight_grams, low_stock_threshold, active, measurements, fit, care. Only GBP selling prices accepted. Amounts are integer minor units (2499 means £24.99); do not supply guessed prices. |
| `images` | Up to 30 `{file, alt}` entries per product. Exact case-sensitive ASCII relative ZIP path; alt required, max 240 characters. All imported images are shared product images; no invented colour or variant assignment. |
| `supplier` | `name: "AliExpress"`, quoted numeric string `product_id`, and matching canonical `https://www.aliexpress.com/item/ID.html`. Supplier costs/dates are optional. URLs are identifiers only: the importer never fetches them. |

Supplier product fields: `cost_minor`, `currency`, `shipping_cost_minor`, `checked_at`. Currency is a three-letter uppercase code and is required when an amount is given. `checked_at` is a supplied `YYYY-MM-DD` date, not a future date. Unknowns should be null/omitted, including the date; the importer does not invent a verification date.

Variant supplier fields: `supplier_variant_id`, `supplier_cost_minor`, `supplier_currency`, `supplier_shipping_cost_minor`, `supplier_checked_at`. Never infer a supplier variant ID from a photo, colour or size.

A local variant is created only when the supplied SKU, colour, size and selling price are complete. The importer uses the existing writer and its SKU/combination/price validation. Unless explicitly supplied, its activation flag is false and its operational low-stock alert threshold is zero; these settings are not stock counts. No inventory is created. Known supplier IDs are linked to the corresponding actual local variant. If a variant is incomplete, all supplied catalogue values are retained in the private supplier variant row as `pending_catalogue`; the local variant link stays null. Its missing SKU/options/price are not filled in. Preview makes this distinction explicit. Complete pending variants and reconcile supplier mappings before publication; the importer does not silently overwrite a previously imported product to accomplish that.

## Duplicate protection, security and persistence

- `catalogue_supplier_products`: supplier identity, canonical URL, optional costs/currency/date and local product link. A database unique constraint on supplier + external ID protects against simultaneous/repeated imports.
- `catalogue_supplier_variants`: local variant link, known external variant ID, optional costs/currency/date and incomplete values. Unique external variant identity per supplier product and unique local variant link.
- `catalogue_import_batches` / `catalogue_import_items`: staged input hashes, actor, expiry, immutable-to-client payload, preview, per-row status, product ID, attempts and error report. Client input cannot edit a stored preview at execution time.

Supplier records are deliberately separate from catalogue content. Public product/discovery/order content does not gain supplier joins or fields. Manager-only endpoints require the existing staff session, MFA session state, staff origin, CSRF checks for mutations, catalogue view and catalogue manage permissions. Support/read-only staff cannot access the template, preview, reports, thumbnails or supplier details. Reports/images use private no-store responses. Imported originals and media remain in the existing private media root; processed derivative filenames/UUID paths retain the existing media convention. Descriptive image source filenames and alt text are retained in the import payload/report and source manifest.

Deleting an otherwise deletable imported product uses the existing deletion rules. Nullable foreign keys detach supplier/product and supplier/local-variant links, preserving the AliExpress identity reservation. A subsequent import of the same external ID is still a duplicate, including after deletion/archive. Re-importing never updates title, variants, prices or images silently.

Images are never downloaded from JSON URLs. ZIP validation rejects absolute paths, drive prefixes, backslashes, traversal, conflicting file/directory paths, duplicate case-insensitive names, Windows device paths, links/special files, encryption and unsupported entries. It pre-checks at most 200 ZIP entries, 32 MiB compressed upload, 128 MiB expanded total, 8 MiB per file and an expansion ratio limit of 1000. It streams bounded bytes, verifies size/CRC, checks actual MIME/content and dimensions (200px per side minimum; 24 megapixels maximum). It does not use unrestricted ZIP extraction. The existing image processor performs full still-image decoding and derivative generation on import; a later decode failure is reported and the entire product attempt rolls back. Existing source files are copied for processing, so retry input is retained. Original bytes are checked by SHA-256 and preserved; staged tampering fails execution.

## AVIF conversion

AVIF ZIP entries are rejected with instructions because the existing server product pipeline supports still JPEG, PNG and WebP. No unsupported format is relabelled as WebP. The supplied first batch is already converted, so use its WebP ZIP.

For future AVIF archives, extract your trusted source images into a separate local directory. Keep that directory unchanged, install the existing Sharp dependency, then run the included helper with a **new** output directory:

```powershell
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend"
npm ci --prefix media
node media/convert-import-avif.cjs "C:\incoming\original-images" "C:\incoming\converted-webp"
```

It reads at most 200 AVIFs, rejects links/animation/oversized input, and writes lossless WebP copies plus a conversion report. It does not overwrite an existing output directory, modify sources or generate replacement garment details. Check the report, choose descriptive filenames and update every JSON `images[].file`. ZIP only the converted image files, excluding `conversion-report.json`. Then preview. If your local Sharp build cannot decode a supplied AVIF, export a copy through an image editor with AVIF support as still WebP; retain the original and review the resulting image.

## First batch

Directory: `docs/imports/women-knitwear-first-batch`.

- `products.json`: six incomplete draft records with distinct copy and SEO within existing limits.
- `women-knitwear-images.zip`: 67 matched lossless WebP copies.
- `PREVIEW.html`: all 73 original-image conversions, the provisional archive/URL matches and visible exclusions. Keep its `webp-images` sibling directory when opening it.
- `source-archives`: byte-for-byte copies of all six supplied ZIPs. The original Downloads ZIPs remain unchanged.
- `source-manifest.json`: archive/image source hashes, converted hashes, dimensions, mapping and exclusions.
- `resolved-groups.json`: actual local database lookup Women ID 9 / Knitwear ID 16. These IDs were resolved from the database, not guessed; JSON preview independently resolves slugs in the destination database. Tests used different IDs (42/97) to verify this.

The six URL/image pairings remain **provisional** and were not independently authenticated against supplier listing contents. Six visibly V-neck photographs in the proposed crew-neck archive were excluded from that product, while remaining in the source review. The merchant's provisional roll-neck name needs supplier confirmation; the copy describes the raised neckline visible in the photographs. No cashmere, wool, UK manufacture, reviews or delivery claims were added. No colour or size variants were created from photographs.

Before publication: verify each supplier/image match and image rights; actual colour/size availability and supplier variant IDs; fibre composition; fit/care; actual measurements for each offered size; real GBP selling prices/SKUs; stock/availability handling; supplier costs/currency/shipping/date where available; and the merchant's live payment/delivery/returns/tax rules. Existing catalogue publication requirements remain enforced. This feature imports catalogue data only and does not place or fulfil AliExpress or DSers orders.

## API endpoints

All paths are under `/api/v1/staff/catalogue`, protected by `catalogue.manage` in addition to the existing staff middleware:

```text
GET  imports/template                       JSON download
GET  imports                                Latest own batches
POST imports/preview                        Multipart: products JSON + optional images ZIP
GET  imports/{batch}                         Own batch report
GET  imports/{batch}/items/{item}/images/{i}  Private staged thumbnail
POST imports/{batch}/items/{item}            confirm_matches=1; one atomic draft attempt
GET  products/{id}/supplier                  Private supplier details
```

Normal catalogue endpoints do not change their response contract. Shared catalogue validation and shared media writing were extracted so both manual entry and import use the same validation, optimistic version checks, image pipeline and audit conventions.

## Verification and manual acceptance

See `BULK-IMPORT-TEST-RESULTS.md` for completed checks and their limits. No network browsing/scraping, real payment or provider order action is part of the tests.

Focused tests with a PHP binary supporting GD and zip (tests create isolated databases):

```powershell
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend"
php vendor/bin/phpunit --filter BulkImportTest
```

To include all six prepared records/67 actual images in the isolated test database:

```powershell
$env:BULK_IMPORT_BATCH_DIR = (Resolve-Path "..\docs\imports\women-knitwear-first-batch").Path
$env:MEDIA_IMAGE_DRIVER = 'gd'
php vendor/bin/phpunit --filter BulkImportTest
Remove-Item Env:BULK_IMPORT_BATCH_DIR
Remove-Item Env:MEDIA_IMAGE_DRIVER
```

The full backend regression command is `php vendor/bin/phpunit`; the admin command is `npm run check`. The guarded MySQL profile requires a disposable `elvarqo_test_*` schema and explicit `MYSQL_TEST_*` environment variables as documented in MYSQL-MIGRATION.md. Never aim that profile at the business database. Both CI workflows request zip/GD; remote CI has not been run for these unpushed changes.

Manual browser acceptance after local migration: verify manager-only Bulk Import; download template; preview a missing image and observe the row error; preview the six-product package and review thumbnails; confirm category/supplier matches; import only when you choose; refresh/report counts; retry an already completed row and confirm no duplicate; review a draft/supplier panel; confirm unfilled publication requirements still block publication and customer pages show no drafts. Repeat on a narrow viewport. Automated browser walkthrough was unavailable because local browser use is disabled in the app settings. API/security tests and the admin build are verified; authenticated visual browser acceptance remains for the operator.


## Hostinger deployment — 4 November 2026

Bulk Import is deployed to https://admin.elvarqo.uk with its Laravel API and four additive tables. No products were imported or published. Sign in, refresh, then open Products → Bulk Import. The local six-product JSON/image ZIP can be previewed there before you explicitly confirm an import. See HOSTINGER-DEPLOYMENT.md for backup location, preservation evidence and live checks. Authenticated browser acceptance remains manual; checkout launch gates are unchanged.


Publication rule update (4 November 2026): material, fit and care are now optional by merchant request. Unknown values can stay blank, and empty sections are hidden from product pages. Other publication checks, including verified measurements, remain in force. No existing drafts were published by this change.


Measurement rule update (4 November 2026): garment measurements are optional for publication. Leave the section empty when unknown; if entering rows or overrides, existing numeric/range validation still applies. Empty size-guide controls are hidden for the selected colour when no measurements are supplied. Existing verified measurements are retained. Titles, descriptions, SEO, active category/variants, positive selling prices and images remain required. No products are automatically published.
