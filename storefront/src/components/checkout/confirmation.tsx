'use client';
import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { Button } from '../../ui/components';
import { commerce, type Order } from '../../lib/commerce';
import { Totals } from './checkout';
import BankTransferCard from './bank-transfer-card';
export default function Confirmation({ id }: { id: string }) {
  const [order, setOrder] = useState<Order | null>(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const read = useCallback(
    async (resume = false) => {
      setBusy(true);
      setError('');
      try {
        setOrder(
          await commerce<Order>(
            'orders/' + id + (resume ? '/resume' : ''),
            resume ? {} : undefined,
          ),
        );
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Order unavailable.');
      } finally {
        setBusy(false);
      }
    },
    [id],
  );
  useEffect(() => {
    const timer = setTimeout(() => void read(), 0);
    return () => clearTimeout(timer);
  }, [read]);
  const paid =
    order?.payment_state === 'paid' && order.order_state === 'confirmed';
  const wise = order?.payment_method === 'wise_bank_transfer';
  const terminal = order && ['expired', 'failed'].includes(order.payment_state);
  return (
    <section className="commerce-page">
      <p className="eyebrow">
        Guest order / {wise ? 'Wise bank transfer' : 'Payment status'}
      </p>
      <h1>
        {paid
          ? 'Thank you. Your order is confirmed.'
          : terminal
            ? wise
              ? 'The payment deadline has passed.'
              : 'Your payment was not completed.'
            : order?.payment_state === 'review'
              ? 'Your order needs review.'
              : wise
                ? 'Awaiting bank transfer'
                : 'Check your payment'}
      </h1>
      {error && (
        <p role="alert" className="shop-error">
          {error}
        </p>
      )}
      {order && (
        <>
          <p>Order {order.reference}</p>
          <p role="status">
            Order: {order.order_state} / Payment: {order.payment_state} /
            Fulfilment: {order.fulfilment_state}
          </p>
          {paid ? (
            <p>
              {wise
                ? 'Store staff confirmed receipt of your Wise bank transfer.'
                : 'Stripe sandbox payment verified.'}{' '}
              Confirmation:{' '}
              {order.mail_status === 'saved_private_sandbox_copy'
                ? 'saved to private sandbox mail.'
                : 'queued for a private sandbox mail copy.'}{' '}
              No external email is sent in this setup.
            </p>
          ) : terminal ? (
            <p>
              {wise
                ? 'This order’s stock reservation has expired. If you have already transferred money, contact the store with your order reference before placing another order. Do not pay again or use the expired instructions.'
                : 'Your bag and checkout details remain available in this order. Stock reservations have been released. Review your bag to start a new checkout at current prices.'}
            </p>
          ) : order.payment_state === 'review' ? (
            <p>
              Payment evidence needs staff review. Do not attempt a second
              payment. Your order is on hold.
            </p>
          ) : wise ? (
            order.payment_instructions ? (
              <BankTransferCard instructions={order.payment_instructions} />
            ) : (
              <p>
                The payment deadline has passed. Do not send money using old
                instructions. Refresh this order and contact the store if you
                have already transferred funds.
              </p>
            )
          ) : (
            <p>
              A return from Stripe does not confirm payment. A declined card can
              be retried on the same hosted checkout. An abandoned checkout
              keeps its stock reserved until Stripe confirms expiry.
            </p>
          )}
          {order.refresh_unavailable && (
            <p role="status">
              Payment status could not be refreshed. Try again later.
            </p>
          )}
          <div className="selection-actions">
            {order.checkout_url && !paid && (
              <a className="button button-primary" href={order.checkout_url}>
                Continue hosted payment
              </a>
            )}
            <Button
              variant="secondary"
              busy={busy}
              onClick={() => void read(true)}
            >
              Refresh payment status
            </Button>
            {terminal && !wise && (
              <Link
                className="button button-primary"
                href={'/checkout?retry=' + order.id}
              >
                Review bag and retry
              </Link>
            )}
            <Link href="/track">Track your order securely</Link>
            <Link href="/shop">Continue shopping</Link>
          </div>
          <Totals s={order.snapshot} />
          <h2>Delivery address snapshot</h2>
          <p>
            {order.snapshot.shipping_address.first_name}{' '}
            {order.snapshot.shipping_address.last_name}
            <br />
            {order.snapshot.shipping_address.line1},{' '}
            {order.snapshot.shipping_address.city},{' '}
            {order.snapshot.shipping_address.postcode}
          </p>
        </>
      )}
      {!order && (
        <Button busy={busy} onClick={() => void read()}>
          Load order status
        </Button>
      )}
    </section>
  );
}
