# Phase 4 API contract

All paths below are prefixed `/api/v1`. JSON retains the Phase 1 data/error/meta envelope. Validation is 422 with error.details field messages; stale versions and unique-key races are 409. Staff endpoints use the existing MFA session, exact origin and explicit CSRF token for POST. Public reads never use credentials. No customer write/checkout endpoints are added.

| Public GET | Behavior |
| --- | --- |
| catalogue/products | Paginated published, current products with active variants. page>=1, per_page 1–50 (20 default), optional active collection slug. |
| catalogue/products/{slug} | Published canonical product. Old registered slug returns 301 + Location + canonical_slug/redirect_url; target must currently be published. |
| catalogue/collections | Active collections/categories in navigation position order. |
| catalogue/media/{uuid}/{640,1280,1920} | Re-encoded WebP only if parent is currently published, media current and optional linked variant active/current. No-store; original never public. |

| Staff endpoint after staff/catalogue/ | Permission / behavior |
| --- | --- |
| GET products, products/{id} | catalogue.view. Search q, status draft/published/archived, page. Returns saved editor data with version. |
| POST products; POST products/{id} | catalogue.manage. Create/save private draft. Existing product requires current version. |
| POST products/{id}/publish, /unpublish, /archive | catalogue.manage. Current version; archive also requires confirmation=ARCHIVE. Published editing is locked until explicit unpublish. |
| GET collections; POST collections; POST collections/{id} | catalogue.view / catalogue.manage. kind collection/category, active, navigation position, version on edit. |
| GET collections/{id}/products; POST collections/{id}/order | Read / manage. Order requires current collection version and every assigned product_id exactly once. |
| POST products/{id}/media | Manage, multipart file/alt/optional variant_id/version. Actual decode and metadata validation, private original + responsive WebP. |
| POST products/{id}/media-order | Manage. version plus items [{id,alt,variant_id}], all current media exactly once in desired order. |
| POST products/{id}/media/{uuid}/archive | Manage, version + confirmation=ARCHIVE. Retain files and record. |
| GET media/{uuid}/{size} | Read, private staff derivative preview; not original downloads. |
| GET locations; POST locations | Read / manage. Create a real warehouse with unique uppercase code. |
| GET inventory | Read. q, optional location_id, page; active current variants with per-location on_hand/reserved/available. |
| GET movements | Read. optional variant_id/location_id, page; latest 30 immutable movement rows. |
| POST adjustments | Manage. operation_id UUID, variant_id, location_id, nonzero signed delta, reason, optional reference/note. |
| POST transfers | Manage. operation_id UUID, variant_id, source location_id, different to_location_id, positive quantity, reason, optional reference/note. |

Create/save fields: title, slug, product_type, description/material/fit/care, seo_title <=70 and seo_description <=160, measurements [{size,chest_cm,length_cm,shoulder_cm}], collection_ids and variants. Variants contain optional existing id, uppercase sku, optional unique barcode, colour/size, integer price_minor, optional compare_at_minor, currency=GBP, optional weight_grams, low_stock_threshold, active, optional measurement/fit/care overrides. Arrays must be present; draft arrays may be empty. Variant combinations and supplied IDs must belong to the product. Omitted variants are archived rather than deleted; inactive variants can remain in the form for temporary deactivation. Archived variants retain IDs and unique identifiers; restoring one through the API requires its original ID, not a new replacement.

Publication requires verified base product fields, at least one active collection, positive prices on active variants, complete measurements for each active size (base row plus optional variant overrides) and image coverage per active variant. Zero inventory does not prevent publication; public availability remains zero. There are no fabricated stock quantities.

Inventory movement transactions lock the variant and locations/balances, preserve reserved <= on_hand and cap on_hand at one billion. adjustment absolute delta/transfer quantity <= one million. Server writes resulting balances, signed delta, actor ID, timestamp, reason, reference/note and operation UUID. The operation fingerprint includes actor and exact normalized request. Identical retry returns 200/replayed=true without a second movement; changed payload on the same key is 422. New movement returns 201. Clients cannot write reserved or on_hand. Transfers create two rows atomically; a failed leg rolls back the whole operation. DB triggers reject ledger updates/deletes on verified SQLite.

Retention: product archival uses soft deletion/status; variants/media use archived_at. Foreign keys restrict hard deletion of referenced parents; original files remain private. Slugs are never recycled. Future Phase 6 order items must store immutable price/content snapshots and retained product/variant/media identifiers; retained catalogue records alone must not be used as a live reconstruction of historical order prices. There is no data purge policy or order system in this phase.

Media delivery intentionally uses no-store so unpublication takes effect without a browser/CDN cache continuing to serve a formerly published image. Deployments must preserve this authorization behavior. Files are never served through a public storage symlink. Sharp processes argument-array local paths under a 40-second timeout with a 24MP limit; the browser upload timeout is 60 seconds. No arbitrary remote URL fetching or SVG rendering.

References used for implementation: [Laravel validation](https://laravel.com/framework/docs/13.x/validation) and [Sharp output options](https://sharp.pixelplumbing.com/api-output/). The worker re-encodes public derivatives without retaining source metadata; originals remain private for historical retention.
