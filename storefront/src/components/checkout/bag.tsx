'use client';
import Link from 'next/link';
import { Button } from '../../ui/components';
import { money } from '../../lib/catalogue-types';
import { useBag } from './bag-provider';
export default function BagContents({
  onNavigate,
}: {
  onNavigate?: () => void;
}) {
  const { bag, loading, error, refresh, change } = useBag();
  return (
    <div className="commerce-flow">
      {error && (
        <p role="alert" className="shop-error">
          {error}
        </p>
      )}
      <Button
        variant="secondary"
        busy={loading}
        onClick={() => void refresh().catch(() => {})}
      >
        Refresh bag
      </Button>
      {!loading && !bag?.items.length && (
        <div>
          <h2>Your bag is waiting.</h2>
          <p>Explore the collection to find your next piece.</p>
          <Link href="/shop" className="text-link" onClick={onNavigate}>
            Continue shopping
          </Link>
        </div>
      )}
      {!!bag?.items.length && (
        <>
          {bag.active_order_id && (
            <p role="status">
              An order is awaiting payment.{' '}
              <Link
                onClick={onNavigate}
                href={'/checkout/confirmation?order=' + bag.active_order_id}
              >
                Resume or check payment
              </Link>
              . Quantities are locked while its stock is reserved.
            </p>
          )}
          <ul className="bag-lines">
            {bag.items.map((l) => (
              <li key={l.variant_id}>
                <div>
                  {l.slug ? (
                    <Link href={'/products/' + l.slug} onClick={onNavigate}>
                      {l.title}
                    </Link>
                  ) : (
                    <strong>Unavailable piece</strong>
                  )}
                  <p>
                    {l.colour} / {l.size}
                  </p>
                  <p>
                    {l.unit_minor === undefined
                      ? 'Price unavailable'
                      : money(l.unit_minor)}
                  </p>
                  {l.unavailable && (
                    <p role="status">
                      This quantity is unavailable. Reduce it or remove the
                      piece.
                    </p>
                  )}
                </div>
                <div className="bag-quantity">
                  <label>
                    Quantity
                    <select
                      aria-label={
                        'Quantity for ' + (l.title || 'unavailable piece')
                      }
                      value={l.quantity}
                      disabled={loading || !!bag.active_order_id}
                      onChange={(e) =>
                        void change(l.variant_id, Number(e.target.value)).catch(
                          () => {},
                        )
                      }
                    >
                      {Array.from({ length: 10 }, (_, i) => (
                        <option key={i + 1} value={i + 1}>
                          {i + 1}
                        </option>
                      ))}
                    </select>
                  </label>
                  <Button
                    variant="secondary"
                    disabled={loading || !!bag.active_order_id}
                    onClick={() => void change(l.variant_id, 0).catch(() => {})}
                  >
                    Remove
                  </Button>
                </div>
              </li>
            ))}
          </ul>
          <p>
            Subtotal{' '}
            <strong>
              {money(bag.items.reduce((n, l) => n + (l.line_minor || 0), 0))}
            </strong>
          </p>
          <p>
            Delivery and test tax calculated at checkout. Stock is reserved when
            you continue to hosted payment.
          </p>
          {!bag.active_order_id && !bag.items.some((l) => l.unavailable) && (
            <Link
              href="/checkout"
              className="button button-primary"
              onClick={onNavigate}
            >
              Checkout as guest
            </Link>
          )}
        </>
      )}
    </div>
  );
}
