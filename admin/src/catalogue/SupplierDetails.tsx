import { useEffect, useState } from 'react';
import { request } from './common';
type Supplier = {
  supplier: string;
  external_id: string;
  url: string;
  cost_minor: number | null;
  shipping_cost_minor: number | null;
  currency: string | null;
  checked_at: string | null;
};
type SupplierVariant = {
  id: number;
  variant_id: number | null;
  external_id: string | null;
  cost_minor: number | null;
  shipping_cost_minor: number | null;
  currency: string | null;
  checked_at: string | null;
  pending_catalogue: unknown;
};
export default function SupplierDetails({ productId }: { productId: number }) {
  const [data, setData] = useState<{
    supplier: Supplier | null;
    variants: SupplierVariant[];
  } | null>(null);
  const [error, setError] = useState('');
  useEffect(() => {
    let live = true;
    void request<{ supplier: Supplier | null; variants: SupplierVariant[] }>(
      `products/${productId}/supplier`,
    )
      .then((result) => {
        if (live) setData(result);
      })
      .catch(() => {
        if (live)
          setError(
            'Supplier information could not be loaded. Reload to retry.',
          );
      });
    return () => {
      live = false;
    };
  }, [productId]);
  if (!data?.supplier && !error) return null;
  const s = data?.supplier;
  return (
    <section className="catalogue-panel supplier-detail">
      <h2>Supplier information</h2>
      <p className="muted">
        Private to catalogue managers. Catalogue data only; no automatic order
        fulfilment.
      </p>
      {error && <p role="alert">{error}</p>}
      {s && (
        <>
          <p>
            {s.supplier} · {s.external_id} ·{' '}
            <a href={s.url} target="_blank" rel="noreferrer">
              Supplier listing
            </a>
          </p>
          <p>
            Cost:{' '}
            {s.cost_minor === null
              ? 'Unknown'
              : `${s.cost_minor / 100} ${s.currency}`}{' '}
            · Shipping:{' '}
            {s.shipping_cost_minor === null
              ? 'Unknown'
              : `${s.shipping_cost_minor / 100} ${s.currency}`}{' '}
            · Checked: {s.checked_at || 'Unknown'}
          </p>
          {data?.variants.map((variant) => (
            <details key={variant.id}>
              <summary>
                Local variant {variant.variant_id || 'pending'} · Supplier
                variant {variant.external_id || 'unknown'}
              </summary>
              <pre>{JSON.stringify(variant, null, 2)}</pre>
            </details>
          ))}
        </>
      )}
    </section>
  );
}
