# Phase 5 — setup and testing
26 October 2026. Project: `C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO`.

## Existing installation

No new package dependency, database migration, account or credentials are required for Phase 5. Keep existing .env/.env.local files and APP_KEY. Phase 4's image worker still requires `npm ci --prefix backend/media` on a fresh installation. README covers full dependency installation. The configured SQLite database does not require XAMPP.

Stop any old storefront terminal with Ctrl+C, then start three terminals:

```powershell
# Terminal 1 — Laravel, including Phase 4 image upload limits
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\backend"
php -d upload_max_filesize=8M -d post_max_size=12M -S localhost:8000 -t public public/index.php
```

```powershell
# Terminal 2 — live public storefront
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\storefront"
npm run dev
```

```powershell
# Terminal 3 — existing private staff admin
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\admin"
npm run dev
```

Use `npm.cmd` if PowerShell blocks npm. Keep localhost consistent: 3000 storefront, 5173 admin, 8000 API. Do not substitute 127.0.0.1 without changing the allowed origins. The PHP native server is local development only.

## Publish actual products first

No QA merchandise was seeded into your development database. If you have not entered products yet, the storefront intentionally shows empty catalogue/collection states. Sign in at http://localhost:5173/?view=products with your staff account and authenticator. Follow PHASE4-SETUP.md to create an active collection, product, variants, verified measurements, images with alt text, prices and publication. Record actual stock through Inventory. Zero stock is shown as unavailable; it is never invented. An active but empty/draft-only collection is hidden from public navigation. Owner bootstrap/recovery remains in STAFF-ACCESS.md; no default login exists.

## Customer routes

- http://localhost:3000 — live published home product sections and collection links; the campaign illustration remains labelled a design concept until merchant assets are provided.
- http://localhost:3000/collections — non-empty published collection/category navigation.
- http://localhost:3000/collections/YOUR_COLLECTION_SLUG — ordered collection listing and filters.
- http://localhost:3000/shop — all published products; server filters/sort/pagination.
- http://localhost:3000/search?q=YOUR_SEARCH — published product search.
- http://localhost:3000/products/YOUR_PRODUCT_SLUG — gallery, colour/size/price/availability, fit/care and size guide.
- http://localhost:3000/wishlist — guest pieces saved in this browser, refreshed from current published data.

Example filter URL (replace values with those actually offered): `/shop?colour=Navy&size=M&min=20&max=100&stock=in_stock&sort=price_asc&page=1`. Browser min/max are GBP amounts; Laravel receives integer minor units. Apply filters resets page. Previous/Next retain filters and sort. Clear all removes them. No SKU, price or available quantity is hard-coded in saleable product UI.

## Step-by-step manual verification

1. Open the homepage and a collection. Confirm titles, product images and prices match saved/published admin content. A draft product must be absent. Empty collections must not appear as navigation tiles.
2. Apply colour, size, price and availability filters. Copy the URL into a fresh tab and reload. Confirm the same filters, order, result count and page. Test Next/Previous and browser Back. Variant constraints match one variant (Navy/M stock cannot satisfy a Sand/L filter).
3. Search from the header. Suggestions wait 300ms, use published products and announce counts. Submit and confirm the query remains in the form. Try a nonsense query; use Clear filters and browse again to recover. Test invalid URL price/sort values: the page preserves inputs and offers clear/retry actions.
4. Open a product. Choose colour and then size. Confirm variant price, sold-out labels, linked images and any fit/care overrides. A size must be selected before Review selection is available. Sold-out sizes may be inspected but cannot produce an available selection summary. Combinations not offered are disabled.
5. Open Size guide. Switch centimetres/inches and confirm supplier-entered values/variant overrides. Choose a size from the guide. Use Tab/Shift+Tab and Escape to verify focus remains inside, closes and returns to the trigger. Native radio groups support arrow keys; selected/sold-out states are textual and announced. Continue target-device/screen-reader checks before launch.
6. Use Quick select on a product card. It shares the same variant/availability controls. Review selection shows the current choice and explicitly confirms nothing was added/reserved. Bag stays zero. This is not a checkout or a test order.
7. Save a selected piece. Open Saved, reload and open a second tab. Confirm persistence and that removal in one tab updates the other. Only product_id and optional variant_id are stored, never prices or descriptions. Reopening a saved piece restores a valid saved variant. Unpublished/missing products become removable unavailable entries without stale titles/prices.
8. Change a draft/unpublished product's price in admin, republish and refresh availability on its product page. Current server prices must appear. Detail/quick views also refresh on focus/visibility and every 30 seconds while visible. Review selection fetches again before showing its summary. This is informational availability; only Phase 6 can reserve stock.
9. Stop the API terminal. Reload a filtered listing: recovery should preserve its query and offer Retry catalogue. Restart the API and retry. On an open product, Refresh availability must explain the failure and disable review until verified. Image failures have placeholders and a working retry, including errors before hydration.
10. Unpublish a product and request its URL in a fresh tab: expect the intentional 404 page. Unknown/draft products also return HTTP 404. Unpublish, rename and republish; the old product URL must HTTP 308 to the current slug (API alias remains 301), and canonical metadata uses the new URL.
11. Inspect page source with JavaScript disabled: home/list/product catalogue content and real product text remain rendered by the server. Interactive overlays and guest storage need JavaScript. Native GET filters remain available with no JavaScript.
12. Check 390/768/1440px, keyboard operation, reduced motion and 200% browser zoom on your devices. Automated Phase 5 checks use equivalent 720px reflow for 200%; they do not claim a fresh native-zoom or screen-reader certification.

## Configuration and rendering

`NEXT_PUBLIC_API_URL` defaults to `http://localhost:8000/api/v1` and is public browser configuration. Optional `CATALOGUE_API_URL` is the server-side origin for the same public catalogue, e.g. an internal backend address. Public media links use NEXT_PUBLIC_API_URL so internal hostnames are not exposed. Restart dev servers after changing env; rebuild production assets after changing NEXT_PUBLIC values.

`NEXT_PUBLIC_SITE_URL` defaults to `https://elvarqo.uk` for canonical metadata. `STOREFRONT_INDEXING=false` is the safe default. Indexing requires both production mode and explicit true; do not opt in before the merchant approves deployment/content. Home, unfiltered non-empty collections and products use configured canonical paths; filtered collection URLs are noindex with base collection canonical, and product variant query URLs canonicalize to the base product. Shop/search remain noindex and canonicalize to /shop. Wishlist stays noindex/nofollow. Full SEO launch review/sitemap work remains Phase 11.

Public pages now use request-time SSR with no-store catalogue fetches; Phase 2 cacheComponents/partialPrefetching are disabled. Metadata streaming is disabled so missing and alias product requests return actual HTTP 404/308 before body streaming. Server JSON fetch timeout is 7 seconds; client saved/product refresh 8 seconds. No catalogue is required during `next build`. A failing catalogue is not misrepresented as an empty successful response.

## Repeatable automated checks

```powershell
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO"
cd backend
composer validate --strict
composer format:check
composer test
cd ../storefront
npm run check
cd ../admin
npm run check
cd ..
npm install --prefix .qa --no-save playwright
node shared/tests/storefront-shopping.cjs
node shared/tests/catalogue.cjs
```

On Windows browser tests use installed Microsoft Edge. On Linux/macOS install Chromium with `node .qa/node_modules/playwright/cli.js install chromium`. The shopping suite builds an isolated production Next server in ignored storefront/.next-qa and starts only its own services on 3001/8007; Phase 4 regression uses 5181/8006. Leave these ports free. Guarded fixture helpers require a private local SQLite path and never seed development data. Existing running user services are not stopped.

QA databases, build logs, private images and fixture IDs live in ignored .qa by default; screenshots/results are in docs/verification/phase5. Optional ELVARQO_NEXT_DIST exists for test-build isolation; normal builds retain .next. The recorded run used a complete isolated production build, not a mocked UI. Keep test run directories private. Existing Phase 2 preview acceptance scripts are historical and do not describe current shopping behavior.

There is no Phase 5 migration and no package-lock change. SQLite is verified. Production hosting/TLS, real load, wider browser/device coverage, physical screen readers, real campaign assets and merchant-approved products remain release inputs. Search covers catalogue products; future editorial search, genuine reviews, restock signup, newsletter and customer account sync follow their respective later phases. Phase 6 has not started.
