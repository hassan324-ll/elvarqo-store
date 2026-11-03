# Women collection landing page

Open http://localhost:3000/collections/women with the backend and storefront running.

The page uses the approved design: a campaign hero, category circles, olive editorial strip, Trending now, Your wardrobe refreshed, The finishing touches, a campaign story, New arrivals and the complete filtered product catalogue. Existing header/footer and newsletter preview remain in use. Other collections and category pages keep their existing catalogue layout.

## Automatic content

- Category names, order, links, visibility, descriptions and photos come from the active categories under Women.
- Trending now shows up to three published Women products, ranked by units in confirmed, paid GBP orders during the previous 30 days. Pending/cancelled/old orders do not contribute. With no paid sales, newest first-publication date supplies the order; product ID breaks ties. Order details and ranking scores are not exposed publicly.
- New arrivals shows the four newest published Women products.
- The complete product list retains prices, stock, filters, paging, wishlist and quick selection. Products may belong directly to Women or an active child category; inactive groups/drafts/archives remain hidden through existing public discovery rules.
- Your wardrobe refreshed uses the first six active categories in admin navigation order. The finishing touches uses active Women categories for Boots, Lingerie and Pyjamas when present. Category images/descriptions update from admin.
- Data is read without a server cache. While the page is visible, it refreshes every 60 seconds; returning to the tab also refreshes it. Publishing, unpublishing, price/stock and category edits therefore need no manual homepage assignment. A page refresh shows changes immediately.
- Empty product sections show empty states, never demo products. API failures show recovery controls. Removing all categories does not invent replacement categories.

## Add a product

1. Admin → Products → Women → choose a category → Add product.
2. Enter details, variants, GBP prices and photos; save the draft.
3. Add actual stock in Inventory and explicitly publish the completed product.
4. Open /collections/women. The published product appears automatically. Until sales history exists, Trending now uses recent releases.

The campaign banner is a fixed design asset, not a product recommendation or a representation that its pictured clothing is available. No new banner CMS, newsletter service or payment integration was introduced. The newsletter remains the existing explicitly labelled preview.

## Campaign asset

Saved asset: storefront/public/campaigns/women/autumn-hero.png (2022 x 778). Generated using the built-in image generation tool with the approved mockup as a reference, not third-party reference photography. Reused with a different crop in the campaign story section. The hero heading and buttons are accessible HTML rather than text baked into the image.

Generation prompt: Use case: precise-object-edit. Supporting reference is the approved ELVARQO web mockup. Output ONLY the clean wide editorial HERO PHOTOGRAPH from the top of this mockup, recreated at high photographic quality for its real website banner. Two adult women sitting together at warm pale stone steps in soft autumn daylight. Left brunette woman with long wavy dark brown hair, ivory textured chunky knit jumper and deep charcoal coat; right blonde adult woman in olive-brown coat and dark knit, on the right side of frame, faces and clothing exactly matching the approved reference hero's look and relaxed thoughtful poses. Warm stone architecture behind, subtle greenery left. Preserve the composition: subjects center-right, left third darker low-detail photographic negative space for HTML white headline overlay. WIDE landscape aspect ratio approximately 2.6:1. Full bleed realistic luxury British fashion editorial photography. Remove ALL text, navigation, logo, buttons, mockup elements and every other section. No text, no collage, no borders, no watermark. This is an image asset for the approved ELVARQO Women landing page, not a new page mockup.
