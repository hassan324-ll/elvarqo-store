# Collection → Category → Products

## Create a collection

Open Products > Collections & categories. Choose Collection as the kind, enter its title and unique slug, choose navigation position and save. Enable Active in public catalogue when you want it public. Only active top-level collections appear in the storefront menu.

## Add categories inside it

Use Add category on the collection row or Add category to this collection in its editor. The Parent collection is filled in. Enter the category title and unique slug, set its visibility/order, then Save category. A category belongs to one collection; category nesting below another category is not allowed. Slugs are unique across the site, so use mens-t-shirts and womens-t-shirts for similarly named categories in different collections.

For categories created before this change, click their pencil icon, select Parent collection and Save category. They are retained as unassigned until you make that choice; their existing product IDs and assignments are preserved. No parent is guessed automatically.

## Add products inside a category

Edit the category and click Add products to this category. Its category is selected in the product form. You can also use Add product and select the collection/category path in Product categories.

Enter the title, description, verified material/fit/care, variants/prices and real garment measurements. A new product slug is suggested until manually edited. Blank SEO text derives from title/description. Choose one or multiple photos in Product images before saving, review their descriptions, then Save draft once. The form saves the product and uploads photos sequentially. Save & add another keeps your category selection for the next product.

JPEG, PNG and WebP only; still images at least 200px per side, maximum 8MB / 24MP each and 30 photos in total. The first saved photo is primary. Saved photos can be reordered, linked to variants or archived. On partial failure, the draft and confirmed uploads remain saved while remaining files stay selected in the open tab; fix/remove the failure and retry. Conflict/uncertain responses require review of the latest saved record. Reloading/discarding loses unsaved local file selections and prompts for confirmation.

Add actual stock in Inventory. Preview and explicitly publish when the product is complete. Published content must be unpublished before editing. Existing direct collection/product assignments remain retained for compatibility; new editor choices are categories.

## Menu and browsing

The storefront top row shows collections only. Use the arrow beside a collection to open its active categories. Collection pages aggregate published eligible products from their active categories; category pages show that category's products. Inactive parents hide their categories from public navigation and category routes. Refresh the storefront after changing groups.

Admin All products includes all statuses. Collection filters include child category memberships without double-counting. IDs remain permanent; each page has at most 10 records. A red bin archives after confirmation and retains historical data.

## Admin collection filters

Use All products in the top filter row for the complete list. Click a collection name to reveal its categories underneath. All in [collection] includes its category products; a category button narrows the list. Switching collections clears the previous category and resets page/search/status. Manage collections opens group editing separately. IDs and ten records per page are retained.


## Quick category creation from filters

Click a collection in the catalogue's top filter row, then Add category beside its category heading. The dialog fixes that collection as the parent. Enter the category details and Save category; the new category appears in the filter row immediately. Cancel leaves the catalogue unchanged. Manage collections remains available for editing or moving existing categories.


## Collection view (current behaviour)

Selecting a collection in admin opens only its categories, with Add category available. Click a category card or category filter to view its products. All products returns to the complete product table. Collections no longer show an aggregate product table in this admin view; storefront collection aggregation remains unchanged.


## Editing and deleting groups

Use the pencil and bin beside a selected collection, on category cards, or in Manage collections. Pencil changes group details or moves a category to another collection. Bin opens confirmation and archives the group, preserving products, images and historical assignments. Move or delete the child categories before deleting a collection. Archived groups disappear from navigation and cannot accept new product assignments; products remain in All products and can be assigned to another category. No restore UI is included.


## Permanently deleting unused products

The product-row bin now permanently deletes unused draft/archived products after explicit confirmation, including their variants and image files. Products with orders, reservations, stock history/nonzero stock or return references must retain their history; use Archive in the editor instead. Unpublish a live product before permanent deletion. All products/list counts refresh after deletion. Collection/category bins continue to archive their groups while preserving products. Product IDs are not renumbered after deletion.

## Category images

Select a collection in Products, then use Add category or the pencil on an existing category. In Category image, choose a JPEG, PNG or WebP file and enter descriptive alternative text. Save category / Save changes saves the image with the category details. The same image control is available when creating or editing a category in Manage collections.

Use Replace or Remove image to change it, then save. Category images must be still, at least 64px on each side (400px or larger recommended), no more than 8MB or 24MP. The system retains an optimized 640px WebP thumbnail. The original upload is not retained. Image selection remains local until the category is saved.

Saved images appear on admin category cards and as circular links under Shop by category on the storefront collection page. Public thumbnails require both the category and its parent collection to be active. Refresh the storefront to see a saved update. Categories without images use a text fallback. No reference screenshot images are supplied automatically.

## Image dropdown in the storefront menu

Hover Women, Men or another collection with active categories to open its category dropdown. Each category displays the image saved in admin and links to its products. Categories without images show a letter placeholder. Shop all opens the collection page. Click or tap a collection name to open its collection page. Desktop hover shows its category dropdown. Keyboard users can press ArrowDown to open the categories, Escape to return focus, or Enter to open the collection page. Collection menu arrows have been removed. Only active categories under active collections are public.

## Women landing page

The Women collection now has an editorial landing page. Add a product to a Women category, complete its photos/details/variants and explicitly publish it; the page includes it automatically. New arrivals uses the latest published pieces. Trending now uses recent paid purchases and falls back to newest releases while sales history is empty. Visible pages refresh every minute; reload for immediate updates. Category circles and editorial category tiles use the images/order/descriptions saved in admin. See WOMEN-COLLECTION.md for full ranking rules, design asset details and testing steps.

## Men landing page

Click Men in the storefront menu to open its editorial landing page. Publish a completed product under a Men category to populate Trending now, New arrivals and the full product catalogue automatically. Admin category images drive the category circles and category editorial tiles. See MEN-COLLECTION.md for section selection rules and campaign details. The page refreshes every minute while visible and on tab return.

## Boys, Girls, Baby and Beauty landing pages

These four collection pages now use the same editorial layout as Women and Men, with their own campaign banners. Publish products under the corresponding categories to populate Trending now, New arrivals and the full catalogue. Active category names/photos/descriptions/order update from admin. Pages refresh every 60 seconds while visible and on tab return. COLLECTION-LANDING-PAGES.md records exact section-selection rules and local review steps.
