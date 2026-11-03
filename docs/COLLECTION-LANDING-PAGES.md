# Boys, Girls, Baby and Beauty landing pages

The following pages use the same editorial design system as the Women and Men landing pages:

- http://localhost:3000/collections/boys
- http://localhost:3000/collections/girls
- http://localhost:3000/collections/baby
- http://localhost:3000/collections/beauty

Each has its own campaign hero, circular category navigation, olive ribbon, Trending now, category edit, finishing/gifting selection, campaign story, New arrivals and the complete filtered/paginated catalogue. Desktop, tablet and mobile layouts share the existing editorial styles. Existing header/footer, arrow-free menu links, newsletter preview, Women and Men designs remain in use.

## Content selection

The active categories, order, photos, descriptions and links come from admin. The category edit uses the first six categories in navigation order. The finishing/gifting section selects existing active child categories:

| Collection | Selected category slugs |
| --- | --- |
| Boys | boys-boots, boys-trainers, boys-pyjamas |
| Girls | girls-leggings, girls-boots, girls-pyjamas |
| Baby | baby-bodysuits, baby-first-shoes, baby-socks-tights |
| Beauty | beauty-fragrance-gifts, beauty-skincare-gifts, beauty-boxes |

Removed or inactive categories are omitted. An active category without an image has a text placeholder; no product photos are invented.

Trending now shows up to three published products using the existing last-30-day confirmed paid GBP order quantities. Newest publication date and product ID provide a stable fallback/tie-breaker. New arrivals shows the four newest published products. Both are scoped to the current collection and its visible child categories. Drafts, archives and unrelated collection products remain hidden. Prices, stock and product images are live catalogue data. Empty product sections are honest empty states, never seeded mockup products. Failed requests expose recovery controls.

The page reads without server caching and refreshes every 60 seconds while visible, as well as when returning to the tab. Publish a completed product assigned to a child category to populate the page automatically; saving a draft does not publish it. Reload for an immediate update. The full catalogue retains filters, sorting, paging, quick selection and wishlist.

Campaign copy and imagery are fixed design content. This work does not introduce banner CMS, live newsletter or Stripe configuration. New designs are selected for the existing collection slugs; changing a collection slug requires updating the campaign configuration. Category routes retain the normal listing layout.

## Generated campaign assets

Created through the built-in image generation tool for ELVARQO, without copying third-party campaign photos. Hero text/buttons are accessible HTML. Each image is also reused as a crop in its campaign story panel.

- storefront/public/campaigns/boys/autumn-hero.png — 2020 x 778
- storefront/public/campaigns/girls/autumn-hero.png — 2020 x 778
- storefront/public/campaigns/baby/autumn-hero.png — 2020 x 778
- storefront/public/campaigns/beauty/autumn-hero.png — 2017 x 780

The exact generation prompts are recorded in COLLECTION-CAMPAIGN-PROMPTS.json. Hero dimensions, section copy and selection rules are configured in storefront/src/lib/collection-campaigns.ts; storefront/src/components/shop/collection-landing.tsx renders the shared dynamic layout.

## Local review

1. Start backend on port 8000 and storefront on port 3000 using the README commands.
2. Click Boys, Girls, Baby or Beauty in the top menu; hover still opens its category dropdown.
3. Review the banner and category circles on desktop and phone widths. Click a category to open its product listing.
4. In admin, add/complete a product under a category in the selected collection; upload photos, configure variants/prices and inventory, then explicitly publish.
5. Reload the collection or wait up to 60 seconds with the tab visible. New arrivals and the full catalogue include the published product; Trending uses newest releases until paid sales history exists.
6. Unpublish it and confirm it disappears. Changes in one collection must not populate another collection.
