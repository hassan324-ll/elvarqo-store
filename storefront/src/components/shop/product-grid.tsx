'use client';
import Link from 'next/link';
import { type Product, money, variantInStock } from '../../lib/catalogue-types';
import { SavePiece } from './wishlist';
import ProductImage from './product-image';
import { cardPrice } from '../../lib/product-card';

export default function ProductGrid({ products }: { products: Product[] }) {
  return (
    <div className="live-product-grid compact-product-grid">
      {products.map((p) => {
        const matched = p.matched_variant_ids
          ? p.variants.filter((v) => p.matched_variant_ids!.includes(v.id))
          : p.variants;
        const first =
          matched.find((v) => v.id === p.selected_variant_id) ||
          matched[0] ||
          p.variants[0];
        const media =
          p.media.find((m) => m.variant_id === first?.id) ||
          p.media.find((m) => !m.variant_id) ||
          p.media[0];
        const price = cardPrice(matched);
        const href = `/products/${p.slug}${p.selected_variant_id ? '?variant=' + p.selected_variant_id : ''}`;
        return (
          <article
            className="live-product-card refined-product-card compact-product-card"
            key={p.id}
          >
            <div className="product-card-image">
              <Link
                href={href}
                prefetch={false}
                aria-label={`View ${p.title}`}
                className="product-card-photo-link"
              >
                <ProductImage media={media} />
              </Link>
              {!matched.some(variantInStock) && (
                <span className="product-card-badge sold-out-badge">
                  Sold out
                </span>
              )}
              <SavePiece
                id={p.id}
                variant={p.selected_variant_id ?? null}
                iconOnly
                title={p.title}
              />
            </div>
            <div className="product-card-content">
              <h3 className="product-card-name">
                <Link href={href} prefetch={false} title={p.title}>
                  {p.title}
                </Link>
              </h3>
              <div
                className={`product-card-price${price?.regular ? ' discounted' : ''}`}
              >
                {price ? (
                  <>
                    <strong>
                      {price.from && <span>From </span>}
                      {money(price.amount)}
                    </strong>
                    {price.regular && <del>{money(price.regular)}</del>}
                  </>
                ) : (
                  <span>View product for price</span>
                )}
              </div>
            </div>
          </article>
        );
      })}
    </div>
  );
}
