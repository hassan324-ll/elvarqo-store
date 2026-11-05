import test from 'node:test';
import assert from 'node:assert/strict';
import { catalogueSitemap, escapeXml, sitemapXml } from '../src/lib/sitemap.ts';
const page = (products = [], groups = []) => ({
  products,
  groups,
  page: 1,
  last_page: 1,
  total: products.length,
});
test('XML escapes all reserved characters and preserves a valid namespace', () => {
  assert.equal(escapeXml('&<>"\''), '&amp;&lt;&gt;&quot;&apos;');
  const xml = sitemapXml([
    {
      url: 'https://elvarqo.uk/?a=1&b=<"\'>',
      lastmod: '2026-01-02T00:00:00.000Z',
    },
  ]);
  assert.ok(xml.startsWith('<?xml version="1.0" encoding="UTF-8"?>'));
  assert.ok(
    xml.includes('xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'),
  );
  assert.ok(xml.includes('&amp;b=&lt;&quot;&apos;&gt;'));
});
test('fetches every page, canonicalises and deduplicates without private/filter URLs', async () => {
  const seen = [];
  const entries = await catalogueSitemap(async (n) => {
    seen.push(n);
    return {
      ...page(
        [
          {
            slug: n === 1 ? 'first' : 'second',
            lastmod: '2026-01-02T12:00:00Z',
          },
        ],
        [{ slug: 'women' }, { slug: 'women' }],
      ),
      page: n,
      last_page: 2,
      total: 2,
    };
  });
  assert.deepEqual(seen, [1, 2]);
  assert.equal(entries.length, 6);
  assert.ok(
    entries.every(
      (e) => e.url.startsWith('https://elvarqo.uk/') && !e.url.includes('?'),
    ),
  );
  assert.equal(
    entries.find((e) => e.url.endsWith('/first')).lastmod,
    '2026-01-02T12:00:00.000Z',
  );
  assert.ok(entries.slice(0, 3).every((e) => e.lastmod === undefined));
});
test('omits unavailable invalid or future lastmod instead of inventing dates', async () => {
  for (const date of [null, 'bad', '9999-01-01T00:00:00Z']) {
    const entries = await catalogueSitemap(async () =>
      page([{ slug: 'valid', lastmod: date }]),
    );
    assert.equal(entries.at(-1).lastmod, undefined);
  }
});
test('fresh reads reflect edits and removals without rebuild', async () => {
  let products = [{ slug: 'old' }];
  const load = async () => page(products);
  assert.ok((await catalogueSitemap(load)).some((e) => e.url.endsWith('/old')));
  products = [{ slug: 'new' }];
  const entries = await catalogueSitemap(load);
  assert.ok(entries.some((e) => e.url.endsWith('/new')));
  assert.ok(!entries.some((e) => e.url.endsWith('/old')));
});
test('rejects unsafe slugs, oversized/invalid pagination and partial failures', async () => {
  for (const value of [
    '../account',
    'bad?filter=x',
    '<xml>',
    'https://evil.test',
  ])
    await assert.rejects(catalogueSitemap(async () => page([{ slug: value }])));
  await assert.rejects(
    catalogueSitemap(async () => ({ ...page(), last_page: 101 })),
  );
  await assert.rejects(
    catalogueSitemap(async () => ({ ...page(), total: 50001 })),
  );
  await assert.rejects(
    catalogueSitemap(async (n) => {
      if (n === 2) throw Error('offline');
      return { ...page(), last_page: 2 };
    }),
  );
});
