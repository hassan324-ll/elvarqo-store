# Hostinger deployment status — 2026-11-02

## Public applications

- Storefront: https://elvarqo.uk — managed Next.js / Node 24 application. Home and Women collection HTTPS responses, canonical URLs and API connectivity verified. Search indexing remains disabled.
- Admin: https://admin.elvarqo.uk — static React/Vite dashboard. HTTPS, assets, SPA fallback, sign-in rendering, exact API origin and storefront links verified. Existing owner login and authenticator acceptance still require merchant verification.
- API: https://api.elvarqo.uk/api/v1/health — Laravel / PHP 8.5.12 / Hostinger MySQL-compatible database / GD. Health, navigation, products, imported category image, CORS, secure staff session and unauthenticated rejection verified.

The database preserves 75 imported catalogue groups and the existing staff account and supporting records. Public navigation currently contains six active collections and 60 categories. No demo products were inserted. The local SQLite database and local environment remain unchanged. Subsequent online admin edits are stored in the hosting database and do not automatically synchronize back to local SQLite. Do not re-import the original snapshot over the live database.

## Server layout and rollback

- Laravel release: /home/u688505577/elvarqo/releases/20261101-stage01/backend
- API public entry: /home/u688505577/domains/api.elvarqo.uk/public_html/index.php — boots the private release; secrets and vendor files are outside public_html.
- Admin assets: /home/u688505577/domains/admin.elvarqo.uk/public_html
- Private backups: /home/u688505577/elvarqo/backups
- Private maintenance log: /home/u688505577/elvarqo/logs/hosting-tick.log

Before replacing API/admin public files, their previous contents were archived privately. A further backup precedes the admin storefront-link correction. Old admin hashed assets were retained for cached pages and rollback. Restore public files from the matching backup only after inspecting the intended target. Database rollback requires a separate current database backup; do not assume an old SQLite snapshot includes subsequent online changes. Original encrypted fields require the existing APP_KEY; never regenerate it on the imported database.

## Build configuration

Storefront Hostinger source packages use next.config.mjs, build command `next build --webpack`, and `experimental: { cpus: 2 }`. Server glibc 2.28 cannot load the Next 16.4.2 native SWC requiring 2.29. Webpack with Next's WASM fallback built successfully on the actual host. The first 24-worker attempt hit EAGAIN; two workers passed. Do not revert to the original failing TypeScript-config/Turbopack ZIP. Dependency versions and lockfile were unchanged by this packaging adaptation.

Managed storefront variables:

```dotenv
NEXT_PUBLIC_API_URL=https://api.elvarqo.uk/api/v1
CATALOGUE_API_URL=https://api.elvarqo.uk/api/v1
COMMERCE_API_URL=https://api.elvarqo.uk/api/v1
NEXT_PUBLIC_SITE_URL=https://elvarqo.uk
STOREFRONT_ORIGIN=https://elvarqo.uk
STOREFRONT_INDEXING=false
```

Redeploy the managed Node application after variable changes. Merchant completed this and public canonical links were verified.

Admin build variables:

```dotenv
VITE_API_URL=https://api.elvarqo.uk/api/v1
VITE_STOREFRONT_URL=https://elvarqo.uk
```

The admin's three storefront links now use VITE_STOREFRONT_URL with a localhost default for local development. Source changes are limited to Workspace.tsx, auth/StaffAccess.tsx, lib/site.ts and the secret-free .env.example. Formatting, lint and isolated TypeScript/Vite build passed. Static package includes SPA rewrite rules and noindex headers/robots. No private environment file, source database or API secret is part of the static package.

## Remaining hPanel cron setup

SSH does not expose `crontab`; no scheduled entry was installed. One manual `hosting:tick` succeeded on the host, and a second invocation while holding its file lock skipped correctly. In the API website's hPanel Advanced → Cron Jobs, use a custom command on the every-minute schedule (`* * * * *`, UTC):

```sh
/opt/alt/php85/usr/bin/php /home/u688505577/elvarqo/releases/20261101-stage01/backend/artisan hosting:tick >> /home/u688505577/elvarqo/logs/hosting-tick.log 2>&1
```

Inspect existing cron jobs first. Use only one application scheduler, not schedule:run, schedule:work, queue:listen or a parallel permanent queue worker. This bounded command holds an overlap lock, processes at most 20 jobs, and uses a cooperative time budget. PHP 8.5 CLI supports pcntl. Verify hPanel saves the entry and that a new log entry appears; rotate private logs periodically.

## Merchant acceptance and launch gates

1. Sign in at https://admin.elvarqo.uk using the existing staff account and its authenticator. Keep password, MFA seed and recovery codes private.
2. Confirm existing collections/categories and images. Add a genuine draft product, upload images and verify its saved details. Publishing and stock changes should use the merchant's actual data.
3. Confirm the published product and category images appear on the storefront; review desktop/mobile navigation and stock display.
4. Configure and verify the minute cron, hosting backups and restoration procedure.
5. Complete approved live tax/delivery/returns rules, verified Wise receiving details and real payment reconciliation, external transactional email, shipping/postage and refund processes before enabling checkout.

Wise/Stripe remain disabled. Email remains private local/log copies. Existing courier/refund and policy sandbox limitations remain. Current public availability is for browsing and admin verification, not readiness to take paid orders. Search indexing remains disabled until launch acceptance is complete.


## 2026-11-04 — Bulk Import deployed to Hostinger

Deployed at the merchant's explicit request. The Laravel backend at api.elvarqo.uk and static React admin at admin.elvarqo.uk now include Bulk Import and the private supplier details panel. The managed Next.js storefront needed no source deployment for this feature. No products were imported or published, and no credentials or checkout settings were changed.

Before the brief API maintenance window, created private backend/admin/environment backups and a compressed database dump at `/home/u688505577/elvarqo/backups/20261104-bulk-import-v1`. Dump integrity checked successfully. Applied only `2026_11_04_000001_create_catalogue_import_tables.php`; all four new tables were empty after deployment. Existing product, collection, membership, variant, media, stock, staff and recovery-code table hashes and the existing environment-file hash matched their pre-deployment values. Do not expose the backup directory through a web root or download its secrets into source control.

Deployment package SHA-256: `2db21c62d9a5d361692eeced31cf34d04ff438ce2a628b8c5254b3f596a67b85`. Backend configuration cache rebuilt; API restored from maintenance; new admin assets published with atomic index replacement and previous hashed assets retained. The package contained no source image batch or private environment/database files. No GitHub push was performed.

Live checks PASS: valid HTTPS/HTTP 200 for storefront homepage and Women collection, API health/navigation/products, admin root and /products SPA fallback, and new JS/CSS assets. Admin JS contains Bulk Import and the production API URL. Admin noindex retained. API CORS accepts the exact admin origin. Anonymous importer history/template and supplier-details requests return HTTP 401. All six importer routes registered. Host hosting:check passed all eight checks including database and immutable evidence triggers. Actual web PHP 8.5.12 supports ZipArchive, GD and WebP; upload_max_filesize/post_max_size are 2048M, with stricter application importer limits retained. Temporary nonsecret capability probe was immediately removed. Existing database/category resolution and example validation passed without catalogue writes.

Authenticated visual acceptance remains manual because browser automation was disabled in this session. Sign in at https://admin.elvarqo.uk, refresh the page, open Products → Bulk Import, download the example or preview the prepared products.json and women-knitwear-images.zip. Review all provisional matches before explicitly importing drafts. The six-product package remains local under docs/imports/women-knitwear-first-batch. Existing publication requirements remain enforced; this deployment does not enable paid checkout, supplier fulfilment, external email or search indexing. Existing cron/payment/policy launch gates remain as documented above.


## 2026-11-04 — Easier product variants and photo colour review

Implemented and deployed the React admin Quick variant builder. It analyses existing/pending product photos locally in the browser using a bounded centre-region canvas sampler, presents editable/excludable colour suggestions and swatches, deduplicates names, accepts explicit supplier sizes and one GBP selling price, and generates only missing colour/size combinations with unique local SKUs. Confirmation is required after reviewing photo colours and supplier sizes. This is approximate colour sampling, not garment segmentation or verified supplier option extraction. Failed photo reads remain editable/unselected. Sources are unchanged and no external image service receives them.

Existing variants and prices are preserved when generating. A separate confirmed bulk-price action changes form values only and clears conflicting compare-at prices. Nine automated tests PASS for colour/centre/neutral handling, input and money validation, deduplication, SKU collisions, preservation and variant limits. Formatting, lint, TypeScript, production Vite build and git diff --check PASS. Offline decoding/sampling of the supplied 20 roll-neck photographs completed without modifying sources; exact supplier colour names still require review. Compact expandable variant rows and a searchable, bounded category list reduce long product forms. New variants retain unknown weight/composition/supplier IDs and create no invented stock.

Production admin built with the existing live API and storefront URLs. SHA256-verified static package afb65a5998fbd4f422303f149783a209cbb2040d9cbbc3370b1a42e229b18000 deployed with atomic HTML replacement. Private backup: /home/u688505577/elvarqo/backups/20261104-quick-variants-v1/admin-public.tar.gz. Served JS matches verified bytes; previous hashed assets and web settings retained. No backend files, migrations, database records, real variants, photos, credentials, publication or checkout settings were changed by deployment. No GitHub push. Browser authenticated/visual acceptance remains manual in the merchant's signed-in session. See QUICK-VARIANTS.md for exact workflow, limits and local commands.


## 2026-11-04 — Floating product form, icons and size/price controls

Add product and existing-product editing now open over the catalogue in an independently scrolling native modal dialog. Close/Escape share an imperative editor guard: saving/uploading prevents close; unsaved product, pending-photo and saved-media edits require discard confirmation. Focus returns to the trigger after close; background scrolling is locked while open. The catalogue retains its filters and refreshes on close. Existing nested preview/publication dialogs remain in use.

Added admin-local semantic icon labels for product/variant inputs without changing shared storefront components. GBP prices and compare-at prices now use number controls with a 0.01 step and visible browser arrows. Quick builder includes an empty-by-default size dropdown (clothing, UK and age examples), removable selected-size chips and a custom size field. Individual variant size fields offer the same suggestions through a datalist. Options are illustrative, not verified supplier inventory; no sizes, colours, prices or products are saved automatically. Existing validation and explicit supplier confirmation still apply.

Verification: eleven variant/size tests PASS; full formatting, lint, TypeScript and production build PASS; git diff --check PASS. The live-admin build uses production API/storefront URLs. Static package SHA256 277b4670409e4f78336a7cf8353bf0ae6f7c0481c278862219f99fe3809c397c transferred and checksum verified, then published with atomic index replacement. Served JavaScript matches package bytes. Previous assets and web rules retained. Private admin backup: /home/u688505577/elvarqo/backups/20261104-product-dialog-v1/admin-public.tar.gz. No database/backend changes, migrations, product writes, credential changes, publication or GitHub push. Authenticated visual/keyboard browser acceptance remains manual in the merchant session; browser automation was unavailable. Instructions updated in QUICK-VARIANTS.md.


## 2026-11-04 — Explicit regular and sale price controls

Added Regular price and optional Sale price to each variant and the quick builder. Admin decoding preserves existing discounts: compare_at_minor is displayed as regular price, price_minor as sale price; without compare-at, price_minor is regular. Save maps back to the existing API fields with exact penny parsing. A positive sale must be strictly below regular; clearing it restores regular and removes compare-at. Zero-priced incomplete drafts remain supported and publication validation is unchanged. New generated rows inherit regular/sale settings; existing combinations remain untouched. Bulk Apply prices explicitly confirms applying both prices or removing existing sales.

Fourteen variant/size/pricing tests PASS, including discount round-trip mapping, sale removal/restoration, invalid/equal/higher/zero sales and original variant protections. Formatting, lint, TypeScript, production build and git diff --check PASS. Existing storefront product-selection code already displays effective price and struck-through regular price; no storefront/backend migration or deployment was needed. Manual signed-in visual acceptance remains pending in the merchant browser.

Static admin deployed with production URLs, verified SHA256 be0d450b3abfaae974ad2ec5b53ef818c29d092c24cb9e511f2727c405efff82 and byte-identical served JS. Private backup: /home/u688505577/elvarqo/backups/20261104-sale-prices-v1/admin-public.tar.gz. Atomic index replacement, previous assets/settings retained. No actual product/price changes, database writes, credentials, publication, payment settings or GitHub push. Instructions updated in QUICK-VARIANTS.md.


## 2026-11-04 — Material, fit and care optional for publication

At the merchant's explicit request, removed only material, fit and care from the publication-required text fields. They remain nullable, bounded and editable. Admin labels now state optional. Required title/type/description/SEO, verified measurements, active category, positive active-variant prices, images, permissions and version checks remain enforced. No measurements, stock or unknown specifications were invented. Storefront hides empty material/care accordions and an empty fit paragraph; the verified measurements section remains available.

Added publication regression coverage for null/blank material/fit/care. Isolated SQLite/GD catalogue/importer suite: 50 tests, 499 assertions, one optional large-fixture skip (49 passing). The existing incomplete-publication and per-size measurement checks passed. Pint PASS; admin full check including 14 pricing/variant tests PASS; storefront lint/typecheck PASS; actual Hostinger webpack/WASM/two-worker standalone production build PASS; git diff --check PASS. Remote write-free validation confirms optional fields no longer produce errors while missing description, measurements and variants still do. No real publication or database writes were used for acceptance; visual signed-in acceptance remains manual.

Deployed API writer atomically and admin labels with private backups at /home/u688505577/elvarqo/backups/20261104-optional-details-v1. SHA256-verified package: 2a0d0f02d985b6b1a71d628d5e13a090a0bff51bf9acf9bcb10bbf64057e456f. No migration, environment, credential or business-record changes. Built storefront in private isolated deployment/source from the existing managed source after matching its component checksum to the reviewed baseline. Current Passenger release link now targets hbuilds/versions/elvarqo-optional-20261104-v1. Previous target is recorded privately in the backup. Preserved Passenger web rules, noindex/runtime URLs and prior static assets. Atomic link switch plus restart marker; homepage, API health and admin bundle checks passed. Updated the managed last-source component to preserve the change on a future rebuild. Future uploaded source archives must also include the current repository change; do not redeploy an old source ZIP. Existing checkout and launch gates remain unchanged. No GitHub push.


## 2026-11-04 — Unused manual variant rows no longer block saves

An empty new manual variant row could be left beside generated variants, or receive bulk prices without any colour/size/SKU, causing cryptic variants.0 required errors. The editor now ignores only completely untouched new rows during save, generation and bulk pricing; generated variants replace these unused placeholders. Saved rows and any partially entered values (including price, barcode, overrides or changed metadata) remain intact. Incomplete used rows trigger a clear numbered message, expand and focus the affected row, and explain that Generate variants must be clicked before Save draft. No backend validation was relaxed and no supplier options or identifiers are invented.

Sixteen helper/price/variant tests PASS, including untouched-row handling and preservation of saved/partially completed rows; formatting, lint, TypeScript, production build and git diff --check PASS. Static admin package SHA256 2c37afdad750dca3a992a9211b95d0416df2b32a296531921154b80888a24bbc checksum verified and deployed with atomic index replacement. Served JS matches the verified bytes. Private backup: /home/u688505577/elvarqo/backups/20261104-variant-empty-fix-v1/admin-public.tar.gz. No backend/database/product changes, real variant saves, publication, credentials or GitHub push. Signed-in visual acceptance remains manual.


## 2026-11-04 — Garment measurements optional by merchant request

Removed mandatory product-level and per-active-size measurement completeness checks from publication only. Empty or missing-size measurement rows no longer block publishing. Existing optional measurement input validation remains: supplied invalid negative/zero/out-of-range rows are rejected; no values are invented and saved measurements are preserved. Other description/SEO/category/variant/price/image requirements and staff permission/version/audit controls are unchanged. Admin marks Garment measurements optional and explains empty is allowed. Storefront filters empty measurement rows, hides Size guide/View garment measurements when none are supplied for the current colour, and hides an empty Fit & measurements section while retaining actual fit information.

Regression suite: 51 tests, 506 assertions, 50 passed and one optional large-fixture skip. Explicit empty/partial measurement publication and invalid supplied measurement cases pass. Admin full check including 16 variant/pricing tests PASS; storefront lint/typecheck PASS; Pint and git diff --check PASS; isolated actual Hostinger webpack/WASM production build PASS. Remote write-free validation confirms measurements/material/fit/care are optional while description and variants remain required. Authenticated visual acceptance remains manual.

SHA256 package 302816e55c32b6f13aa857c6dcaa187157e9473109a533672e7c3bf783fc5016 verified. API writer and admin labels deployed atomically; private backup at /home/u688505577/elvarqo/backups/20261104-optional-measurements-v1. Storefront staged from checksum-matched managed source and activated through hbuilds/versions/elvarqo-measurements-20261104-v1 with restart marker, retaining previous static assets and rollback link. Source component updated in managed last-source for future rebuilds. Homepage/API/admin bundle checks PASS and served admin JS matches package. No migrations, business-record changes, automatic publication, credential/payment changes or GitHub push. Future source uploads must use current repository files rather than old source ZIPs.


## 2026-11-04 — Default Always available ordering without stock quantities

Merchant explicitly confirmed customers should be allowed to order without recorded stock counts. Implemented catalogue-wide CATALOGUE_TRACK_STOCK=false by default; true restores count-based availability. Active published variants are in stock without ledger quantities; inactive, draft, archived and deleted choices remain blocked. Catalogue API returns distinct stock_tracked/is_in_stock flags alongside actual recorded available quantities. Updated discovery filters, product cards, product selection, saved choices, admin list/preview, inventory and overview to use status rather than invent counts.

Server pricing records its trusted stock mode in immutable quote/order snapshots; browser-supplied stock policy cannot bypass it, and a mode change invalidates a reviewed quote. New untracked orders skip reservations; payment settlement accepts absent reservations only for a nonempty snapshot of explicitly untracked lines. Legacy/tracked orders retain their reservation commit/release logic across mode changes. Fulfilment uses immutable line mode to skip only the committed-stock constraint while retaining payment, dispatch-location and ordered/allocation quantity checks. Existing inspected physical returns can still record real received units. Stock locations and adjustments are unnecessary for catalogue availability, not abolished from dispatch/returns operations. Provider readiness, order quantity limits, permissions, auditing, prices and publication rules remain unchanged. See AVAILABILITY.md for configuration and operating details.

Verification: full isolated GD/SQLite backend suite 207 tests / 1790 assertions, 206 passed and one optional large-image fixture skipped. New cases cover no-balance bag/checkout/paid settlement and expiry, public in-stock filtering, hidden/inactive blocking, mode/quote changes, existing reservations, Wise confirmation and shipment allocation without committed quantities. Existing tracked stock/returns/exchange/payment/permission suites passed. Admin full checks including 16 helper tests PASS; storefront lint/typecheck PASS; Pint and git diff --check PASS. Actual isolated Hostinger Next production build PASS after verifying all four source baselines. No live purchase was created for testing; authenticated visual acceptance remains manual.

SHA256-verified deployment package f0b6297d1ea515353cd295f9b8a88ddeec6a8a1c99f0555ce8c93915321a71fe. Private backups at /home/u688505577/elvarqo/backups/20261104-always-available-v1. Brief API maintenance, reviewed code replacement and config cache refresh; atomic admin index and storefront link activation at hbuilds/versions/elvarqo-available-20261104-v1. Private before/after hashes prove environment, payment configuration, products, variants, stock balances/movements, orders, reservations, snapshots, payments and staff unchanged. No migrations, fabricated stock, actual product publication, live orders/payments or GitHub push. Existing managed source updated for subsequent builds; source ZIPs must include current changes.

Public HTTPS acceptance PASS: existing published product page, in-stock discovery reports all returned variants untracked and available despite actual zero balances, live admin bundle contains Always available/Not counted, storefront/API/admin/assets, exact admin CORS and private importer/supplier HTTP 401 restrictions. Payment setup/checkout provider enablement remains unchanged; availability mode alone does not configure receiving details or enable live payments.


## 2026-11-04 — Corrected stale live storefront showing sold-out sizes

Merchant screenshot exposed a gap in prior deployment acceptance: API flags and deployed files were correct, but the running storefront still rendered the original November 2 code. Confirmed on fresh uncached requests: 60 is_in_stock fields in page data while five size buttons said sold out and empty size-guide controls remained visible. Hostinger processes still had the original managed release as their working directory, despite hbuilds/current pointing to the new availability release. HTTP 200 and API-only checks were insufficient; earlier reports of storefront activation did not establish the rendered UI was updated.

Tried Passenger restart markers and the installed passenger-config utility. Its default Ruby lacked rubygems; the correct installed Ruby launched the tool but no Passenger instance registry was visible to this shared-hosting shell. Marker updates did not change the rendered page. Backed up main public_html/.htaccess privately at /home/u688505577/elvarqo/deployments/20261104-always-available-v1/storefront-htaccess-before-explicit, then changed only PassengerAppRoot to the explicit verified hbuilds/versions/elvarqo-available-20261104-v1/nodejs release, preserving other directives and mode 0644. Gracefully terminated only owned next-server processes whose working directory exactly matched the obsolete elvarqo.uk release, after checking identity/path. Left the unrelated failed temporary-site process alone. Hostinger restarted the storefront with the new release working directory; rendered sold-out labels dropped from five to zero immediately.

Read-only public verification confirms actual size choices have no sold-out labels or unavailable-option class while API reports is_in_stock=true, stock_tracked=false and actual available quantities still zero. Empty optional material/care/measurement controls are now hidden as implemented. Checks include main/www addresses, uncached query requests and loading their initial JavaScript assets. Added verify-rendered-stock.py and strengthened verify-always-available.py to assert rendered availability, rather than only HTTP 200. No source business logic, products, counts, orders, credentials or payment settings changed during this correction. Signed-in/client interaction remains manual; server-rendered UI and public assets are verified.

Future storefront activations must verify the running Node process working directory and rendered UI. A symlink switch/restart marker alone did not activate a new process on this host. Use an explicit intended PassengerAppRoot, preserve existing directives and private backup, ensure appropriate web permissions, and if needed gracefully stop only positively identified owned processes for the obsolete app. Never terminate unrelated Node applications. Hostinger redeployments may regenerate configuration; recheck the actual served release afterwards.


## 2026-11-04 — Refreshed live product cards

Redesigned the shared ProductGrid cards across homepage/shop/collection/saved views with consistent cover-cropped 4:5 image frames, restrained white card surfaces, aligned content hierarchy, product-type labels, actual price/sale display, four compact named-colour swatch links plus remaining-count links, an In stock/size-count footer, top-right heart save controls and labelled Quick view. Mobile layouts show two columns (one below 360px); hover/focus reveals Quick view on pointer devices while touch devices retain visible actions. Existing original media, order, product data, quantities, wishlist storage, quick-selection and Always available behaviour are preserved. Unknown colour names use a neutral patterned marker; named swatches are decorative approximations, not new verified supplier shades. No fake ratings, reviews, discount percentages or stock counts added.

Added a pure card-price helper: uniform prices omit From; varying matched-variant prices use their true minimum; regular-price strike-through/sale badges belong only to the lowest displayed variant when its compare-at is higher. SavePiece accepts an optional icon-only presentation with accessible names and unchanged default text UI elsewhere. Existing links target real product/variant IDs and preserve selected-variant handoffs.

Five pricing/swatch tests PASS; formatting, lint, TypeScript and git diff --check PASS; actual isolated Hostinger-compatible production build PASS. Node test runner prints a non-failing module-format detection warning for the standalone TypeScript helper; no application module-format setting was changed. Tests are available via npm run test:cards and included in the storefront check command. Public rendered acceptance on main/www verified six cards, one labelled heart and Quick view per card, at most four actual variant swatches, product titles and loaded new CSS. Product-page availability/hidden-empty-detail regression checks also pass. Visual and interactive browser review remains manual because computer use was unavailable for this session; no screenshot acceptance is claimed.

Storefront-only source package SHA256 13a6d2300a64910e74981f31e479eb906f94b81766e0480178bc8267d8ff78db. Verified remote source baselines, built privately, backed up previous source/main .htaccess/link at /home/u688505577/elvarqo/backups/20261104-product-cards-v1, activated hbuilds/versions/elvarqo-cards-20261104-v1 with explicit PassengerAppRoot in main and managed release web rules. Gracefully restarted only owned processes whose working directory exactly matched the previous storefront release, avoiding the prior stale-process issue. Previous hashed assets retained and managed last-source updated. No backend, database, original image, product, price, stock, payment, credential or publication changes; no GitHub push. See PRODUCT-CARDS.md for design behaviour and verification commands.


## 2026-11-04 — Shorter marketplace-style product cards

At merchant request, reduced listing card height and information density: square instead of 4:5 image frames, one-line title with ellipsis/full accessible link text, effective price plus genuine regular-price strike-through, and existing heart save button. Removed listing Quick view/modal, category text, colour swatches/counts, availability footer and size counts; full detail and buying controls remain on the linked individual product page. Sold out image badge remains only for actually unavailable products. Existing matched/selected variant IDs, media selection, wishlist state, prices and Always available logic are preserved. CSS specificity ensures the square frame and compact typography override earlier expanded-card/mobile styles. No original images or product data changed.

Five card pricing/swatch helper tests PASS; formatting, lint, TypeScript, git diff --check and isolated actual Hostinger production build PASS. Existing harmless Node module-format warning remains in the standalone helper test command. Public rendered checks on main/www confirm six compact cards each with one heart/title/price, exactly two matching product links, no expanded detail/quick-view elements, and served square/ellipsis CSS. Linked detail pages still have size selection and Add to bag; available choices remain enabled. Visual interactive review is manual; no screenshot acceptance claimed.

Storefront-only package SHA256 b4d98f9ee34bc68cbd95057f2f5c7aaabdc4dda7c5b8dc9722961fcc41d9da2d; source baselines matched the prior card release. Private backup at /home/u688505577/elvarqo/backups/20261104-compact-cards-v1. Activated hbuilds/versions/elvarqo-compact-20261104-v1 with explicit Passenger root and graceful restart of positively identified owned processes from the prior release; retained old assets/rollback/config and updated managed last-source. No API/backend, database, prices, stock, publication, credentials or payment changes; no GitHub push. PRODUCT-CARDS.md now identifies the compact layout as current and the expanded design as superseded.


## 4 November 2026 — Production indexing correction

Supersedes earlier pre-launch STOREFRONT_INDEXING=false instructions for the live elvarqo.uk storefront only. Deployed release elvarqo-indexing-20261104-v2 with production indexing true, exact-host/private-route safeguards, and generated robots.txt. Live apex/www homepage, public catalogue and published product checks passed; private/admin/filtered pages retained noindex. Normal and cache-busting responses were DYNAMIC/no-store with no stale noindex. Hostinger required a production startup wrapper to override its inherited stale false value. Real Search Console live retest remains for the owner; spoofed Googlebot from this workstation returns Hostinger 403. See PRODUCTION-INDEXING.md and PRODUCTION-INDEXING-LIVE-RESULTS.json for exact deployment files, settings and evidence. No migrations, product publication or admin/backend changes in this deployment.


## 5 November 2026 — Dynamic sitemap deployed

Read-only Laravel sitemap feed and concrete Next XML route deployed (release elvarqo-sitemap-20261105-v1). Live GET/HEAD and www return 200/application/xml; 11 canonical indexable URLs and actual product lastmod dates match current catalogue data. robots.txt advertises the sitemap. Useful-category metadata fixed; drafts/private/empty/hidden pages excluded. No database writes or migrations. 5 sitemap, 4 indexing, 5 card tests and 11 Laravel tests/104 assertions passed, plus production build/lint/types/format. Optional isolated loopback HTTP test was blocked by a refused listener and is not counted as passing. See SITEMAP.md and SITEMAP-LIVE-RESULTS.json for exact deployment scripts, files, settings, tests and live evidence.


## 5 November 2026 — Product structured data deployed

Server-rendered reusable ProductGroup/Product/Offer and matching BreadcrumbList deployed across published products. Live HTTP audit matched all 216 variants across six products, first/last direct selections and actual public images. Google live Rich Results Test reports saved separately in GOOGLE-PRODUCT-RICH-RESULTS.json. Narrow public-image robots/header exception added; private API/draft/staff protections retained. HCDN stale robots cleared through hPanel and ordinary URL verified. 6 schema unit tests, existing 14 storefront tests and 12 Laravel tests/111 assertions plus build/lint/types/format passed. Unknown global identifiers/brands/shipping/returns/ratings remain omitted; Google optional warnings do not justify invented values. See PRODUCT-STRUCTURED-DATA.md for exact deployment files, configuration, image caveat and evidence. No business data changed.
