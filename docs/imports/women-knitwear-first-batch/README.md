# First knitwear batch — preview before importing

Upload products.json and women-knitwear-images.zip in Admin → Products → Bulk Import. Review every provisional link/image match and resolved group, then explicitly confirm before importing drafts. This package is prepared only; it has not been imported into the real local or hosted store.

Open PREVIEW.html in a browser to see all source photographs and exclusions. Keep the full folder together. source-archives contains byte-for-byte copies of the six supplied ZIPs; the Downloads originals are unchanged. webp-images contains lossless converted copies at the original dimensions. The import ZIP includes 67 eligible photographs; no new garment details, colours or variants were generated. Source-to-WebP hashes and exclusions are in source-manifest.json.

All six AliExpress ID/link pairings remain provisional. Verify each live listing independently. Six V-neck photographs in images (1).zip (3, 4, 8, 11, 12, 15) were excluded from the crew-neck draft because they visibly differ. The proposed roll-neck name needs confirmation: the supplied photographs establish a raised neckline, not a clearly folded roll neck. Copy describes visible details only and does not claim cashmere, wool, UK manufacture, reviews, softness or delivery times.

The local database resolved Women and its Knitwear category; resolved-groups.json records the read-only lookup. The importer itself resolves slugs and parent-child membership afresh on the destination database, so a different ID assignment is supported.

Before publication obtain: verified supplier-to-photo matches and image rights; actual supplier variant IDs and available colour/size combinations; fibre composition; fit and care instructions; garment measurements for every offered size; real GBP selling prices and unique SKUs; known stock or approved availability handling; supplier costs/currencies/shipping/date checked; merchant-approved delivery/returns/tax and payment policy. Unknown values are null/empty. No variants or stock are created by this first batch. Existing publication checks still apply. This importer does not place or fulfil orders through AliExpress or DSers.
