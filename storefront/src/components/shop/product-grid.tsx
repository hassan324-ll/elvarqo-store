'use client';
import { useState } from 'react';
import Link from 'next/link';
import { Modal, Icon } from '../../ui/components';
import { type Product, money } from '../../lib/catalogue-types';
import { SavePiece } from './wishlist';
import ProductImage from './product-image';
import ProductSelection from './product-selection';
export default function ProductGrid({ products }: { products: Product[] }) {
  const [quick, setQuick] = useState<Product | null>(null);
  return (
    <>
      <div className="live-product-grid">
        {products.map((p) => {
          const matched = p.matched_variant_ids
            ? p.variants.filter((v) => p.matched_variant_ids!.includes(v.id))
            : p.variants;
          const first = matched[0] || p.variants[0];
          const media =
            p.media.find((m) => m.variant_id === first?.id) ||
            p.media.find((m) => !m.variant_id) ||
            p.media[0];
          return (
            <article className="live-product-card" key={p.id}>
              <div className="product-card-image">
                <Link
                  href={`/products/${p.slug}${p.selected_variant_id ? '?variant=' + p.selected_variant_id : ''}`}
                  prefetch={false}
                  aria-label={`View ${p.title}`}
                >
                  <ProductImage media={media} />
                </Link>
                <button
                  className="quick-trigger icon-button"
                  type="button"
                  aria-label={`Quick select ${p.title}`}
                  onClick={() => setQuick(p)}
                >
                  <Icon name="plus" />
                </button>
              </div>
              <div className="live-card-title">
                <Link
                  href={`/products/${p.slug}${p.selected_variant_id ? '?variant=' + p.selected_variant_id : ''}`}
                  prefetch={false}
                >
                  {p.title}
                </Link>
                <span>
                  From{' '}
                  {money(
                    p.listing_price_minor ??
                      Math.min(...p.variants.map((v) => v.price_minor)),
                  )}
                </span>
              </div>
              <p className="muted">
                {[...new Set(matched.map((v) => v.colour))].join(' / ')}
              </p>
              <p className="card-availability">
                {matched.some((v) => v.available > 0)
                  ? 'Available in selected sizes'
                  : 'Currently sold out'}
              </p>
              <SavePiece id={p.id} />
            </article>
          );
        })}
      </div>
      <Modal
        open={!!quick}
        title={quick ? `Choose ${quick.title}` : 'Choose a piece'}
        onClose={() => setQuick(null)}
      >
        {quick && (
          <ProductSelection
            key={quick.id}
            initial={quick}
            initialVariant={quick.selected_variant_id ?? undefined}
            compact
          />
        )}
      </Modal>
    </>
  );
}
