export type Collection = {
  image_url?: string | null;
  image_alt?: string | null;
  parent_id?: number | null;
  categories?: Collection[];
  id: number;
  title: string;
  slug: string;
  kind: string;
  description: string | null;
};
export type Measurements = {
  size: string;
  chest_cm: number;
  length_cm: number;
  shoulder_cm: number;
};
export type Variant = {
  id: number;
  sku: string;
  colour: string;
  size: string;
  price_minor: number;
  compare_at_minor: number | null;
  currency: string;
  available: number;
  stock_tracked?: boolean;
  is_in_stock?: boolean;
  active: boolean;
  measurements: Partial<Omit<Measurements, 'size'>> | null;
  fit: string | null;
  care: string | null;
};
export function variantInStock(
  variant: Pick<Variant, 'available' | 'is_in_stock'>,
) {
  return variant.is_in_stock ?? variant.available > 0;
}
export type ProductMedia = {
  id: string;
  variant_id: number | null;
  alt: string;
  url: string;
  width: number;
  height: number;
  sources: { url: string; width: number; height: number }[];
};
export type Product = {
  id: number;
  title: string;
  slug: string;
  product_type: string;
  description: string;
  material: string;
  fit: string;
  care: string;
  measurements: Measurements[];
  seo_title: string;
  seo_description: string;
  collections: Collection[];
  variants: Variant[];
  media: ProductMedia[];
  selected_variant_id?: number | null;
  matched_variant_ids?: number[];
  listing_price_minor?: number;
};
export type Listing = {
  items: Product[];
  page: number;
  last_page: number;
  total: number;
  facets: { colours: string[]; sizes: string[] };
};
export type SavedChoice = { product_id: number; variant_id: number | null };
export type BagHandoffV1 = {
  version: 1;
  product_id: number;
  variant_id: number;
  quantity: 1;
  currency: 'GBP';
};
export const publicApi = (
  process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1'
).replace(/\/$/, '');
export const money = (minor: number) =>
  new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(
    minor / 100,
  );
export function publicProduct(p: Product): Product {
  return {
    ...p,
    media: p.media.map((m) => ({
      ...m,
      url: `${publicApi}/catalogue/media/${m.id}/1280`,
      sources: m.sources.map((s) => ({
        ...s,
        url: `${publicApi}/catalogue/media/${m.id}/${s.url.split('/').pop()}`,
      })),
    })),
  };
}
export async function loadSaved(
  ids: number[],
  signal?: AbortSignal,
): Promise<{ items: Product[]; unavailable_ids: number[] }> {
  if (ids.length > 500)
    throw Error('An account can hold up to 500 saved pieces.');
  const unique = Array.from(new Set(ids));
  const batches: number[][] = [];
  for (let i = 0; i < unique.length; i += 50)
    batches.push(unique.slice(i, i + 50));
  const results = await Promise.all(
    batches.map(async (batch) => {
      const response = await fetch(
        `${publicApi}/catalogue/saved-products?ids=${batch.join(',')}`,
        {
          cache: 'no-store',
          credentials: 'omit',
          signal: signal
            ? AbortSignal.any([signal, AbortSignal.timeout(8000)])
            : AbortSignal.timeout(8000),
        },
      );
      if (!response.ok)
        throw Error('We could not refresh the catalogue. Please try again.');
      const json = await response.json();
      return {
        items: json.data.items.map(publicProduct) as Product[],
        unavailable_ids: json.data.unavailable_ids as number[],
      };
    }),
  );
  return {
    items: results.flatMap((r) => r.items),
    unavailable_ids: results.flatMap((r) => r.unavailable_ids),
  };
}
