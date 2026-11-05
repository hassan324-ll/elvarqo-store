# Quick product variants

Open a draft in Products. Upload the product photos in the same form, or use its existing imported photos. In **Variants, SKU & pricing → Quick variant builder**:

1. Click **Suggest colours from photos**. The browser reads supported JPEG/PNG/WebP photos using the existing staff session, samples their centre torso region, and proposes broad colour names. No external AI service or API key is involved and source photos are unchanged.
2. Review each photo. Correct the name or exclude lifestyle/detail photos where the garment colour is not represented. Repeated names are combined case-insensitively. Add supplier-specific names in **Additional colours** when needed. You can also use this field without analysis.
3. Select supplier-confirmed sizes from the size dropdown. Each selection adds a removable chip; none are selected automatically. Standard clothing, UK clothing and baby/child examples are suggestions only. You can also enter custom sizes, comma-separated. Do not assume standard sizes or one size from photographs. Enter one GBP selling price; the number field's arrows adjust it by £0.01.
4. Confirm the selected colours and sizes, then click **Generate variants**. This adds missing colour/size combinations to the form with unique local SKUs. Existing combinations, supplier records, stock and prices are not overwritten. Up to 100 variants are supported, including existing rows.
5. Expand a compact variant row to change its regular price, optional sale price, SKU or optional details. **Apply prices to all variants** is a separate explicit confirmation; it applies both the regular price and the optional sale price in the form. Leaving sale empty in this bulk action removes existing sales after confirmation. Save draft to persist.
6. Click **Save draft**. Existing backend validation, version checks, permissions and audit logging apply. New variants do not manufacture stock, supplier IDs, dimensions, composition or delivery information. After saving, saved photos can be linked to individual variants with the existing image controls.

Adding or removing photos resets the helper's review/input state so stale suggestions cannot be confirmed against a different photo list. Generated variants remain in the product form. Saving or publishing is always separate from analysis and generation. Publication rules and inventory management remain unchanged. Only drafts can be edited.

The category selector now has search and a bounded scroll area; variant details are expandable to keep large products manageable. Actual saved category assignments are unchanged until the admin edits and saves them.

## Verification

From the admin directory, using Node 24 and existing installed dependencies:

```powershell
npm ci
npm run test:variants
npm run check
```

Sixteen automated tests cover colour families, centre sampling/transparent images, warm neutrals, input normalisation, explicit size selection/custom-label preservation, decimal price limits, sale validation and regular/effective-price mapping, sale removal, explicit variant combinations, unique/colliding SKUs, duplicate re-generation, preservation of existing data and the 100-variant limit. `check` includes these tests plus formatting, lint, TypeScript and Vite production build.

The Add product and existing product editor now open in a native floating modal dialog over the catalogue. Its internal body scrolls independently, focus stays within the modal, Escape/Close use the editor's unsaved-change guard, and closing restores focus to the triggering control. Closing is blocked while a save/upload is running. Field labels include decorative semantic icons while preserving real text labels; prices remain exact decimal input strings for existing server-side money validation. No backend migration is required.

## Regular and sale prices

Enter the usual price in **Regular price GBP** and a lower positive amount in **Sale price GBP (optional)**. For example, regular 30.00 and sale 24.99 save `price_minor=2499` and `compare_at_minor=3000` using the existing API. The product page already renders the effective price and a struck-through regular price for a selected discounted variant. Saved discounts are decoded into the same regular/sale fields without changing their meaning. Clearing sale and saving restores the regular price and clears compare-at. Zero prices remain allowed for incomplete drafts, but positive publication requirements remain in force; a zero sale is not allowed. New variants can receive both prices from the quick builder. Generating variants and changing bulk form prices do not persist until Save draft. This update adds no sale scheduling or automatic publication.

No backend migration or external image-processing service is needed. Photo analysis is an approximate colour sampler, not garment segmentation or supplier verification; patterned clothing, background objects, lighting and crops may produce incorrect suggestions. If a photo cannot be read because of decoding, browser support or an expired staff session, it remains editable and unselected rather than inventing an option. Browser visual/authenticated acceptance must still be checked in the signed-in admin.


Untouched new manual variant rows are ignored during save, generation and bulk pricing. Partially completed or saved rows are preserved and must be completed or explicitly removed. Generate variants before saving quick-builder selections; selecting sizes/colours or entering prices alone does not create rows.


Measurement rule update (4 November 2026): garment measurements are optional for publication. Leave the section empty when unknown; if entering rows or overrides, existing numeric/range validation still applies. Empty size-guide controls are hidden for the selected colour when no measurements are supplied. Existing verified measurements are retained. Titles, descriptions, SEO, active category/variants, positive selling prices and images remain required. No products are automatically published.


Stock policy update: variants now use Always available by default. No stock quantity is required for new orders; see AVAILABILITY.md. Existing recorded counts are retained, but do not control sale availability unless CATALOGUE_TRACK_STOCK=true is configured. Publication and payment-provider readiness are still separate requirements.
