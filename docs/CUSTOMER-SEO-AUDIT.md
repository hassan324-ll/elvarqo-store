> Final review follow-up: see [exact before/after copy](customer-seo-final-review/COPY-BEFORE-AFTER.md), [per-product checklists](customer-seo-final-review/PRODUCT-CHECKLISTS.md) and [current ready/blocked gates](customer-seo-final-review/READY-AND-BLOCKED.md). The final source removes the internal photo warning and uses shorter public fallbacks; those supersede the earlier wording described below. Private Linux build was subsequently approved and completed, without deployment. Original audit evidence remains historical.

# Customer SEO and product accuracy audit — 5 November 2026

**Implemented and tested locally only. Nothing deployed, published or repriced. No production database writes, policy changes or supplier/image mappings.** The working checkout, dynamic sitemap and server-rendered product structured data are retained. Existing unrelated work remains in place.

## Evidence and limits

Inspected project/root/storefront instructions, installed Next.js metadata documentation, current source, public catalogue/navigation/sitemap, all six product pages, 92 linked public routes and all 67 product photo URLs. The live snapshot was taken at 2026-11-05 07:44 UTC. See `CUSTOMER-SEO-LIVE-AUDIT.json`. All 67 image URLs returned 200; contact sheets of the actual images were reviewed and retained in `verification/customer-seo-20261105/` (local evidence, ignored by Git). This was not a complete external link or Lighthouse audit. Authenticated live admin and live checkout were not exercised; price consistency was checked using the public catalogue, the same Laravel Pricing logic on an isolated snapshot and the local shopping flow, not a real live order.

Sources: `docs/imports/women-knitwear-first-batch/README.md`, its source manifest/JSON/photos, `docs/WISE-CHECKOUT.md`, `backend/config/commerce.php`, public API records. All six supplier URLs were attempted but could not be retrieved. The import README calls the link/photo matches provisional; catalogue options alone cannot verify supplier stock, composition, measurements, fit, care or fulfilment. No claim of supplier verification is made.

The live six pages already had one H1 each, unique SEO titles/descriptions, canonical HTTPS URLs and valid initial-HTML JSON-LD. They did not need arbitrary renaming or duplicate SEO text. All six have empty material/fit/care and measurement arrays. All 67 photos are shared, with **zero variant-to-photo links**. Existing alt text is generic supplier-photo numbering: non-empty, but it does not establish colour accuracy. Local copy/alt proposals are supplied separately in `CUSTOMER-SEO-COPY-FOR-REVIEW.json`; they are not an import file and have not been applied.

## Per-product findings

All rows received the shared local improvements: category breadcrumb in visible HTML and JSON-LD, truthful missing-details disclosures, shared-photo warning, appropriate detail-image sizing/async decoding. Prices and original titles/descriptions/photos/mappings remain unchanged. All six still need supplier-confirmed identity/options/stock, measurements/composition/care/fit, image rights and approved delivery/returns information.

| Product / public slug | Evidence and issue | Local change / proposal | Unresolved before presenting as verified |
| --- | --- | --- | --- |
| Textured V-Neck — `womens-textured-v-neck-knitted-jumper` — £27.00, 25 variants, 6 photos | Images show V-neck, textured vertical panels, ribbed edges. Current description is photo-supported but generic. Supplier mapping is provisional. | Shared improvements; concise photo-based description and first-image alt proposal saved for review. | Verify product/link identity, actual colours and sizes; no fibre or stock inference. |
| Crew Neck — `womens-crew-neck-knitted-jumper` — £25.00, 36 variants, 13 photos | Primary image is a neckline/fabric close-up; view 2 shows full garment. Six conflicting V-neck source images were excluded during original import. | Shared improvements; propose full-garment lead image and descriptive close-up alt, without changing image order or mapping. | Confirm remaining photos are the same supplier product and colour set; approved lead photo needed. |
| High Neck — `womens-high-neck-knitted-jumper` — £18.50, 20 variants, 6 photos | Most photos show only a slightly raised ribbed neck; black view differs. “High Neck” needs supplier confirmation. | Shared improvements; evidence-bounded raised-neck description/alt proposal. Title and URL retained. | Confirm neckline name, consistent construction across colours and correct source match. |
| Collared — `womens-collared-knitted-jumper` — £22.99, 20 variants, 6 photos | Collar and short button placket are visible; current “open neckline” description omits useful visible detail. | Shared improvements; specific button-placket copy/alt proposal for review. | Verify identity, material and options; do not infer composition from knit texture. |
| V-Neck — `womens-v-neck-knitted-jumper` — £22.00, 55 variants, 16 photos | Plain V-neck and ribbed edges visible. Mirror-selfie photos do not verify sizes, fit or fibre. | Shared improvements; plain V-neck description/alt proposal. | Supplier-confirmed colour/size list and photo associations, not automatic visual guesses. |
| Roll Neck — `womens-roll-neck-knitted-jumper` — £20.99, 60 variants, 20 photos | Raised neckline visible; no clearly folded roll neck. Original import notes already flagged provisional naming. | Shared improvements; raised-neck copy/alt proposal. No rename or URL change made. | Confirm supplier neckline/specification; review whether “Raised Neck” is a more accurate title before changing catalogue. |

The proposed descriptions mention only visible construction. They intentionally omit wool, cashmere, fabric percentages, washing directions, UK manufacture, reviews, discounts, delivery times and supplier stock. Approval of photo identity is still required. Generic alt text should eventually be replaced per actual view, not bulk filled with keywords or guessed colours.

## Implemented source fixes

- Actual category: Women ID **9**, Knitwear ID **16**, parent_id 9, slug **womens-knitwear**. URL remains **https://elvarqo.uk/collections/womens-knitwear**. The helper keys by existing category/parent slugs, not guessed IDs.
- Category H1 is **Women's knitwear**; SEO title **Women's Knitwear & Knitted Jumpers**; concise meta description and useful introduction describe the available neck styles and invite comparison of listed options/prices. All six retain ordinary crawlable product anchors. Header → Women → Knitwear and Shop → Women → Knitwear breadcrumbs work.
- Public catalogue response adds the real category `parent_id`; product breadcrumbs use that relationship, never a guessed category. The same helper feeds visible breadcrumbs and JSON-LD. Backwards-compatible when an older API has no parent_id.
- Empty material/fit/care/measurement sections now explicitly disclose missing information rather than disappearing. No fake size chart is supplied; the general size-guide preview now explains charts appear only where measurements exist.
- Shared photos are explicitly identified as unlinked to individual colour choices. Selecting a colour still selects the correct catalogue variant; it does not silently claim a matched photo.
- Product detail hero uses a responsive `sizes` value appropriate to its real column/mobile width; retained intrinsic dimensions, srcset, lazy loading of secondary images and prioritisation of the primary image; async decoding added.
- Existing preview/content pages now receive their own title, description and canonical, while remaining noindex until actual approved content exists. Unknown routes still return 404. No blanket indexing changes.
- Removed stale bag text promising a reservation on “hosted payment”; the bag now says prices/availability are checked before order placement. Payment implementation and stock rules are unchanged.
- Added focused SEO tests, a public category-parent regression test and ignored the isolated audit build in Git/Prettier. No migrations or new runtime dependencies.

## Remaining SEO / launch findings

- **Production shipping and policies are not approved.** Existing £4.95/£8.95 rates, 3–5/1–2-day estimates after dispatch and zero tax are labelled test assumptions. They are not verified business promises. Contact, delivery/returns, legal/privacy pages remain previews. The new product disclosure makes this gap explicit; it cannot substitute for approved operational/legal information.
- **Always available is a merchant-approved ordering rule**, not confirmation of AliExpress stock. All 216 variants match public API prices/availability, local Laravel Pricing, product offers and the tested quote. Confirm supplier availability and fulfilment arrangements separately.
- Empty department/category pages currently return 200/noindex and stay out of the sitemap. Retain this assessment until they have appropriate published products/useful content. Navigation to empty departments is a merchandising gap, not a reason to index them all.
- Homepage, Shop, Women, Knitwear and six published product URLs are indexable with single canonical URLs. Private/account/bag/checkout/search/filter/preview routes stay excluded. Sitemap has 11 intended canonical URLs, accurate catalogue lastmod dates, application/xml and no HTML fallback. No accidental production public noindex was found in this snapshot.
- Existing information-page duplicate root metadata is fixed locally. No broken product-image URLs or failing linked page fetches were found in the audited scope. A deliberately missing local route returns 404. Do not interpret 200 previews as completed customer information.
- Mobile 390×844 test: product selection, detail disclosure, add-to-bag and bag worked; document content stayed within viewport (375px usable width with browser scrollbar). Collection navigation/thumbnails intentionally scroll horizontally inside their containers. All six initial HTML pages passed checks. This is representative browser verification, not a full WCAG, field Core Web Vitals or Lighthouse certification.
- No current Google Rich Results Test was run on these **undeployed changes**. Prior live Google reports remain historical evidence only. Current Google docs support the retained ProductGroup/hasVariant/variesBy/productGroupID approach. Unknown brand/GTIN, reviews/aggregateRating, shippingDetails and hasMerchantReturnPolicy remain omitted; optional enhancement warnings do not justify invented values. See [Google variants](https://developers.google.com/search/docs/appearance/structured-data/product-variants), [product snippets](https://developers.google.com/search/docs/appearance/structured-data/product-snippet), [merchant listings](https://developers.google.com/search/docs/appearance/structured-data/merchant-listing). Eligibility never guarantees appearance or ranking.

## Tests and evidence

- Storefront: **22 tests passed** (5 card, 4 indexing, 5 sitemap, 6 structured-data, 2 new SEO); lint and TypeScript passed; formatting passed after excluding generated audit output.
- Backend: **57 tests / 606 assertions passed**, filtered CommerceTest, WiseCheckoutTest and PublicDiscoveryTest (including 13 discovery tests / 114 assertions). Pint targeted checks passed.
- Production webpack build passed, including dynamic sitemap and proxy routes. Built in `.next-seo-audit`, not a deployment archive; public browser API in this local build deliberately targets localhost.
- Final local HTTP audit: six products, single H1, unique metadata, canonical/noindex checks, 216 offers, matching 4-item breadcrumbs, six category links, 11 XML sitemap URLs, robots reference, empty/preview exclusions and real 404 passed. Isolated SQLite fixture copied public data only; source database/accounts/orders untouched. Real Laravel Pricing matched all 216 variants.
- Desktop shopping: Beige/M Roll Neck at £20.99; quantity 2 → £41.98; existing standard test delivery £4.95 → final quote **£46.93**. Stopped before accepting terms or placing order; Wise intentionally disabled in the local fixture.
- Mobile: selected Pink/S at £20.99, expanded missing-details disclosures, successfully added to bag; bag showed previous Beige/M quantity 2 and Pink/S quantity 1, subtotal **£62.97**. No live order, payment or inventory mutation.
- Evidence: `verification/customer-seo-20261105/local-route-checks.json`, `desktop-category.png`, `mobile-product.png`, `mobile-disclosures.png`, `mobile-added.png` and six photo review sheets. Public live snapshot is separate from local test results.
- Existing harmless Node module-format warning appears in standalone TS helper tests; it does not fail tests. No package module-mode change was introduced just to suppress it.

## Exact local verification commands

PowerShell (Node 24, project dependencies already installed):

```powershell
cd "C:\Users\Muhammad Hassan Ali\Desktop\ELVARQO\storefront"
npm ci
npm run format:check
npm run lint
npm run typecheck
npm run test:cards
npm run test:indexing
npm run test:sitemap
npm run test:structured-data
npm run test:seo
npm run build -- --webpack
cd ..\backend
composer install
php artisan test --compact --filter='CommerceTest|WiseCheckoutTest|PublicDiscoveryTest'
vendor\bin\pint --test app/Catalogue/CatalogueData.php tests/Feature/PublicDiscoveryTest.php
```

Use the existing local `.env` examples/start instructions for normal applications. The isolated audit fixture/router are under the Codex workspace `work/seo-audit-20261105/`, not the project or a deployable database. Never upload that fixture, its local API build or real `.env` files. The local audit script is `work/deployment-access/verify-local-customer-seo.py`; its Host override intentionally checks production metadata against localhost, not live deployment success.

## Deployment steps — only after explicit approval

**No commands in this section have been executed against Hostinger in this audit.** Do not run historical deployment scripts verbatim: their release names and baseline hashes describe already-deployed versions.

1. Review the scoped changes against the current active release; back up API code, current Passenger link/.htaccess/runtime configuration and managed last-source. Preserve database/media/order data. No migration, seed, import, repricing or publication command is required.
2. Deploy `backend/app/Catalogue/CatalogueData.php` to the existing Laravel release first (currently `/home/u688505577/elvarqo/releases/20261101-stage01/backend`); validate syntax with `/opt/alt/php85/usr/bin/php -l app/Catalogue/CatalogueData.php` and verify public product JSON contains category parent_id. Do not overwrite its `.env`, media/public image robots exception, web bootstrap or private API exclusions. Admin needs no deployment.
3. Rebuild the **complete** Node storefront from reviewed current managed source plus the files listed below. Keep Hostinger's existing compatible `next.config.mjs`/webpack+WASM build settings, standalone output, Node24, sync-ui handling and `production-indexing.cjs` startup wrapper. Do not upload the Windows localhost `.next-seo-audit` build or replace Hostinger configuration with the plain local TypeScript config. Follow the existing immutable-release/rollback workflow in `docs/PRODUCT-STRUCTURED-DATA.md` with fresh release/backup names and updated baseline/archive hashes.
4. In that isolated Hostinger build source, retain these exact production build/runtime values:

```sh
export PATH="/opt/alt/alt-nodejs24/root/bin:$PATH"
export NEXT_PUBLIC_API_URL=https://api.elvarqo.uk/api/v1
export CATALOGUE_API_URL=https://api.elvarqo.uk/api/v1
export COMMERCE_API_URL=https://api.elvarqo.uk/api/v1
export NEXT_PUBLIC_SITE_URL=https://elvarqo.uk
export STOREFRONT_ORIGIN=https://elvarqo.uk
export STOREFRONT_INDEXING=true
export NEXT_TELEMETRY_DISABLED=1
npm ci --include=dev --no-audit --no-fund
npm run build
test -f .next/standalone/server.js
```

5. Install standalone server plus `.next/static` and `public` together in the fresh version, preserve old chunks for open sessions, atomically switch the existing Passenger current link/AppRoot, keep `production-indexing.cjs`, gracefully restart only the old owned storefront process. Update managed last-source only after acceptance. Preserve staging/private noindex and all existing environment secrets; no DNS change.
6. Recheck normal URLs and cache-busting URLs separately. If ordinary HTML is stale, clear the storefront Hostinger/CDN cache and recheck the ordinary URL. Check HTML **and** X-Robots-Tag, not only status. Verify homepage remains indexable; category new H1/introduction and six anchors; six products four breadcrumbs and honest detail fallbacks; XML sitemap 200/application/xml and11 matching canonical URLs; robots reference; empty/private/filtered routes still noindex; unknown product404. Confirm public images remain200/indexable while API JSON stays excluded. Retest colour/size→bag→quote on desktop/mobile; never place a real order for a deployment smoke test without approval.
7. Run Google's live URL Rich Results Test on all six after deployment and Search Console live URL inspection for homepage/category. Record new timestamped live results separately. Do not label this audit's local checks as Google's validation. Roll back **code/config only** if acceptance fails, never restore an older catalogue/order database.

Storefront runtime files to include:

```text
src/lib/collection-content.ts (new)
src/lib/product-structured-data.ts
src/app/collections/[collection]/page.tsx
src/app/products/[product]/page.tsx
src/app/[slug]/page.tsx
src/components/shop/product-selection.tsx
src/components/shop/product-image.tsx
src/components/checkout/bag.tsx
src/app/globals.css
```

Retain package.json/new tests, backend test and .gitignore/.prettierignore in source control for reproducibility; these are not separate runtime deployments. The deployment manifest/package under `deploy/hostinger/customer-seo/` contains only this audit's scoped source and no credentials. A full build is still required; it is not an independently runnable release.

## Highest-priority next actions

1. Confirm each supplier/product/photo match and rights, exact neckline names (especially Roll Neck and High Neck), available colours/sizes and fulfilment/stock procedure. Link confirmed photos to variants; do not guess.
2. Approve actual delivery charges, dispatch/arrival estimates, returns/exchanges, customer contact and merchant/legal/privacy information. Replace test assumptions/previews only after approval; finish Wise receiving/reconciliation and real payment acceptance testing separately.
3. Obtain verified fabric composition, care, size charts and fit information; review the photo-backed copy/alt proposals and select a full-garment Crew Neck lead image.
4. Approve/deploy this scoped source release, then perform the live checks above. Expand/index empty departments only when useful, real content/products are ready. Monitor Search Console indexing, merchant enhancements and actual performance after deployment; no first-place ranking promise.
