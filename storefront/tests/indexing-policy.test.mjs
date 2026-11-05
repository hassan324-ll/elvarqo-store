import test from 'node:test';
import assert from 'node:assert/strict';
import {
  productionIndexing,
  productionHost,
  excludeFromIndex,
} from '../src/lib/indexing-policy.ts';
test('only explicitly enabled canonical production can index', () => {
  assert.equal(
    productionIndexing('true', 'production', 'https://elvarqo.uk'),
    true,
  );
  for (const args of [
    ['false', 'production', 'https://elvarqo.uk'],
    ['true', 'development', 'https://elvarqo.uk'],
    ['true', 'production', 'https://stage.elvarqo.uk'],
    ['true', 'production', undefined],
    [undefined, 'production', 'https://elvarqo.uk'],
  ])
    assert.equal(productionIndexing(...args), false);
});
test('temporary staging admin and lookalike hosts are excluded', () => {
  for (const host of ['elvarqo.uk', 'www.elvarqo.uk', 'ELVARQO.UK'])
    assert.equal(productionHost(host), true);
  for (const host of [
    'admin.elvarqo.uk',
    'api.elvarqo.uk',
    'stage.elvarqo.uk',
    'dimgray.hostingersite.com',
    'localhost:3000',
    'elvarqo.uk.evil.test',
    'elvarqo.uk@evil.test',
  ])
    assert.equal(productionHost(host), false);
});
test('public homepage shop collections and products are indexable when enabled', () => {
  for (const path of [
    '/',
    '/shop',
    '/collections',
    '/collections/women',
    '/products/real-product',
  ])
    assert.equal(excludeFromIndex('elvarqo.uk', path, true), false);
});
test('private diagnostics placeholders and filtered duplicate pages remain excluded', () => {
  for (const path of [
    '/admin',
    '/account',
    '/bag',
    '/checkout/confirmation',
    '/pay-order',
    '/returns',
    '/track',
    '/wishlist',
    '/search',
    '/development',
    '/design-system',
    '/privacy',
    '/the-edit',
    '/api/commerce/bag',
  ])
    assert.equal(excludeFromIndex('elvarqo.uk', path, true), true);
  assert.equal(excludeFromIndex('elvarqo.uk', '/shop', true, true), true);
  assert.equal(
    excludeFromIndex('elvarqo.uk', '/collections/women', true, true),
    true,
  );
  assert.equal(excludeFromIndex('staging.elvarqo.uk', '/', true), true);
  assert.equal(excludeFromIndex('elvarqo.uk', '/', false), true);
});
