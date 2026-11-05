# Final review package and deployment / rollback procedure

**NOT DEPLOYED.** Product copy and alt proposals are separate review documents, not part of any database write. No prices, publication, policies or supplier mappings change. Automatic approval review initially rejected private transfer. The user then explicitly approved private upload/Linux build only; it completed. The actual Linux standalone archive is now included. No deployment or catalogue changes occurred; host runtime acceptance remains pending.

## Concrete contents

- `storefront-hostinger-review-source.zip`: complete clean frontend build inputs, including synchronised bundled UI, public campaign assets, sitemap, robots, JSON-LD and tests. No `.env`, database, private product source images, node_modules or `.next`. Production API endpoints are set by build script, never credentials.
- `backend-parent-field-source.tar.gz`: only `backend/app/Catalogue/CatalogueData.php`, adding category parent_id to the public response. No migrations/configuration/seeding/imports. Production media/private/API guards unchanged.
- `customer-seo-linux-standalone.tar.gz`: actual successful private Hostinger Linux build, SHA256-verified after download. Includes standalone runtime/static/public/startup, no .env files.
- `build-only.sh`: checksum-guarded build record/reproduction script. It has already run in its named private directory; do not rerun over that directory. No activation or site changes. Windows also tested the same MJS/webpack/two-worker/standalone configuration with WASM fallback. Windows runtime files are NOT included.
- `SHA256SUMS.txt`, source manifest and local validation results/log.
- Full before/after copy, 67 image alt comparisons, six evidence checklists, ready/blocked gates in `docs/customer-seo-final-review/`.

## Build verification completed

Clean local npm ci and production build PASS with Node24 / Next16.4.2, next.config.mjs, webpack, standalone and two workers. Forced WASM log includes “using WASM bindings”; Next also emits a supported-platform experimental warning on Windows. This confirms a WASM build path locally, **not Linux/Hostinger acceptance**. All routes including XML sitemap/proxy retained; no TypeScript error. Local standalone route checks PASS for six products/216 offers/11 sitemap URLs, canonical/index/noindex rules, four-item breadcrumbs and concise customer notices. No business records changed. 22 storefront tests, format, lint/types pass. Existing backend57 tests/606 assertions passed in previous audit; backend source unchanged since that run. No new live Google validation or live fix claimed.

## Completed private Linux build — not activation

There is no usable local WSL Linux distribution or Docker installation. With the user's explicit private-build approval, the checksum-guarded script completed on Hostinger Node24.22.1 in a new private directory. The command below is the **executed build record**, not a pending activation command:

```sh
sh build-only.sh /ABSOLUTE/PRIVATE/PATH/storefront-hostinger-review-source.zip
```

This creates `/home/u688505577/elvarqo/deployments/20261105-customer-seo-final-review-v2/`, installs lockfile dependencies, builds and creates `customer-seo-linux-standalone.tar.gz` plus SHA256. It does not copy into a live document root, switch symlinks, alter Passenger, restart anything, modify `.env`, run PHP or access a database. Do not run it a second time over an existing directory. Failed build log remains private. Native SWC fallback messages on this older host are expected only if WASM build ultimately succeeds. Do not remove type validation or silently downgrade dependencies to pass.

Archive validation after download confirmed the artifact has `nodejs/server.js`, `nodejs/production-indexing.cjs`, `nodejs/public`, `nodejs/.next/static`, runtime dependencies and zero `.env*` files. A private loopback-only smoke attempt logged Ready but GET connections returned ECONNREFUSED on127.0.0.1:41837 using both fetch and direct HTTP. No hosting settings/public exposure changed to work around it. Record this as incomplete host runtime acceptance; the local Windows standalone runtime passed the six-product checks. Obtain a **separate deployment approval** before activation and perform the live checklist immediately afterwards, with rollback available. The private build permission alone is not deployment permission.

## Deployment sequence after approval

1. Re-read current Hostinger paths/settings and compare reviewed source hashes. There may have been changes since this package. Preserve active source, production-indexing startup wrapper, public-media robots exception, CORS/private gates, current .htaccess and runtime `.env`. Do not use broad source synchronisation or a stale package to replace unrelated work.
2. Create a fresh private backup, recording exact prior `hbuilds/current` target, explicit PassengerAppRoot/StartupFile, main .htaccess, API CatalogueData.php and managed last-source files. Back up secrets privately with restrictive permissions; never add them to package/Git. No database restore is part of code rollout/rollback.
3. Install API `CatalogueData.php` atomically only after baseline comparison. Current documented Laravel path is `/home/u688505577/elvarqo/releases/20261101-stage01/backend` (reconfirm). Run `/opt/alt/php85/usr/bin/php -l app/Catalogue/CatalogueData.php`. Confirm actual Women/Knitwear parent_id9 in public JSON, prices/variants and API health unchanged. No migration/config clear required for this class change. If opcode cache retains old code, use the existing hosting-supported PHP refresh rather than exposing a reset endpoint.
4. Expand the **Linux-built** standalone artifact into a fresh immutable version directory, e.g. `hbuilds/versions/elvarqo-customer-seo-20261105-v2/nodejs`. Keep the prior version. Copy older `.next/static` chunks into the new directory without overwriting newly built chunks so existing sessions can load them. Copy current managed version metadata/public_html as required by the established Passenger layout, preserving protection rules.
5. Ensure production runtime API URLs/site/origin and indexing remain exactly the existing canonical values documented in the build script; `production-indexing.cjs` is for the live canonical site only. Do not use it on staging. Record new target, atomically switch current symlink and explicit PassengerAppRoot, retain `PassengerStartupFile production-indexing.cjs`, then use existing restart marker/graceful restart of the positively identified old owned storefront process. Never kill generic Node/PHP processes.
6. Execute every item in LIVE-VERIFICATION.md before updating managed last-source. If any critical check fails, roll back source/config only. Clear relevant HCDN storefront cache only when ordinary URL responses show stale HTML; compare headers and cache-busting response but verify the ordinary URL again. Do not alter DNS/nameservers.
7. After acceptance, retain managed last-source consistency: use the compatible archive's source and MJS/config/package transformations together; do not reupload an old source ZIP. Keep previous immutable release, backup and checksums until operational acceptance. Catalogue-copy/alt proposals require a separate reviewed save, not deployment.

## Rollback steps (code/config only)

No rollback is needed now: nothing was activated. For a future approved rollout, use the **recorded** prior target and backup, not guessed values:

1. Stop rollout. Keep evidence/log and new version for diagnosis. Do not touch catalogue/orders/accounts/media or run migrate:fresh/seed/import.
2. Atomically restore the saved main .htaccess (including prior PassengerAppRoot/StartupFile) and prior `hbuilds/current` symlink. Use a temporary sibling symlink + `mv -T` as in the established deployment scripts, after confirming both target paths are inside the expected hbuilds directory. Do not recursively delete/move directories.
3. Restore saved API `app/Catalogue/CatalogueData.php` atomically if it changed. Verify syntax. Existing storefront falls back safely when parent_id is absent.
4. Restore only the changed managed last-source files/configuration from the backup. Keep current environment secrets unless rollout explicitly modified them (this package does not require that). Preserve API public-image robots/header exception and private API noindex.
5. Gracefully restart only the positively identified owned new storefront process, allow Passenger to start the prior release, and verify public product/checkout/sitemap and noindex/private protection. Clear relevant stale cache if necessary and verify ordinary URLs. No database restore or rollback command.
6. Record rollback time/previous/new release, checksums, failure and verification outcome; leave the new release inactive. A source rollback must not revert later catalogue or customer changes.

Historical deployment scripts under `deploy/hostinger/product-schema/` are references only. Their release names, hashes and old breadcrumb verifier describe the previous release and must not be rerun blindly.

Actual Linux artifact SHA256: `eb7b067eb40c347dc964cb6b2fcb36c30acf162ae6f41d41ad20468685de3522` (31423955 bytes). Active release remains `versions/elvarqo-product-schema-20261105-v1`. Preservation check log confirms unchanged .htaccess and API source. The loopback test child was terminated; no continuous helper remains.
