import test from 'node:test';
import assert from 'node:assert/strict';
import {
  productStructuredData,
  safeJsonLd,
} from '../src/lib/product-structured-data.ts';
import { variantInStock } from '../src/lib/catalogue-types.ts';
const origin = 'https://elvarqo.uk';
const variant = {
  id: 11,
  sku: 'REAL-GREEN-S',
  colour: 'Green',
  size: 'S',
  price_minor: 2099,
  compare_at_minor: 2999,
  currency: 'GBP',
  active: true,
  available: 0,
  is_in_stock: true,
};
const fixture = () => ({
  id: 6,
  title: 'Actual jumper',
  slug: 'actual-jumper',
  description: 'Supplier supplied description',
  collections: [{ title: 'Women', slug: 'women' }],
  media: [
    { url: 'https://api.elvarqo.uk/shared.webp', variant_id: null },
    { url: 'https://api.elvarqo.uk/green.webp', variant_id: 11 },
    { url: 'https://api.elvarqo.uk/blue.webp', variant_id: 12 },
  ],
  variants: [
    variant,
    {
      ...variant,
      id: 12,
      sku: 'REAL-BLUE-M',
      colour: 'Blue',
      size: 'M',
      price_minor: 2250,
      is_in_stock: false,
    },
  ],
});
const schema = (p) => productStructuredData(p, origin, variantInStock);
test('groups actual variants with exact SKU GBP selling price availability and direct selection URLs', () => {
  const p = fixture();
  const [group, breadcrumb] = schema(p)['@graph'];
  assert.equal(group['@type'], 'ProductGroup');
  assert.equal(group.name, p.title);
  assert.equal(group.description, p.description);
  assert.equal(group.url, origin + '/products/actual-jumper');
  assert.equal(group.productGroupID, '6');
  assert.deepEqual(group.variesBy, [
    'https://schema.org/color',
    'https://schema.org/size',
  ]);
  for (let i = 0; i < 2; i++) {
    const v = p.variants[i],
      s = group.hasVariant[i];
    assert.equal(s.sku, v.sku);
    assert.equal(s.color, v.colour);
    assert.equal(s.size, v.size);
    assert.equal(s.offers.price, (v.price_minor / 100).toFixed(2));
    assert.equal(s.offers.priceCurrency, 'GBP');
    assert.equal(s.offers.url, group.url + '?variant=' + v.id);
  }
  assert.equal(
    group.hasVariant[0].offers.availability,
    'https://schema.org/InStock',
  );
  assert.equal(
    group.hasVariant[1].offers.availability,
    'https://schema.org/OutOfStock',
  );
  assert.deepEqual(group.hasVariant[0].image, [
    'https://api.elvarqo.uk/green.webp',
    'https://api.elvarqo.uk/shared.webp',
  ]);
  assert.deepEqual(
    breadcrumb.itemListElement.map((c) => [c.position, c.name, c.item]),
    [
      [1, 'Shop', origin + '/shop'],
      [2, 'Women', origin + '/collections/women'],
      [3, p.title, group.url],
    ],
  );
});
test('single variants use Product and unknown SKU claims are omitted', () => {
  const p = fixture();
  p.variants = [{ ...variant, sku: '' }];
  const s = schema(p)['@graph'][0];
  assert.equal(s['@type'], 'Product');
  assert.equal(s.name, p.title);
  assert.ok(!('sku' in s));
  for (const key of [
    'brand',
    'gtin',
    'gtin13',
    'review',
    'aggregateRating',
    'shippingDetails',
    'hasMerchantReturnPolicy',
    'itemCondition',
    'priceValidUntil',
  ])
    assert.ok(!safeJsonLd(schema(p)).includes('"' + key + '"'));
});
test('inactive and invalid currency or prices never become offers; legacy tracked availability agrees', () => {
  const p = fixture();
  p.variants = [
    { ...variant, is_in_stock: undefined, available: 0 },
    { ...variant, id: 12, active: false },
    { ...variant, id: 13, currency: 'USD' },
    { ...variant, id: 14, price_minor: -1 },
    { ...variant, id: 15, price_minor: NaN },
  ];
  assert.equal(
    schema(p)['@graph'][0].offers.availability,
    'https://schema.org/OutOfStock',
  );
  p.variants[0].available = 1;
  assert.equal(
    schema(p)['@graph'][0].offers.availability,
    'https://schema.org/InStock',
  );
});
test('breadcrumbs omit absent collection and safely serialise hostile text without script breakout', () => {
  const p = fixture();
  p.collections = [];
  p.title = '</script><script>alert("x")</script> & \u2028\u2029';
  p.description = 'Text <img src=x onerror=alert(1)>';
  const value = schema(p),
    json = safeJsonLd(value);
  assert.ok(!/[<>&\u2028\u2029]/.test(json));
  assert.deepEqual(JSON.parse(json), value);
  assert.equal(value['@graph'][1].itemListElement.length, 2);
});
test('unsafe images are excluded; valid relative paths become absolute HTTPS', () => {
  const p = fixture();
  p.media = [
    { url: 'javascript:alert(1)', variant_id: null },
    { url: 'http://insecure.test/a', variant_id: null },
    { url: '/real.webp', variant_id: null },
  ];
  assert.deepEqual(schema(p)['@graph'][0].image, [origin + '/real.webp']);
});
test('all catalogue edits reflect on next generation without retained prices or stock', () => {
  const p = fixture();
  const first = schema(p);
  p.variants[0].price_minor = 1750;
  p.variants[0].is_in_stock = false;
  const next = schema(p);
  assert.equal(next['@graph'][0].hasVariant[0].offers.price, '17.50');
  assert.equal(
    next['@graph'][0].hasVariant[0].offers.availability,
    'https://schema.org/OutOfStock',
  );
  assert.equal(first['@graph'][0].hasVariant[0].offers.price, '20.99');
});
