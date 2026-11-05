# Product structured data — 5 November 2026

## Implemented and deployed

Reusable native JSON-LD script is rendered in the initial server-generated HTML
of every successfully loaded, indexable published product page. The existing
public Laravel endpoint still decides publication/variant eligibility. Missing,
unpublished or failed product loads do not get product markup. Server fetches
remain no-store, so future catalogue prices/availability are reflected on the
next request without a rebuild. No products or database records were changed.

Multi-variant products use a ProductGroup with nested Product entries and one
genuine Offer per actual variant. Group ID uses the existing local product ID,
not an invented SKU/GTIN. Each variant has its real SKU where present, colour,
size, exact current GBP price and schema.org availability from the same
variantInStock function used by the customer interface. Sale offers use the
actual price_minor; compare-at prices and arbitrary expiry dates are not substituted.
URLs use the single canonical product URL and existing ?variant=ID selection.
One-variant products use Product directly. No AggregateOffer represents variants.

Product names/descriptions and absolute HTTPS images come from public catalogue
data. Linked variant images are prioritised, with shared gallery photos retained
exactly as the current UI presents them. Current imported photos are mostly
shared: no colour-to-photo matches are inferred. Staff can use the existing image
variant links to provide specific colour imagery; markup then follows those links.
No ratings, reviews, brand/manufacturer, GTIN, material composition, shipping,
item condition or return-policy assertions are invented.

BreadcrumbList uses the visible trail exactly: Shop → first displayed collection
(when present) → product. The noscript variant table now uses the same availability
rule instead of showing zero counted units for always-available products.
Serialization escapes <, >, &, U+2028 and U+2029 while preserving valid JSON,
including hostile strings such as closing script tags.

## Google documentation checked

- [Product variants](https://developers.google.com/search/docs/appearance/structured-data/product-variants): single-page ProductGroup/hasVariant, variesBy, group identity, direct variant URLs and server HTML.
- [Merchant listings](https://developers.google.com/search/docs/appearance/structured-data/merchant-listing): Product images/name/Offer and genuine currency/price/availability.
- [Product snippets](https://developers.google.com/search/docs/appearance/structured-data/product-snippet): offers without invented review/rating claims; AggregateOffer is not for variants.
- [BreadcrumbList](https://developers.google.com/search/docs/appearance/structured-data/breadcrumb): visible trail names, URL items and sequential positions.
- Installed Next.js 16.4.2 JSON-LD guide: native server-rendered script and escaping.

## Separate verification results

Local: six structured-data tests passed (group/single product, genuine identifiers,
prices, stock rules, images, breadcrumb omissions, unsafe URL rejection and script
injection). Existing 4 indexing, 5 sitemap and 5 card tests passed. Formatting,
lint, TypeScript and the isolated Hostinger production build passed. Laravel
PublicDiscoveryTest: 12 tests, 111 assertions passed, including public image
indexing, draft image 404 and private staff image 401.

Live HTTP checks: all six product pages' initial scripts parse as JSON and match
all 216 actual variant offers in the public API (SKU, price, GBP, availability,
URL and image gallery). For each product, first/last variant URLs were fetched
and their server-rendered selected radios and displayed price verified. All
breadcrumbs match visible HTML/canonicals. All six main image responses return
200/image-webp without noindex, and the ordinary API robots policy permits them.
Missing product returns 404 without product JSON-LD; private API remains noindex.
Evidence: PRODUCT-STRUCTURED-DATA-LIVE-RESULTS.json.

Google's own **live URL** Rich Results Test was run separately after deployment:

| Product slug | Valid items | Merchant listings | Live Google result |
| --- | ---: | ---: | --- |
| womens-roll-neck-knitted-jumper | 62 | 60 | [Google report](https://search.google.com/test/rich-results/result?id=G5YPII8G8HCDaAtXpZKP_A) |
| womens-textured-v-neck-knitted-jumper | 27 | 25 | [Google report](https://search.google.com/test/rich-results/result?id=v82J2rnLisUY6--cruHXlg) |
| womens-crew-neck-knitted-jumper | 38 | 36 | [Google report](https://search.google.com/test/rich-results/result?id=1SvdJdD8nQ-yhzCIcUx9dQ) |
| womens-high-neck-knitted-jumper | 22 | 20 | [Google report](https://search.google.com/test/rich-results/result?id=3XnwwY3_vJybAoihnjaJnQ) |
| womens-collared-knitted-jumper | 22 | 20 | [Google report](https://search.google.com/test/rich-results/result?id=InSBvTTkiuHwCUzj7-fZlg) |
| womens-v-neck-knitted-jumper | 57 | 55 | [Google report](https://search.google.com/test/rich-results/result?id=P3V7jCe82qgc5w8YXVdGiA) |

Each report also detects one product snippet and one BreadcrumbList.
Non-critical merchant warnings remain for unknown global identifier/brand,
shippingDetails and hasMerchantReturnPolicy. These omissions are intentional;
do not fabricate them to remove warnings. Optional review/rating fields can also
be flagged in product snippets. Validation means eligibility, not a guarantee of
indexing or rich-result display. Google reports can expire; rerun from their URL
field or the [Rich Results Test](https://search.google.com/test/rich-results).
See GOOGLE-PRODUCT-RICH-RESULTS.json and GOOGLE-RICH-RESULTS-ROLL-NECK.png.

## Exact files/settings deployed

Storefront source (rebuild the complete app):

1. storefront/src/lib/product-structured-data.ts
2. storefront/src/components/shop/product-structured-data.tsx
3. storefront/src/app/products/[product]/page.tsx

Backend source:

4. backend/app/Http/Controllers/PublicCatalogueController.php — only the successful,
   existing public image response gains X-Robots-Tag: index, follow after validation.

Production web configuration:

5. deploy/hostinger/api-robots.txt → API public_html/robots.txt, allowing only
   /api/v1/catalogue/media/ over the existing Disallow: /.
6. API public_html/index.php: replace the unconditional production noindex header
   with the narrowly scoped predicate below; preserve its bootstrap paths and all
   other PHP-version/maintenance logic. Symfony response headers did not replace
   the entry-point PHP header; leaving both makes noindex win.

```php
$publicImageRequest = preg_match('#^/api/v1/catalogue/media/[a-f0-9-]{36}/(?:640|1280|1920)$#i', parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '') === 1;
if (! $publicImageRequest) {
    header('X-Robots-Tag: noindex, nofollow');
}
```

This exempts only the public image URL shape; existing controller publication,
variant/media archival, file and allowed-size checks still apply. Staff/API JSON
responses stay noindex and authentication is unchanged. No data access is granted.
No migrations, environment secrets, dependency changes or admin deployment.
Retain package.json test command and both test-file changes in source control.

## Deployment steps used / repeat guidance

The exact guarded scripts and live verifier are in deploy/hostinger/product-schema/.
They are release records; do not rerun with their existing directory names. A new
release needs fresh staging/backup/version names and reviewed baseline/archive
hashes. Current archive SHA256:
840f596ef0ee3bee33c14b541c473067cbd7b48a7378b7954289eba9bcb43623.

1. Upload only scoped source to private staging
   /home/u688505577/elvarqo/deployments/20261105-product-schema-v1/package.tar.gz.
   Validate SHA256 and compare old source hashes before overwriting anything.
2. Run build-product-schema.sh over pinned SSH. It overlays the three storefront
   files on copied managed last-source, uses existing Hostinger webpack-compatible
   config with standalone output, Node 24, npm ci --include=dev --no-audit --no-fund
   then npm run build. Keep production indexing/origins/API variables from
   PRODUCTION-INDEXING.md. There are no new environment settings.
3. Run deploy-schema-media-v2.sh. It backs up and checks the controller, API entry
   point and robots policy, applies the narrow exception and validates real public
   image headers plus private API noindex. It rolls back on failure.
4. Run deploy-product-schema-storefront.sh. It backs up existing source, app link
   and main .htaccess, installs the complete standalone/static/public build, keeps
   old static chunks, switches the explicit PassengerAppRoot/current link, retains
   production-indexing.cjs, and gracefully restarts only owned old storefront
   processes. JSON-LD, buying availability, homepage indexing and sitemap checks
   must pass before it updates managed last-source. Failed checks roll back.
5. Hostinger hPanel → api.elvarqo.uk dashboard → Clear cache. This was necessary:
   normal robots.txt was an HCDN HIT serving the old Disallow-all file while a
   cache-busting URL showed the new rule. Verify the **ordinary** URL, not only a
   fresh query. The UI confirmed Cache cleaned successfully, and live checks then
   passed with the correct public image rule.
6. Run python deploy/hostinger/product-schema/verify-product-schema.py on Windows
   (Python standard library + curl.exe), and rerun Google's URL-based Rich Results
   Test separately. No publication or seed command is required.

Current storefront release:
/home/u688505577/domains/elvarqo.uk/hbuilds/versions/elvarqo-product-schema-20261105-v1/nodejs.
PassengerStartupFile remains production-indexing.cjs. Laravel remains at
/home/u688505577/elvarqo/releases/20261101-stage01/backend.
Backups: elvarqo/backups/20261105-product-schema-media-v2 and
20261105-product-schema-storefront-v1. Restoring code/config must not restore or
reset catalogue/order data. Preserve the media robots exception on future uploads.

Local commands from storefront: npm run test:structured-data; npm run test:indexing;
npm run test:sitemap; npm run test:cards; npm run format:check; npm run lint;
npm run typecheck. From backend: php artisan test --compact --filter=PublicDiscoveryTest.
