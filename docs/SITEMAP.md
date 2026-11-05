# Dynamic production sitemap — deployed 5 November 2026

## Live result

https://elvarqo.uk/sitemap.xml returns **200**, `Content-Type: application/xml; charset=utf-8`.
GET and HEAD both pass. The www alias also returns XML containing apex canonical URLs.
The live XML currently contains 11 URLs: six published products, Women, Knitwear,
and `/`, `/shop`, `/collections`. Every listed URL was fetched and verified as 200,
indexable, with a matching canonical. robots.txt advertises
`Sitemap: https://elvarqo.uk/sitemap.xml`. See `SITEMAP-LIVE-RESULTS.json` for results.

The old URL reached the catch-all React page because no concrete sitemap route
existed. The new Next App Router handler `src/app/sitemap.xml/route.ts` takes
precedence over `[slug]`; the production route manifest confirms a dynamic
`/sitemap.xml`. Hostinger's main .htaccess contains Passenger app routing, with no
SPA rewrite to index.html. This deployment preserves that routing. No HTML page
fallback, static sitemap file or custom domain redirect is used.

## Architecture and freshness

The read-only Laravel `GET /api/v1/catalogue/sitemap?page=1` endpoint uses existing
`PublicDiscovery::published`, `collectionVisible` and `CategoryHierarchy::visible`
queries. It exposes only current canonical product slugs, product updated_at
timestamps, useful visible collection/category slugs and pagination. It excludes
drafts, deleted products, inactive/archived variants, hidden parents/categories,
and groups containing no publicly eligible products. No supplier/account fields
or staff permissions are exposed or changed. Existing catalogue endpoints retain
their behaviour.

The storefront fetches each feed page with `cache: no-store`, renders XML per
request (`force-dynamic`) and sends `Cache-Control: no-store, max-age=0`.
Catalogue publication, removal and editing therefore update the next sitemap
request without a rebuild or cron. Product lastmod comes from the actual database
edit date, converted to ISO UTC. Unknown/invalid/future dates are omitted. Static
navigation and groups have no reliable aggregate content timestamp, so lastmod
is deliberately omitted for them; generation time is never substituted.

URLs are canonical HTTPS apex URLs, XML-escaped, validated and deduplicated.
Private pages, admin/account/basket/checkout, search/filter query URLs and existing
noindex informational previews are omitted. The only currently indexable static
content routes are homepage, shop and collections. Add future finished public
content routes to `publicContentPaths` only after making their metadata indexable.
Useful category page metadata now agrees with the sitemap (the previous check
only recognised top-level collections).

The feed paginates 500 products at a time. The renderer enforces the single-file
50,000 URL limit; a larger catalogue must add a sitemap index/split files rather
than silently truncating. Feed failures or inconsistent pagination return uncached
XML HTTP 503 with Retry-After 60 rather than an empty/partial successful sitemap.
Staging/unknown hosts or disabled indexing return 404; staging robots advertises
no sitemap and still disallows all. Existing admin and draft exclusions remain.

## Exact source files to deploy

Backend (no migrations, packages, or environment changes):

1. `backend/app/Http/Controllers/CatalogueSitemapController.php` — new public read-only feed.
2. `backend/routes/catalogue.php` — additive feed route under existing read throttle.

Rebuild storefront with these four source changes:

1. `storefront/src/lib/sitemap.ts` — XML/pagination/canonical/date helpers.
2. `storefront/src/app/sitemap.xml/route.ts` — concrete XML GET/HEAD route.
3. `storefront/src/app/robots.ts` — sitemap reference on production only.
4. `storefront/src/app/collections/[collection]/page.tsx` — useful-category indexability.

Retain `storefront/tests/sitemap.test.mjs`, updated verification commands in
`storefront/package.json`, and the added test in
`backend/tests/Feature/PublicDiscoveryTest.php` in source control.
The admin does not need a build or upload. No products were published, changed,
or created as part of deployment.

## Production deployment steps used

Reviewed source archive SHA256:
`df87b3544dfb7d1648f865ea5b595201820541bc17bf6786265cd68f494c6c91`.
Private staging directory on server:
`/home/u688505577/elvarqo/deployments/20261105-sitemap-v1`.

Exact scripts are saved under `deploy/hostinger/sitemap/`. They have baseline and
existing-directory guards and are records of this release; for a future release
create a fresh staging/backup/version name and matching reviewed archive hash.
Do not rerun against an existing release or blindly overwrite new source.

1. Upload the scoped source archive to private staging, validate its SHA256,
   and compare the live source against reviewed baselines. Keep all .env files,
   credentials and catalogue data out of the archive.
2. Run `build-sitemap.sh`. It copies existing managed last-source into isolated
   staging, overlays the four storefront files, keeps the Hostinger-compatible
   webpack build and adds standalone output in the copied config. Node 24 runs
   `npm ci --include=dev --no-audit --no-fund`, then `npm run build`.
3. Run `deploy-sitemap-api.sh`. It backs up backend routes, checks both PHP files,
   installs them into the existing private Laravel release, runs
   `/opt/alt/php85/usr/bin/php artisan route:cache`, and verifies feed and health.
   This is additive and write-free. No migration or seeding command is required.
4. Run `deploy-sitemap-storefront.sh`. It backs up the previous link, main
   .htaccess and existing source; installs the complete standalone build, public
   files and static chunks; retains older static chunks; preserves production
   startup indexing; switches current and explicit PassengerAppRoot atomically;
   and gracefully restarts only owned storefront processes with the old exact
   working directory. It verifies XML, robots, homepage indexing and availability,
   rolling back the storefront if checks fail. It then updates managed last-source.
5. Run `verify-live-sitemap.py` from Windows (Python standard library and curl.exe).
   It parses real public XML, checks GET/HEAD, normal and cache-busting URLs,
   robots.txt, every canonical page and API lastmod values.

These scripts can be sent over the authorised SSH connection using `sh -s` from
Git Bash/Linux, for example:

```bash
ssh -p 65002 -i ~/.ssh/elvarqo_hostinger_ed25519 u688505577@153.92.6.10 'sh -s' < deploy/hostinger/sitemap/build-sitemap.sh
ssh -p 65002 -i ~/.ssh/elvarqo_hostinger_ed25519 u688505577@153.92.6.10 'sh -s' < deploy/hostinger/sitemap/deploy-sitemap-api.sh
ssh -p 65002 -i ~/.ssh/elvarqo_hostinger_ed25519 u688505577@153.92.6.10 'sh -s' < deploy/hostinger/sitemap/deploy-sitemap-storefront.sh
```

The authorised deployment used the pinned-host-key SSH helper instead of changing
SSH trust settings. Keep server host-key verification enabled.

Current release:
`/home/u688505577/domains/elvarqo.uk/hbuilds/versions/elvarqo-sitemap-20261105-v1/nodejs`.
Both main and version .htaccess use that exact PassengerAppRoot and
`PassengerStartupFile production-indexing.cjs`. Preserve this production startup
wrapper (documented in `PRODUCTION-INDEXING.md`) and production settings:

```dotenv
NODE_ENV=production
NEXT_PUBLIC_SITE_URL=https://elvarqo.uk
STOREFRONT_ORIGIN=https://elvarqo.uk
STOREFRONT_INDEXING=true
NEXT_PUBLIC_API_URL=https://api.elvarqo.uk/api/v1
CATALOGUE_API_URL=https://api.elvarqo.uk/api/v1
COMMERCE_API_URL=https://api.elvarqo.uk/api/v1
```

Backups are private under `elvarqo/backups/20261105-sitemap-api-v1` and
`20261105-sitemap-storefront-v1`. Restore the previous PassengerAppRoot/link and
restart only that storefront if rollback is needed. To roll back the API, restore
its saved routes and rebuild Laravel route cache. Do not restore or reset the
database for this change.

## Tests and local verification

From `storefront/`:

```powershell
npm run test:sitemap
npm run test:indexing
npm run test:cards
npm run format:check
npm run lint
npm run typecheck
```

From `backend/`:

```powershell
php artisan test --compact --filter=PublicDiscoveryTest
```

Passed: 5 sitemap tests; 4 indexing tests; 5 existing product-card tests; formatting,
lint and TypeScript; production Hostinger build; 11 Laravel tests / 104 assertions.
Fixture tests verify real edits/unpublication and new lastmod without rebuilding.
Live XML parsing verifies all 11 URLs and their indexability/canonicals, exact
current-feed dates, GET/HEAD and robots. Apex/www and cache-busting XML responses
are DYNAMIC/no-store; stale HTML was not observed.

An additional isolated HTTP test process on shared hosting printed Next Ready
but its local loopback listener refused connections; that optional process-level
mock test was not counted as passing. The live Passenger route, unit failure tests
and isolated Laravel edit/removal fixtures above did pass. No live catalogue data
was modified for testing.

Manual checks:

```powershell
curl.exe -i https://elvarqo.uk/sitemap.xml
curl.exe -I https://elvarqo.uk/sitemap.xml
curl.exe -sS https://elvarqo.uk/robots.txt
python deploy/hostinger/sitemap/verify-live-sitemap.py
```

Submit `sitemap.xml` again in Google Search Console. A successful public fetch is
verified; Search Console's refresh and Google's eventual indexing are external
to this deployment and have not been claimed as completed.
