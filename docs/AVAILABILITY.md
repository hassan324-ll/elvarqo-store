# Always available stock mode

The merchant explicitly chose to show active products in stock and permit orders without recorded quantities. `CATALOGUE_TRACK_STOCK=false` is now the default, applied to existing and future variants. This is one catalogue-wide mode, not supplier inventory synchronisation.

- Published, active, non-archived variants are offered regardless of recorded warehouse balance. Draft, deleted, archived and inactive products/variants remain unavailable to customers.
- Bag/checkout still validate server prices, variants, currency, quantity limits, quote hashes, provider readiness and ownership. Quantity limits (10 per variant and 30 different variants per bag) remain. This mode does not enable Wise receiving details or paid checkout by itself.
- No fake quantities, stock locations, supplier stock or inventory movements are created. API `available` remains the actual recorded quantity; `stock_tracked=false` and `is_in_stock=true` express availability separately. UI uses the boolean status, not a manufactured stock number.
- New order snapshots record their server-controlled stock mode. Untracked orders do not reserve/decrement stock. Settlement permits no reservations only for a nonempty immutable snapshot whose lines explicitly say untracked. Legacy snapshots without the field remain tracked; previously reserved orders still commit/release their existing quantities after a mode change.
- Dispatch uses the immutable mode to skip the committed-stock requirement for untracked order lines, while still requiring a real active dispatch location, confirmed payment, a valid parcel and quantities no greater than unallocated ordered units. Dispatch and return operations remain manual/sandbox according to the existing provider configuration. Physically received, inspected resellable returns may still be recorded in the existing warehouse ledger; these counts do not constrain new orders while mode is untracked.
- The admin catalogue, preview, inventory and overview show In stock / Not counted. Existing ledger quantities are retained for reference. Stock movements remain available to authorised staff, but are not required to offer a product. To stop offering a product, unpublish it or deactivate its variant through the existing workflow.

## Restore quantity tracking

Set `CATALOGUE_TRACK_STOCK=true` in the private backend environment and run `php artisan config:cache` with the correct production PHP binary. Record accurate per-location quantities before switching. Existing orders retain their recorded mode; current bags must obtain a fresh quote when the mode changes. Do not modify immutable order snapshots or add made-up quantities. Keep configuration changes consistent across application instances.

## Verification

Both PHPUnit profiles explicitly retain tracked mode for regression fixtures; new tests opt into untracked mode. Tests cover zero/no-balance purchase and settlement, repeated provider evidence, expiry, inactive and unpublished blocking, in-stock discovery, mode changes/quote invalidation, Wise confirmation, dispatch allocation and preservation of existing reservations. The full in-memory database suite never uses the business database or real provider credentials. Admin checks include its 16 existing variant/pricing tests. Storefront lint/typecheck and an isolated Hostinger production build verify client consumers of the new status field.
