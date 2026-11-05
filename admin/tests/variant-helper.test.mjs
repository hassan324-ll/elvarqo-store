import test from 'node:test';
import assert from 'node:assert/strict';
import { addSize, sizeOptions } from '../src/catalogue/size-options.ts';
import {
  colourName,
  optionList,
  priceMinor,
  suggestColour,
  variantCombinations,
  variantPrices,
  priceFields,
  isUnusedVariant,
} from '../src/catalogue/variant-helper.ts';

test('recognises broad colour families from representative pixels', () => {
  for (const [rgb, expected] of [
    [[20, 20, 20], 'Black'],
    [[240, 240, 240], 'White'],
    [[140, 140, 140], 'Grey'],
    [[220, 210, 185], 'Cream'],
    [[170, 150, 125], 'Beige'],
    [[100, 70, 45], 'Brown'],
    [[180, 40, 45], 'Red'],
    [[210, 150, 160], 'Pink'],
    [[40, 60, 90], 'Navy'],
    [[50, 110, 160], 'Blue'],
    [[90, 120, 80], 'Green'],
    [[120, 80, 160], 'Purple'],
  ])
    assert.equal(colourName(rgb), expected);
});
test('samples centre torso and ignores contrasting photo border', () => {
  const rgba = new Uint8ClampedArray(64 * 64 * 4);
  for (let y = 0; y < 64; y++)
    for (let x = 0; x < 64; x++) {
      const i = (y * 64 + x) * 4;
      rgba.set(
        x >= 20 && x < 44 && y >= 20 && y < 44
          ? [80, 120, 80, 255]
          : [245, 245, 245, 255],
        i,
      );
    }
  assert.equal(suggestColour(rgba, 64, 64)?.name, 'Green');
  assert.equal(suggestColour(new Uint8ClampedArray(64 * 64 * 4), 64, 64), null);
});

test('warm cream under different lighting is not suggested as neutral grey or white', () => {
  assert.equal(colourName([220, 213, 198]), 'Cream');
  assert.equal(colourName([196, 189, 175]), 'Cream');
  assert.equal(colourName([180, 187, 117]), 'Lime');
});
test('size and colour lists are explicit, trimmed and case-insensitively deduplicated', () => {
  assert.deepEqual(optionList(' S, M;L\ns, ,M '), ['S', 'M', 'L']);
  assert.deepEqual(optionList(''), []);
});

test('size menu adds only explicit selections and preserves custom supplier labels', () => {
  assert.equal(addSize('', ''), '');
  assert.equal(addSize('Supplier size 85, M', 'L'), 'Supplier size 85, M, L');
  assert.equal(addSize('Supplier size 85, M', 'm'), 'Supplier size 85, M');
  assert.equal(addSize('', 'One size'), 'One size');
});

test('size suggestions have no preselected defaults or duplicate labels', () => {
  const sizes = sizeOptions.flatMap((group) => group.sizes);
  assert.equal(new Set(sizes).size, sizes.length);
  assert.ok(
    sizes.includes('S') && sizes.includes('UK 8') && sizes.includes('One size'),
  );
  assert.ok(
    sizeOptions.every((group) =>
      Object.keys(group).every((key) => ['label', 'sizes'].includes(key)),
    ),
  );
});
test('price parser validates positive decimal GBP and upper bound without rounding', () => {
  assert.equal(priceMinor('24.99'), 2499);
  assert.equal(priceMinor(' 3.5 '), 350);
  assert.equal(priceMinor('1000000'), 100000000);
  for (const invalid of [
    '',
    '0',
    '-1',
    '3.999',
    '1e3',
    '£12',
    'NaN',
    '1000000.01',
  ])
    assert.equal(priceMinor(invalid), null);
});

test('regular and sale UI map to the existing effective/compare-at backend fields', () => {
  assert.deepEqual(variantPrices('30.00', '24.99'), {
    price_minor: 2499,
    compare_at_minor: 3000,
  });
  assert.deepEqual(priceFields({ price_minor: 2499, compare_at_minor: 3000 }), {
    price: '30.00',
    sale: '24.99',
  });
});

test('a completely untouched new manual row can be ignored without creating fake variants', () => {
  assert.equal(
    isUnusedVariant({
      sku: '',
      colour: '',
      size: '',
      price: '',
      sale: '',
      barcode: null,
      weight_grams: null,
      low_stock_threshold: 5,
      active: true,
      fit: null,
      care: null,
      measurements: null,
    }),
    true,
  );
});
test('saved and partially completed variants are never discarded as unused', () => {
  const blank = { sku: '', colour: '', size: '', price: '', sale: '' };
  for (const value of [
    { id: 1 },
    { sku: 'KEPT' },
    { colour: 'Green' },
    { size: 'M' },
    { price: '30' },
    { sale: '20' },
    { barcode: '123' },
    { weight_grams: 1 },
    { measurements: { chest_cm: 100 } },
    { fit: 'Regular' },
    { care: 'Hand wash' },
    { active: false },
    { low_stock_threshold: 0 },
  ]) {
    assert.equal(isUnusedVariant({ ...blank, ...value }), false);
  }
});
test('removing a sale restores regular price and clears the old compare-at value', () => {
  const fields = priceFields({ price_minor: 2499, compare_at_minor: 3000 });
  assert.deepEqual(variantPrices(fields.price, ''), {
    price_minor: 3000,
    compare_at_minor: null,
  });
  assert.deepEqual(priceFields({ price_minor: 3000, compare_at_minor: null }), {
    price: '30.00',
    sale: '',
  });
  assert.deepEqual(variantPrices('0.00', ''), {
    price_minor: 0,
    compare_at_minor: null,
  });
});
test('sale must be positive and strictly below its regular price', () => {
  for (const sale of ['0', '-1', '30', '31', '24.999', 'Infinity'])
    assert.throws(() => variantPrices('30', sale), /Sale price/);
  assert.throws(() => variantPrices('', '20'), /regular GBP price/);
  assert.throws(() => variantPrices('1000000.01', '20'), /regular GBP price/);
});
test('generates only confirmed combinations and unique valid local SKUs', () => {
  const rows = variantCombinations(
    ['Green', 'Blue', 'green'],
    ['S', 'M', 's'],
    [],
    '24.99',
    'EV6-ABC12',
  );
  assert.equal(rows.length, 4);
  assert.equal(new Set(rows.map((v) => v.sku)).size, 4);
  assert.ok(
    rows.every(
      (v) =>
        v.price_minor === 2499 && /^[A-Z0-9][A-Z0-9._-]{0,79}$/.test(v.sku),
    ),
  );
  assert.deepEqual(Object.keys(rows[0]).sort(), [
    'colour',
    'price_minor',
    'size',
    'sku',
  ]);
});
test('preserves existing combinations and prices and is safe to generate again', () => {
  const existing = [
    { colour: 'Green', size: ' S ', sku: 'KEPT', price_minor: 1200 },
  ];
  const snapshot = JSON.stringify(existing);
  const next = variantCombinations(
    ['green', 'Blue'],
    ['S', 'M'],
    existing,
    '30',
    'PREFIX',
  );
  assert.equal(next.length, 3);
  assert.equal(JSON.stringify(existing), snapshot);
  assert.equal(
    variantCombinations(
      ['green', 'Blue'],
      ['S', 'M'],
      [...existing, ...next],
      '40',
      'NEXT',
    ).length,
    0,
  );
});
test('handles colliding normalised colour tokens without duplicate SKUs', () => {
  const rows = variantCombinations(
    ['Blue/Green', 'Blue-Green'],
    ['M'],
    [],
    '10',
    'EV6',
  );
  assert.equal(rows.length, 2);
  assert.notEqual(rows[0].sku, rows[1].sku);
});
test('rejects missing supplier size information and oversized variant matrices', () => {
  assert.throws(
    () => variantCombinations(['Blue'], [], [], '20', 'EV6'),
    /available sizes/,
  );
  assert.throws(
    () => variantCombinations([], ['S'], [], '20', 'EV6'),
    /at least one colour/,
  );
  assert.throws(
    () => variantCombinations(['Blue'], ['S'], [], '', 'EV6'),
    /positive GBP/,
  );
  assert.throws(
    () => variantCombinations(['x'.repeat(81)], ['S'], [], '20', 'EV6'),
    /80 characters/,
  );
  assert.throws(
    () =>
      variantCombinations(
        Array.from({ length: 11 }, (_, i) => `C${i}`),
        Array.from({ length: 10 }, (_, i) => `S${i}`),
        [],
        '20',
        'EV6',
      ),
    /100 variants/,
  );
});
