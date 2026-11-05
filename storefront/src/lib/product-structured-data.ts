import type { Product, Variant } from './catalogue-types';

type Availability = (variant: Variant) => boolean;
function absolute(value: string, origin: string): string | undefined {
  try {
    const url = new URL(value, origin);
    return url.protocol === 'https:' && !url.username && !url.password
      ? url.href
      : undefined;
  } catch {
    return undefined;
  }
}
export function productBreadcrumbs(product: Product, origin: string) {
  const collection =
    product.collections.find((group) => group.kind === 'collection') ||
    product.collections[0];
  const category =
    collection &&
    product.collections.find(
      (group) => group.kind === 'category' && group.parent_id === collection.id,
    );
  return [
    { name: 'Shop', item: new URL('/shop', origin).href },
    ...(collection
      ? [
          {
            name: collection.title,
            item: new URL('/collections/' + collection.slug, origin).href,
          },
        ]
      : []),
    ...(category
      ? [
          {
            name: category.title,
            item: new URL('/collections/' + category.slug, origin).href,
          },
        ]
      : []),
    {
      name: product.title,
      item: new URL('/products/' + product.slug, origin).href,
    },
  ];
}
export function productStructuredData(
  product: Product,
  origin: string,
  inStock: Availability,
) {
  const canonical = new URL('/products/' + product.slug, origin).href;
  const groupId = canonical + '#product-group';
  const variants = product.variants.filter(
    (v) =>
      v.active &&
      v.currency === 'GBP' &&
      Number.isSafeInteger(v.price_minor) &&
      v.price_minor >= 0,
  );
  const images = (media: Product['media']) => [
    ...new Set(
      media
        .map((m) => absolute(m.url, origin))
        .filter((url): url is string => !!url),
    ),
  ];
  const groupImages = images(product.media);
  const products = variants.map((variant) => {
    const url = canonical + '?variant=' + variant.id;
    // Match the actual gallery: explicit variant images first, then shared images.
    // Do not infer colour associations from photographs or filenames.
    const gallery = product.media
      .filter((m) => !m.variant_id || m.variant_id === variant.id)
      .sort((a, b) => Number(!a.variant_id) - Number(!b.variant_id));
    return {
      '@type': 'Product',
      '@id': canonical + '#variant-' + variant.id,
      name: [product.title, variant.colour, variant.size]
        .filter(Boolean)
        .join(' — '),
      description: product.description,
      url,
      image: images(gallery),
      ...(variant.sku?.trim() ? { sku: variant.sku } : {}),
      ...(variant.colour ? { color: variant.colour } : {}),
      ...(variant.size ? { size: variant.size } : {}),
      offers: {
        '@type': 'Offer',
        '@id': canonical + '#offer-' + variant.id,
        url,
        priceCurrency: 'GBP',
        price: (variant.price_minor / 100).toFixed(2),
        availability:
          'https://schema.org/' + (inStock(variant) ? 'InStock' : 'OutOfStock'),
      },
    };
  });
  const group = {
    '@type': 'ProductGroup',
    '@id': groupId,
    name: product.title,
    description: product.description,
    url: canonical,
    image: groupImages,
    // A stable local product identifier; never presented as a manufacturer GTIN or SKU.
    productGroupID: String(product.id),
    variesBy: [
      ...(new Set(variants.map((v) => v.colour)).size > 1
        ? ['https://schema.org/color']
        : []),
      ...(new Set(variants.map((v) => v.size)).size > 1
        ? ['https://schema.org/size']
        : []),
    ],
    hasVariant: products,
  };
  const crumbs = productBreadcrumbs(product, origin);
  return {
    '@context': 'https://schema.org',
    '@graph': [
      ...(products.length > 1
        ? [group]
        : products.length === 1
          ? [{ ...products[0], name: product.title, url: canonical }]
          : []),
      {
        '@type': 'BreadcrumbList',
        '@id': canonical + '#breadcrumbs',
        itemListElement: crumbs.map((crumb, index) => ({
          '@type': 'ListItem',
          position: index + 1,
          ...crumb,
        })),
      },
    ],
  };
}
export function safeJsonLd(value: unknown): string {
  return JSON.stringify(value).replace(
    /[<>&\u2028\u2029]/g,
    (char) =>
      ({
        '<': '\\u003c',
        '>': '\\u003e',
        '&': '\\u0026',
        '\u2028': '\\u2028',
        '\u2029': '\\u2029',
      })[char]!,
  );
}
