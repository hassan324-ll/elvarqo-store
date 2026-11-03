# Phase 4 completion report — 26 October 2026

Implemented in `C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO`. Read both supplied PDFs; Phase 04 plan pp.11–12 and designs A09–A14, A20, A31 plus S08–S10 define this work. No Phase 5 work has started.

## Delivered behavior

Protected catalogue lists/search/pagination, draft editor, normalized colour/size options and variants, global SKU/barcode uniqueness, GBP minor-unit prices, optional compare-at pricing/weight, low-stock thresholds, verified material/fit/care/measurement fields and SEO. Collection/category navigation order and per-collection product ordering are separate and retained on subsequent product edits.

Saved private preview, publication validation, explicit unpublish-before-edit, optimistic versions and clear preserved-input validation/conflict states. Public APIs expose only published current products and active variants; hidden collections and variant-linked images are filtered. Slug aliases return canonical 301 only while the target is published.

Private original uploads and three responsive WebP target sizes using pinned Sharp 0.35.5 in an independent backend/media package. Decode validates real raster format, bytes/pixels/dimensions and animation; SVG is excluded. Alt text, variant linking, primary order, reordering and archival are managed in the editor. Public derivatives check current publication on every request; original files never public. Duplicate actual-width descriptors are omitted when enlargement is prevented.

Real stock locations, per-location on-hand/reserved/available, reviewed signed adjustments with required reasons and atomic transfers. UUID idempotency prevents duplicated retries. Stock ledger stores actor/time/reference/reason and resulting balances; DB guards block mutation. Reserved is a read-only foundation for Phase 6.

Catalogue/media/stock writes require catalogue.manage in Laravel and reuse Phase 3 MFA/session/CSRF/origin controls. Read-only navigation follows server permissions. Audit events record significant writes. Product/variant/media archival retains rows, private originals, stock history and IDs; restrictive foreign keys protect retained references. There are no order records yet: future order items must store immutable content/price snapshots and retained IDs as documented in PHASE4-API.md.

## Acceptance evidence

| Phase 4 criterion | Evidence | Result |
| --- | --- | --- |
| Create colour/size variants, publish and retrieve through public API | Actual browser UI creates a two-colour/two-size polo, uploads media, previews/publishes and retrieves public JSON/images; Laravel full lifecycle test | Pass |
| Draft privacy, duplicate SKUs, invalid price/media errors | Public draft product/image 404, duplicate SKU/barcode/combinations, negative/fractional integer price, corrupt/SVG/oversize tests; browser preserved-input errors | Pass |
| Reasoned traceable stock adjustments | Required reason, actor/history, floor at reserved, retry fingerprint, signed transfer pairs and immutable DB history tests; browser receipt and transfer 20 → 12/8 | Pass |
| Historical integrity and published slug behavior | Archive retains variants/media/private files/ledger; foreign-key deletion-denial tests; reserved slugs/SKUs; public 301 and unpublish/alias 404 checks | Pass for Phase 4 foundation; historical order snapshot integration remains Phase 6 |

Final Laravel suite: **46 tests passed / 380 assertions** (20 new catalogue tests plus 26 foundation/security regressions). Pint and strict Composer validation pass; Composer audit reports no advisories. Admin and storefront formatting/lint/TypeScript/production builds pass. Dedicated image-worker npm audit reports zero vulnerabilities.

Real Edge/Chromium: **15 Phase 4 grouped checks plus 17 staff-security regression groups pass**. Tested editor/inventory at desktop/mobile, tablet inventory and equivalent 200% editor reflow without document overflow; no page errors. Screenshots reviewed for form readability, clipping and saved-preview layout. No new native browser-zoom or full screen-reader certification claim is made.

Testing found and fixed: duplicate fixture selection, responsive srcset duplicate widths, a saved-preview CSS class collision, an expired-session initial request/event race, live-data banner text and collection ordering reset during ordinary product edits. Early browser runs failed on the test's modal-close selector and the expired-session race; final complete runs pass. Evidence: verification/phase4 and verification/phase4-staff-regression.

## Configuration, migration and limits

Applied additive `2026_10_26_000001_create_catalogue_tables` to development SQLite, batch 3, after backing up the database. No saleable products/stock/locations or personal owner credentials seeded. Existing source and local secrets preserved; source replacements backed up privately in the chat work directory. No commit, push, hosted CI execution or production deployment.

Install the worker with `npm ci --prefix backend/media` from the root; new installs also follow README dependency/env/key/SQLite steps. PHP needs Node 24+ on PATH or MEDIA_NODE_BINARY configured. The recommended local backend command explicitly sets 8MB upload / 12MB POST limits; plain artisan serve inherits the installed 2MB default. Exact startup and step-by-step testing are in PHASE4-SETUP.md.

SQLite is verified. MySQL/PostgreSQL ledger trigger branches and real production load/concurrency have not been exercised. Hosting, real SMTP and protected APP_KEY/database/private-file backups remain deployment inputs from Phase 3. Merchant-supplied product claims, photographs, prices, measurements and real stock must be entered by authorized staff. Archival has no hard-delete or purge interface. General CSV tooling is Phase 10; reservations and order snapshots Phase 6. Full campaign media-library/DAM controls remain their later phases; this phase implements product media.

The next phase is **Phase 5: storefront shopping**—connect home/collections/search/product detail to the real catalogue, implement filters, selection, wishlist and shopping recovery states. The current public storefront remains the clearly labelled Phase 2 design preview. Phase 5 requires a new request to begin.
