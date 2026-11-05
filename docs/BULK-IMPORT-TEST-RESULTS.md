# Bulk importer verification — 4 November 2026

## Completed checks

| Check | Result |
|---|---|
| Full backend regression suite, PHP 8.4.27 + GD, isolated SQLite | **PASS: 192 tests / 1,682 assertions**. Includes the initial 15 importer cases and existing catalogue, commerce, security, inventory and after-sales coverage. |
| Expanded importer suite with actual six-product package, SQLite | **PASS: 18 tests / 171 assertions**. All six records and 67 WebP images imported into an isolated test database, all draft, zero variants/stock fabricated; source package unchanged. |
| Expanded importer suite with actual six-product package, MariaDB 10.4.32 | **PASS: 18 tests / 171 assertions**, same real package acceptance on the MySQL-compatible engine. |
| Final focused security suite, SQLite | **PASS: 20 tests / 146 assertions**, one optional six-product fixture test skipped because BULK_IMPORT_BATCH_DIR was not set in this final quick run. The full fixture had already passed in the separate run above. |
| Final focused security suite, MariaDB | **PASS: 20 tests / 146 assertions**, same one optional fixture skip; database-level identity uniqueness, quota/anonymous access and stronger public product privacy assertions passed. |
| New migration rollback/reapply on guarded disposable MariaDB schema | **PASS**. Four new tables removed/recreated; existing catalogue tables retained. |
| Admin npm run check | **PASS**: formatting, lint, TypeScript and production build. |
| PHP Pint --test --dirty | **PASS**. |
| Offline Sharp AVIF converter | **PASS** on a supplied original: lossless WebP written, original bytes unchanged, dimensions retained at 800×800. |
| Original source preservation | **PASS**: existing backend .env and local SQLite file SHA-256 exactly unchanged; each of six Downloads ZIPs matches its original copied source archive. |

The final suite contains 21 tests. The separately enabled image-package test is intentionally skipped during quick/CI runs without its large fixture. Full regression and focused results above are separate runs; do not add their assertion counts together as unique coverage. The final additional tests strengthen existing security coverage rather than change product behaviour. Oracle MySQL and remote CI were not executed in this task.

## Behaviour and security exercised

- Preview does not create catalogue products; collection/category IDs resolve from actual parentage even when fixture IDs differ from the merchant database.
- Minimal drafts preserve null/empty fields; no invented options, prices or stock. Existing publish checks reject incomplete imports and draft product URLs remain private.
- Complete known local variants link to known supplier IDs/cost data; incomplete supplied variant values stay private with null local links.
- Confirmation required; repeated completed-row execution returns its original product; duplicates within a file and across batches do not overwrite. Database unique constraints enforce supplier identity, including race protection. Deleting a product retains supplier identity protection.
- Image matches, missing files, disguised image types, altered staged bytes and strict filename references. Product image pipeline preserves originals and produces private WebP derivatives.
- Forced failure on a later image rolls back the product, supplier, media and earlier image records/files. Retry succeeds without duplicate products. One invalid/failed row does not block another valid row.
- ZIP traversal, absolute/drive/backslash paths, special-file links, conflicting file/directory ancestors, case duplicates, unsupported AVIF/PHP entries, entry/byte/expansion/ratio limits.
- JSON parsing/count limits, unknown fields, wrong types, SEO limits, supplier URL/ID match, cost/currency rules and hierarchy validation; changed group/slug state revalidated at execution.
- Reviewed group title/slug/kind/parent are revalidated under ordered row locks; a moved/renamed category requires a new preview.
- Existing staff origin/CSRF/session/permission checks; cross-actor batch isolation, read-only/support denial, anonymous thumbnail denial, preview expiry and active-preview quota.
- Supplier data absent from a genuinely returned published fixture product response, including supplier variant IDs; private supplier endpoint remains manager-only.
- Expired staging cleanup removes private files while retaining reports.

## Corrections during verification

The first importer test run failed because the new synthetic staff fixture did not refresh database defaults for active/auth-version state. The fixture was corrected; authentication was not relaxed. Extracting shared catalogue validation also required retaining the existing save method's seen-variant list; this was corrected before the passing full regression run.

The old local MariaDB rehearsal instance initially used explicit_defaults_for_timestamp=OFF, causing an existing staff recovery table migration to fail before the new migration. That variable is read-only at runtime on this release. The disposable instance was stopped and restarted with --explicit-defaults-for-timestamp=1; no installed server configuration or production server was changed. All MariaDB importer checks then passed. The instance was stopped after testing.

## Limits and manual checks

Local browser automation was unavailable: the browser tool reported that local browser use is disabled in Work settings. No browser-control bypass was attempted. Authenticated API acceptance and admin production compilation are verified; desktop/mobile visual interaction and a human review of provisional photo-to-listing matches remain manual checks. A temporary synthetic QA API/admin was prepared but stopped when browser testing was unavailable; merchant development servers were left alone.

No real local/storefront/Hostinger product was created or published by these tests. No deployment, GitHub push, external email, supplier scrape, real payment or supplier fulfilment was performed. The prepared six products remain files for the merchant to preview and choose to import after local migration. Source ZIPs and descriptive conversions are included separately from runtime storage. Publication still needs the verified facts listed in BULK-IMPORT.md and the batch README.
