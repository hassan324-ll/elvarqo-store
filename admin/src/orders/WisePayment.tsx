import { useState } from 'react';
import { Button, Field, Modal } from '../ui/components';
import { staffRequest } from '../lib/staff-api';
import { type Detail, money } from './types';
export default function WisePayment({
  order,
  canConfirm,
  onSaved,
}: {
  order: Detail;
  canConfirm: boolean;
  onSaved: () => Promise<void>;
}) {
  const [open, setOpen] = useState(false);
  const [amount, setAmount] = useState('');
  const [reference, setReference] = useState('');
  const [receipt, setReceipt] = useState('');
  const [confirmed, setConfirmed] = useState(false);
  const [operation] = useState(() => crypto.randomUUID());
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  if (order.payment_method !== 'wise_bank_transfer') return null;
  async function save() {
    if (!/^\d{1,7}(?:\.\d{1,2})?$/.test(amount)) {
      setError(
        'Enter the received amount in GBP, with at most two decimal places.',
      );
      return;
    }
    const [whole, fraction = ''] = amount.split('.');
    setBusy(true);
    setError('');
    try {
      await staffRequest('orders/' + order.id + '/confirm-wise-payment', {
        operation_id: operation,
        confirmed,
        amount_minor: Number(whole) * 100 + Number(fraction.padEnd(2, '0')),
        currency: 'GBP',
        payment_reference: reference,
        receipt_reference: receipt,
      });
      setOpen(false);
      await onSaved();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Payment confirmation failed.');
    } finally {
      setBusy(false);
    }
  }
  return (
    <section className="panel" aria-label="Wise payment">
      <h3>Wise bank transfer</h3>
      {order.wise_confirmation ? (
        <p>
          Receipt confirmed by {order.wise_confirmation.confirmed_by} on{' '}
          {new Date(order.wise_confirmation.confirmed_at).toLocaleString()}.
          {order.wise_confirmation.receipt_reference && (
            <>
              {' '}
              Wise transaction ID:{' '}
              <strong>{order.wise_confirmation.receipt_reference}</strong>.
            </>
          )}
        </p>
      ) : (
        <p>
          Awaiting manual verification of funds received. Expected:{' '}
          {money(order.total_minor)} / reference {order.reference}.
        </p>
      )}
      {order.payment_state === 'awaiting_bank_transfer' && canConfirm && (
        <Button onClick={() => setOpen(true)}>
          Confirm Wise payment received
        </Button>
      )}
      <Modal
        open={open}
        title="Confirm Wise payment received"
        onClose={() => {
          if (!busy) setOpen(false);
        }}
      >
        <p>
          Open Wise and check that the funds have arrived. Match this order’s
          exact amount and reference before confirming. Customer screenshots
          alone are not proof of receipt.
        </p>
        <p>
          <strong>
            {order.reference} — {money(order.total_minor)}
          </strong>
        </p>
        {error && (
          <p role="alert" className="auth-error">
            {error}
          </p>
        )}
        <form
          onSubmit={(e) => {
            e.preventDefault();
            void save();
          }}
        >
          <Field
            label="Amount received (GBP)"
            inputMode="decimal"
            value={amount}
            required
            onChange={(e) => setAmount(e.target.value)}
          />
          <Field
            label="Payment reference received"
            value={reference}
            maxLength={40}
            required
            onChange={(e) => setReference(e.target.value)}
          />
          <Field
            label="Wise transaction ID"
            value={receipt}
            minLength={3}
            maxLength={120}
            required
            hint="Use the unique transaction ID shown in Wise, not the order reference."
            onChange={(e) => setReceipt(e.target.value)}
          />
          <label className="checkout-check">
            <input
              type="checkbox"
              checked={confirmed}
              onChange={(e) => setConfirmed(e.target.checked)}
              required
            />
            I checked Wise and the exact GBP amount and order reference have
            been received.
          </label>
          <div className="order-actions">
            <Button type="submit" busy={busy} disabled={!confirmed}>
              Confirm received payment
            </Button>
            <Button
              type="button"
              variant="secondary"
              disabled={busy}
              onClick={() => setOpen(false)}
            >
              Cancel
            </Button>
          </div>
        </form>
      </Modal>
    </section>
  );
}
