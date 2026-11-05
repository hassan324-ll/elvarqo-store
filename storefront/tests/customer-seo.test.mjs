import test from 'node:test';
import assert from 'node:assert/strict';
import { collectionContent } from '../src/lib/collection-content.ts';
import {
  productBreadcrumbs,
  productStructuredData,
} from '../src/lib/product-structured-data.ts';
test('specific existing Women Knitwear copy stays bounded and other categories retain their data', () => {
  const parent = { id: 9, slug: 'women' },
    group = {
      kind: 'category',
      slug: 'womens-knitwear',
      title: 'Knitwear',
      description: null,
    };
  const copy = collectionContent(group, parent);
  assert.equal(copy.heading, "Women's knitwear");
  assert.ok(copy.description.length <= 160);
  assert.ok(copy.title.length + 10 <= 70);
  assert.equal(
    collectionContent({ ...group, slug: 'other' }, parent).heading,
    'Knitwear',
  );
  assert.equal(collectionContent(group, { slug: 'baby' }).heading, 'Knitwear');
});
test('visible and structured breadcrumbs include only the actual category parent', () => {
  const p = {
    id: 1,
    slug: 'jumper',
    title: 'Jumper',
    description: 'Real copy',
    variants: [],
    media: [],
    collections: [
      { id: 9, kind: 'collection', title: 'Women', slug: 'women' },
      {
        id: 16,
        kind: 'category',
        title: 'Knitwear',
        slug: 'womens-knitwear',
        parent_id: 9,
      },
    ],
  };
  const crumbs = productBreadcrumbs(p, 'https://elvarqo.uk');
  assert.deepEqual(
    crumbs.map((c) => c.name),
    ['Shop', 'Women', 'Knitwear', 'Jumper'],
  );
  assert.deepEqual(
    productStructuredData(p, 'https://elvarqo.uk', () => false)[
      '@graph'
    ][0].itemListElement.map((c) => c.name),
    crumbs.map((c) => c.name),
  );
  p.collections[1].parent_id = 10;
  assert.deepEqual(
    productBreadcrumbs(p, 'https://elvarqo.uk').map((c) => c.name),
    ['Shop', 'Women', 'Jumper'],
  );
  delete p.collections[1].parent_id;
  assert.equal(productBreadcrumbs(p, 'https://elvarqo.uk').length, 3);
});
