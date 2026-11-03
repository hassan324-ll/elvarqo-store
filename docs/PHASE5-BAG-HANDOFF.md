# Shopping selection → Phase 6 bag handoff

Phase 5 defines `BagHandoffV1` in storefront/src/lib/catalogue-types.ts:

```typescript
type BagHandoffV1 = {
  version: 1;
  product_id: number;
  variant_id: number;
  quantity: 1;
  currency: 'GBP';
};
```

ProductSelection creates this in memory only after an explicit colour/size choice and a fresh published-product read confirming that variant remains active and available. The accompanying view model contains the current server price for display; price is deliberately absent from the trusted handoff. Review selection opens an honest summary. It does not send a bag mutation, persist a bag, create an order, reserve stock or claim success adding an item. The header bag count remains zero.

Phase 6 must replace the informational action with a validated bag command and authoritative response. Treat every browser field as untrusted. Resolve the product/variant relationship, current publication/active state, market/currency, allowed quantity, current price, applicable tax/discount/shipping and available stock on the server. Never accept a client-submitted price or infer a reservation from a displayed availability check. Return corrected price/quantity or explicit unavailable errors and announce the real result.

Guest wishlist format is separate: localStorage key `elvarqo.wishlist.v1` stores `{version:1,items:[{product_id,variant_id|null}]}` with at most 50 unique product entries. There are no cached price/name/photo snapshots or authentication tokens. Current published resources are fetched through bounded stable-ID GET catalogue/saved-products reads. A saved variant choice may become unavailable; it must not be silently substituted in an order. Customer sync waits until Phase 8.

Phase 6 must implement its own idempotent bag/checkout commands, server totals, atomic expiring stock reservations and immutable order item/address/price snapshots. Reuse Phase 4 retained catalogue IDs/stock balances and explicit ledger operations; do not change reserved quantities from the Phase 5 browser. Payment/provider, tax and delivery choices still require merchant configuration before transactions. None of those systems is started here.
