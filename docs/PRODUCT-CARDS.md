# Storefront product cards

## Current compact layout — 4 November 2026

The merchant subsequently requested shorter cards like the supplied marketplace reference. Cards now show a square image, the existing heart button, a single-line ellipsised title and the actual price (plus genuine struck-through regular price on sale). Category labels, colour swatches, colour/size counts, stock footer and Quick view were removed from listing cards. Clicking the image/title opens the unchanged full product detail page, where colours, sizes, availability, photographs and any supplied specifications remain available. Full title text remains in the link and title attribute. A Sold out badge is retained only for genuinely unavailable choices. Typical mobile layouts remain two columns. No product, media file/order, price, availability or wishlist data changes are involved.

The design description below records the earlier expanded version and is superseded by this compact layout where it differs.

The shared live product grid is used by the homepage, shop/search listings, collection pages and saved products. Its refreshed cards use consistent 4:5 photographic frames, a compact product type, a readable title, aligned price information, up to four colour swatch links plus a remaining-count link, a subtle availability/size footer, a heart save control and a labelled Quick view action. The original product image files and saved media order are unchanged. Card frames use cover cropping for a consistent grid; full product-page galleries retain their original contain presentation.

Colour swatches are decorative approximations of supplier/admin colour names, not newly verified image shades. Unknown names receive a patterned neutral swatch. Every swatch has a textual tooltip and accessible name and links to the actual product/variant ID. No image-to-colour match is invented and no colour variant or product is created by browsing the cards.

The price is computed from the matching variants. A uniform price is displayed directly; From appears only when their effective prices differ. A sale badge and struck-through regular price appear only when the lowest displayed variant has a genuine higher compare-at price. No fake reviews, discount percentages, sales counts or badges are added. Existing Always available status is respected.

Quick view still opens the existing size/colour selection dialog and obtains current availability. It does not add an unspecified variant to the bag. The optional icon rendering of SavePiece preserves its existing wishlist state and behaviour everywhere else. Labelled controls, visible keyboard focus, touch-visible Quick view and reduced-motion rules are included. Cards use two columns on typical mobile widths and one below 360px.

Verification commands in storefront (Node 24 with locked installed dependencies):

```powershell
npm run test:cards
npm run format:check
npm run lint
npm run typecheck
```

Five tests cover uniform/variable prices, the lowest variant's actual discount, absence of misleading sale claims, empty prices and safe named-colour fallback. The production build is tested in an isolated Hostinger-compatible webpack/WASM environment before activation. Browser visual and interactive acceptance remains manual when computer use is unavailable; public rendered markup, variant links, current availability and required CSS/JS assets are checked separately.
