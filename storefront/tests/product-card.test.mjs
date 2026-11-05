import test from 'node:test';
import assert from 'node:assert/strict';
import { cardPrice, colourSwatch } from '../src/lib/product-card.ts';

test('uniform prices display a single exact price without From', () => {
  assert.deepEqual(
    cardPrice([
      { id: 1, price_minor: 2099, compare_at_minor: null },
      { id: 2, price_minor: 2099, compare_at_minor: null },
    ]),
    { amount: 2099, regular: null, from: false },
  );
});
test('varying prices use the lowest actual variant and retain its correct regular price', () => {
  const variants = [
    { id: 1, price_minor: 3000, compare_at_minor: null },
    { id: 2, price_minor: 2499, compare_at_minor: 3500 },
  ];
  assert.deepEqual(cardPrice(variants), {
    amount: 2499,
    regular: 3500,
    from: true,
  });
  assert.equal(variants[0].id, 1);
});
test('a higher-priced discount does not imply the cheapest variant is on sale', () => {
  assert.deepEqual(
    cardPrice([
      { id: 1, price_minor: 2000, compare_at_minor: null },
      { id: 2, price_minor: 2500, compare_at_minor: 3000 },
    ]),
    { amount: 2000, regular: null, from: true },
  );
});
test('no prices or invalid compare-at values do not invent a discount', () => {
  assert.equal(cardPrice([]), null);
  assert.equal(
    cardPrice([{ id: 1, price_minor: 2500, compare_at_minor: 2000 }]).regular,
    null,
  );
});
test('named decorative swatches normalise case and unknown names use a neutral fallback', () => {
  assert.equal(colourSwatch(' Green '), colourSwatch('green'));
  assert.equal(colourSwatch('supplier shade 123'), undefined);
  assert.equal(colourSwatch('background: red'), undefined);
});
