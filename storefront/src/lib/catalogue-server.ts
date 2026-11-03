import 'server-only';
import { cache } from 'react';
import {
  type Product,
  type Collection,
  type Listing,
  publicApi,
  publicProduct,
} from './catalogue-types';
const base = (process.env.CATALOGUE_API_URL || publicApi).replace(/\/$/, '');
export class CatalogueError extends Error {
  status: number;
  constructor(status: number, message: string) {
    super(message);
    this.status = status;
  }
}
async function read(path: string) {
  let response: Response;
  try {
    response = await fetch(base + '/catalogue/' + path, {
      cache: 'no-store',
      redirect: 'manual',
      headers: { Accept: 'application/json' },
      signal: AbortSignal.timeout(7000),
    });
  } catch {
    throw new CatalogueError(
      503,
      'The collection is temporarily unavailable. Your filters and saved pieces are safe.',
    );
  }
  const result = await response.json();
  if (response.status !== 301 && !response.ok)
    throw new CatalogueError(
      response.status,
      response.status === 422
        ? 'Please check your filters and try again.'
        : 'The collection is temporarily unavailable. Please try again.',
    );
  return { status: response.status, data: result.data };
}
export const navigation = cache(
  async (): Promise<Collection[]> => (await read('navigation')).data.items,
);
export const collections = cache(
  async (): Promise<Collection[]> => (await read('collections')).data.items,
);
export const product = cache(
  async (
    slug: string,
  ): Promise<{
    product?: Product;
    canonical?: string;
    missing?: true;
    error?: string;
  }> => {
    if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug) || slug.length > 180)
      return { missing: true };
    try {
      const result = await read('products/' + slug);
      if (result.status === 301)
        return { canonical: result.data.canonical_slug };
      return { product: publicProduct(result.data.product) };
    } catch (e) {
      if (e instanceof CatalogueError && e.status === 404)
        return { missing: true };
      return {
        error:
          e instanceof Error
            ? e.message
            : 'The product is temporarily unavailable.',
      };
    }
  },
);
export async function listing(query: URLSearchParams): Promise<Listing> {
  const { data } = await read('products?' + query);
  return { ...data, items: data.items.map(publicProduct) };
}
export type Query = Record<string, string | string[] | undefined>;
export function filterParams(raw: Query, collection?: string) {
  const q = new URLSearchParams();
  for (const key of ['q', 'colour', 'size', 'stock', 'sort', 'page']) {
    const value = raw[key];
    if (Array.isArray(value))
      throw new CatalogueError(
        422,
        'Use one value per filter. Clear filters to start again.',
      );
    if (value) q.set(key, value);
  }
  for (const key of ['min', 'max']) {
    const value = raw[key];
    if (value) {
      if (typeof value !== 'string' || !/^\d{1,7}(?:\.\d{1,2})?$/.test(value))
        throw new CatalogueError(
          422,
          'Prices must be non-negative GBP amounts with at most two decimal places.',
        );
      const [whole, fraction = ''] = value.split('.');
      q.set(
        key === 'min' ? 'min_price' : 'max_price',
        String(Number(whole) * 100 + Number(fraction.padEnd(2, '0'))),
      );
    }
  }
  if (collection) q.set('collection', collection);
  q.set('per_page', '12');
  return q;
}
export const siteOrigin = (
  process.env.NEXT_PUBLIC_SITE_URL || 'https://elvarqo.uk'
).replace(/\/$/, '');
export const indexEnabled =
  process.env.STOREFRONT_INDEXING === 'true' &&
  process.env.NODE_ENV === 'production';
