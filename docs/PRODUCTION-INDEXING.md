# Production search indexing

## Cause and deployed fix — 4 November 2026

The public root layout and page metadata derived their robots directives from
`STOREFRONT_INDEXING`. Hostinger's managed runtime configuration and inherited
process environment had this set to `false`. The original public homepage returned
`<meta name="robots" content="noindex, follow"/>`; its HTTP headers did not contain
an additional noindex directive.

Production is now enabled only when all three conditions hold:

```dotenv
NODE_ENV=production
NEXT_PUBLIC_SITE_URL=https://elvarqo.uk
STOREFRONT_INDEXING=true
```

Set these in the **existing elvarqo.uk Web App's Environment variables**, at build
and runtime. Keep `STOREFRONT_ORIGIN=https://elvarqo.uk` and the three existing API
URLs unchanged. Local `.env.example` and all staging deployments must retain
`STOREFRONT_INDEXING=false`. Do not copy production configuration to staging.

## Exact deployment contents

These eight storefront source files were overlaid onto the current managed source
before rebuilding the complete app:

- `storefront/src/lib/indexing-policy.ts` — canonical production gate and route/host exclusions.
- `storefront/src/lib/catalogue-server.ts` — shared indexEnabled setting used by root/home/collection/product metadata.
- `storefront/src/proxy.ts` — HTTP noindex on private routes and non-production hostnames.
- `storefront/src/app/robots.ts` — generated public/private robots.txt.
- `storefront/src/app/shop/page.tsx` — base shop indexable; filtered queries excluded.
- `storefront/src/app/development/page.tsx` — diagnostic noindex metadata.
- `storefront/src/app/design-system/page.tsx` — diagnostic noindex metadata.
- `storefront/src/app/[slug]/page.tsx` — existing incomplete informational previews remain noindex.

Also retain the local `.env.example`, `package.json` verification command and
`tests/indexing-policy.test.mjs` changes in source control. No dependency changes,
database migrations, admin rebuild or backend deployment are required for this fix.

The isolated Hostinger build used Node 24, `npm ci --include=dev --no-audit --no-fund`
and the existing compatible `next build --webpack` command, with the production
settings above and `output: 'standalone'` in the copied build config. Deploy the
**complete new build**: `.next/standalone`, `.next/static`, and `public`. Keep older
static chunks available during the transition. Do not mix a new server bundle with
only part of a previous build.

Current deployed release:
`/home/u688505577/domains/elvarqo.uk/hbuilds/versions/elvarqo-indexing-20261104-v2/nodejs`.
Managed `hbuilds/last-source` contains all eight updated source files.

### Hostinger startup setting

Changing `hbuilds/config/.env` alone did not replace Hostinger's inherited false
environment value. The initial failed live check automatically rolled back. The
successful release additionally uses `deploy/hostinger/production-indexing.cjs`,
copied **beside standalone server.js** as `production-indexing.cjs`. It explicitly
sets the production indexing flag before loading Next. It is only the live
storefront's startup entry; do not use it for staging or admin.

The live main `public_html/.htaccess` and version's `public_html/.htaccess` have:

```apache
PassengerAppRoot /home/u688505577/domains/elvarqo.uk/hbuilds/versions/elvarqo-indexing-20261104-v2/nodejs
PassengerStartupFile production-indexing.cjs
```

All other directives were preserved. Persistent managed `hbuilds/config/.env` now
has `STOREFRONT_INDEXING='true'`; its other entries were preserved. Set true in
hPanel too before any future managed upload/redeploy, which may regenerate startup
configuration. A redeploy with the old false flag would bring noindex back.

## Exclusions retained

Only exact `elvarqo.uk` and `www.elvarqo.uk` hosts are eligible. Other hosts receive
`X-Robots-Tag: noindex, nofollow`; staging robots.txt disallows all crawling.
Admin remains independently noindex in its existing HTML and HTTP header.
Account, basket, checkout, payment, search, wishlist, tracking, returns and
diagnostic routes remain excluded, as do filtered listings and unfinished
informational preview pages. The existing Laravel published-only queries and
publication validation were not changed. Missing or unpublished products still
use 404/notFound exclusion. No products were created or published by this change.

## Verification and stale HTML

The post-deployment public HTTPS checks are recorded in
`PRODUCTION-INDEXING-LIVE-RESULTS.json`. Both apex and www, with ordinary and
cache-busting requests, returned HTTP 200 and `index, follow`, no X-Robots-Tag,
`x-hcdn-cache-status: DYNAMIC`, and private/no-cache/no-store/must-revalidate.
No stale noindex HTML was observed. Main shop, collections, Women and a published
product also passed. Private/filtered pages and admin retained noindex, and a
missing product returned 404/noindex. robots.txt permits public crawling.

Hostinger rejects a Googlebot user-agent spoof from this workstation with HTTP 403.
This is **not** a successful real Google crawler test, nor proof that genuine
Googlebot is blocked. After deployment run Search Console URL inspection →
**Test live URL** for https://elvarqo.uk/, confirm indexing allowed, then request
indexing. Search inclusion/timing is controlled by Google.

For later deployment checks (PowerShell):

```powershell
curl.exe -sS -D headers.txt -o homepage.html https://elvarqo.uk/
Select-String -Path homepage.html -Pattern '<meta[^>]*name="robots"[^>]*>' -AllMatches
Get-Content headers.txt
curl.exe -sS https://elvarqo.uk/robots.txt
curl.exe -sS -D fresh-headers.txt -o fresh.html 'https://elvarqo.uk/?indexing_check=NEW_TIMESTAMP'
```

Check normal URL and fresh URL, not just a cache-busted URL. Confirm no noindex in
robots/googlebot metadata or HTTP X-Robots-Tag. If old HTML appears, verify the
PassengerAppRoot points to the new release, restart only this storefront's owned
Next process, then purge the storefront's CDN cache through hPanel if necessary.
Switching the current symlink or touching restart.txt alone previously left the
old process running on this account. Keep private release backups for rollback;
do not delete or replace application data.

## Completed checks

- Hostinger isolated production build: passed (Node 24, Next 16.4.2, webpack).
- Storefront indexing-policy tests: 4 passed.
- Existing product-card tests: 5 passed; lint, TypeScript and formatting checks passed.
- Laravel PublicDiscoveryTest: 10 passed, 92 assertions, isolated SQLite memory database; drafts remain private.
- Public HTTPS response checks: 19 passed plus robots.txt; evidence JSON includes status, metadata, HTTP robots and cache headers.
- Live compact cards and availability regression checks passed on apex and www.
- No real Search Console crawler result is claimed; owner must repeat Test live URL.
