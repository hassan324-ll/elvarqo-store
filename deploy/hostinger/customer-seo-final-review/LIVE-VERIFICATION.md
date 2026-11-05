# Live acceptance checklist — run only AFTER an approved deployment

Nothing here is marked complete. Local validation and historical Google results are not live validation of this release. Record UTC timestamp, release hash, URL, status/headers, HTML evidence and reviewer.

- [ ] Ordinary https://elvarqo.uk/ returns200, exactly one intended canonical, no robots meta/header noindex. Compare fresh query and normal URL; check HCDN cache headers and purge only if stale, then recheck ordinary URL.
- [ ] /collections/womens-knitwear returns200; H1 Women's knitwear; final introduction/meta/title exactly match COPY-BEFORE-AFTER.md; canonical unchanged; six published crawlable product links and correct Shop→Women→Knitwear navigation.
- [ ] All six product URLs return200 with one H1/canonical and initial HTML ProductGroup/Product/Offer + BreadcrumbList. Existing catalogue titles/descriptions/alt stay unchanged unless separately approved; review proposals must NOT appear from a hidden runtime override.
- [ ] No internal “shared across listed colours”, supplier research or provisional-match warning is displayed. Missing fields show only concise final fallbacks. Valid supplied details remain visible. No fabricated measurements/care/composition.
- [ ] Visible product breadcrumb and JSON-LD both include Shop, Women, actual Knitwear and product. Public API category parent_id corresponds to actual parent. No guessed IDs.
- [ ] Read current catalogue variants, not a stale audit price list. Every published active offer's GBP selling price, SKU, URL and availability match current visible variant and backend quote. Do not change prices for testing. Always available remains the existing ordering rule, not a claim of supplier stock.
- [ ] Direct ?variant= URLs select the expected colour/size, price and availability; canonical excludes the selection parameter. Test first/last variant and representative sold-out behaviour where real data supports it; do not fabricate stock.
- [ ] Gallery/photos load200, responsive sizes/srcset and genuine alt text present; unknown colour-photo mappings not auto-created. Source images unchanged. Supplier mapping/colour assumptions remain review-only.
- [ ] /sitemap.xml GET and HEAD return200/application/xml; valid escaped XML, current published canonical products/useful categories/content only, accurate lastmod where supplied; no React HTML fallback. robots.txt references https://elvarqo.uk/sitemap.xml.
- [ ] Empty categories, filtered/search/preview, account/bag/checkout, admin/staging and drafts retain their intended exclusions. Deliberate missing product/page returns404, no Product markup. Do not blanket enable indexing.
- [ ] API private/JSON routes retain noindex and authorisation; public product image exception remains accessible/indexable; original API and shopping responses unchanged.
- [ ] Representative desktop and390px mobile: category→product→colour/size→bag→quantity→quote works, no page overflow or obscured purchase controls. Existing payment readiness/test-policy gates remain. Stop before terms acceptance/order/payment unless separately authorised.
- [ ] Google live URL Rich Results Test for each of the six below. Record fresh report links/results; unknown GTIN/brand/reviews/shipping/return enhancements may remain omitted, never fabricated.
- [ ] Search Console live URL inspection for homepage and Knitwear; submit/request indexing only if approved. No ranking guarantee.

| Product | Live URL / fresh Google result / reviewer |
| --- | --- |
| Textured V-Neck | https://elvarqo.uk/products/womens-textured-v-neck-knitted-jumper — pending |
| Crew Neck | https://elvarqo.uk/products/womens-crew-neck-knitted-jumper — pending |
| High Neck (current live title) | https://elvarqo.uk/products/womens-high-neck-knitted-jumper — pending |
| Collared | https://elvarqo.uk/products/womens-collared-knitted-jumper — pending |
| V-Neck | https://elvarqo.uk/products/womens-v-neck-knitted-jumper — pending |
| Roll Neck (current live title) | https://elvarqo.uk/products/womens-roll-neck-knitted-jumper — pending |

Read-only HTTP examples (save bodies/headers privately; no deployment action):

```sh
curl --fail --silent --show-error -D home-headers.txt https://elvarqo.uk/ -o home.html
curl --fail --silent --show-error -D category-headers.txt https://elvarqo.uk/collections/womens-knitwear -o category.html
curl --fail --silent --show-error -D sitemap-headers.txt https://elvarqo.uk/sitemap.xml -o sitemap.xml
curl --fail --silent --show-error https://elvarqo.uk/robots.txt -o robots.txt
```

Check response bodies, actual canonical/meta/header directives, parsed XML/JSON-LD, not only command exit codes. Use the actual new response data to assess each page's eligibility.
