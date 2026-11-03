'use client';
import { useCallback, useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { Button } from '../../ui/components';
import { type Product, loadSaved } from '../../lib/catalogue-types';
import { useWishlist } from './wishlist';
import ProductGrid from './product-grid';
export default function SavedPieces() {
  const { items, ready, remove } = useWishlist();
  const [products, setProducts] = useState<Product[]>([]);
  const [unavailable, setUnavailable] = useState<number[]>([]);
  const [busy, setBusy] = useState(false);
  const [responseFor, setResponseFor] = useState('');
  const [error, setError] = useState('');
  const flight = useRef<AbortController | null>(null);
  const ids = items.map((i) => i.product_id).join(',');
  const refresh = useCallback(async () => {
    flight.current?.abort();
    const controller = new AbortController();
    flight.current = controller;
    await Promise.resolve();
    if (controller.signal.aborted) return null;
    setBusy(true);
    setError('');
    try {
      const result = await loadSaved(
        ids.split(',').filter(Boolean).map(Number),
        controller.signal,
      );
      if (!controller.signal.aborted) {
        setProducts(result.items);
        setUnavailable(result.unavailable_ids);
        setResponseFor(ids);
      }
    } catch (e) {
      if (!controller.signal.aborted)
        setError(
          e instanceof Error ? e.message : 'We could not refresh saved pieces.',
        );
    } finally {
      if (!controller.signal.aborted) setBusy(false);
    }
  }, [ids]);
  useEffect(() => {
    const initialRequest =
      ready && ids ? setTimeout(() => void refresh(), 0) : undefined;
    return () => {
      clearTimeout(initialRequest);
      flight.current?.abort();
    };
  }, [ready, ids, refresh]);
  const invalid = products.filter((p) => {
    const item = items.find((i) => i.product_id === p.id);
    return (
      item?.variant_id &&
      !p.variants.some((v) => v.id === item.variant_id && v.available > 0)
    );
  });
  return (
    <>
      {!ready ? (
        <p role="status">Opening your saved pieces…</p>
      ) : !items.length ? (
        <section className="shop-empty">
          <h2>A little space for favourites.</h2>
          <p>Save a piece from the collection to return to it here.</p>
          <Link href="/shop" className="button button-primary">
            Explore the collection
          </Link>
        </section>
      ) : (
        <>
          {busy && (
            <p role="status">Refreshing current prices and availability…</p>
          )}
          {error ? (
            <section role="alert" className="shop-recovery">
              <p>{error} Your saved identifiers are unchanged.</p>
              <Button onClick={() => void refresh()}>Retry saved pieces</Button>
            </section>
          ) : busy || responseFor !== ids ? (
            <p role="status">Loading current saved pieces…</p>
          ) : (
            <>
              <p role="status">
                {items.length} saved {items.length === 1 ? 'piece' : 'pieces'} ·
                prices and availability refreshed from the catalogue
              </p>
              {invalid.map((p) => (
                <p key={p.id} className="shop-notice">
                  Your saved choice for {p.title} is unavailable. Open the piece
                  to choose another size or colour.
                </p>
              ))}
              <ProductGrid
                products={products
                  .filter((p) => items.some((i) => i.product_id === p.id))
                  .map((p) => ({
                    ...p,
                    selected_variant_id: items.find(
                      (i) => i.product_id === p.id,
                    )?.variant_id,
                  }))}
              />
              {unavailable
                .filter((id) => items.some((i) => i.product_id === id))
                .map((id) => (
                  <section className="shop-empty unavailable-saved" key={id}>
                    <h2>Saved piece unavailable</h2>
                    <p>This piece is no longer in the published collection.</p>
                    <Button variant="secondary" onClick={() => remove(id)}>
                      Remove unavailable piece
                    </Button>
                  </section>
                ))}
              <Button
                variant="secondary"
                busy={busy}
                onClick={() => void refresh()}
              >
                Refresh saved pieces
              </Button>
            </>
          )}
        </>
      )}
      <noscript>
        Guest saved pieces need JavaScript and browser storage. Product
        discovery remains available through the collection links.
      </noscript>
    </>
  );
}
