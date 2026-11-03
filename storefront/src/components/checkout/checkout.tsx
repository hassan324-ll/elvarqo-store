'use client';
import { useEffect, useState } from 'react';
import Link from 'next/link';
import { Button, Field } from '../../ui/components';
import { money } from '../../lib/catalogue-types';
import {
  commerce,
  type Address,
  type Details,
  type Quote,
  type Order,
  type Snapshot,
} from '../../lib/commerce';
import { useBag } from './bag-provider';
const empty: Address = {
  first_name: '',
  last_name: '',
  line1: '',
  line2: '',
  city: '',
  postcode: '',
  country: 'GB',
};
export function Totals({ s }: { s: Snapshot }) {
  return (
    <section className="checkout-summary">
      <h2>Your order</h2>
      <ul>
        {s.items.map((l) => (
          <li key={l.variant_id}>
            {l.title} / {l.colour} / {l.size} / Qty {l.quantity}{' '}
            <strong>{money(l.line_minor || 0)}</strong>
          </li>
        ))}
      </ul>
      <dl>
        {[
          ['Subtotal', s.subtotal_minor],
          ['Test discount', -s.discount_minor],
          ['Test delivery', s.shipping_minor],
          ['Test tax', s.tax_minor],
          ['Total', s.total_minor],
        ].map(([label, value]) => (
          <div key={label}>
            <dt>{label}</dt>
            <dd>{money(Number(value))}</dd>
          </div>
        ))}
      </dl>
      <p className="shop-notice">
        Sandbox only. Delivery prices and tax are test assumptions awaiting
        merchant review.
      </p>
    </section>
  );
}
function AddressFields({
  label,
  value,
  onChange,
}: {
  label: string;
  value: Address;
  onChange: (a: Address) => void;
}) {
  return (
    <fieldset className="checkout-address">
      <legend>{label}</legend>
      {(
        [
          ['first_name', 'First name'],
          ['last_name', 'Last name'],
          ['line1', 'Address line 1'],
          ['line2', 'Address line 2 (optional)'],
          ['city', 'City'],
          ['postcode', 'Postcode'],
        ] as const
      ).map(([name, title]) => (
        <Field
          key={name}
          name={label + '-' + name}
          label={title}
          value={value[name]}
          required={name !== 'line2'}
          maxLength={name === 'postcode' ? 10 : 160}
          autoComplete={
            (label === 'Delivery address' ? 'shipping' : 'billing') +
            ' ' +
            {
              first_name: 'given-name',
              last_name: 'family-name',
              line1: 'address-line1',
              line2: 'address-line2',
              city: 'address-level2',
              postcode: 'postal-code',
            }[name]
          }
          onChange={(e) => onChange({ ...value, [name]: e.target.value })}
        />
      ))}
      <p>Country: United Kingdom (GB)</p>
    </fieldset>
  );
}
export default function GuestCheckout({ retry }: { retry?: string }) {
  const { bag, loading, error: bagError } = useBag();
  const [step, setStep] = useState(1);
  const [email, setEmail] = useState('');
  const [address, setAddress] = useState<Address>(empty);
  const [billing, setBilling] = useState<Address>(empty);
  const [same, setSame] = useState(true);
  const [delivery, setDelivery] = useState('standard');
  const [code, setCode] = useState('');
  const [quote, setQuote] = useState<Quote | null>(null);
  const [terms, setTerms] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [attempt, setAttempt] = useState('');
  const [restoring, setRestoring] = useState(!!retry);
  useEffect(() => {
    if (!retry) return;
    let active = true;
    void commerce<Order>('orders/' + retry)
      .then((o) => {
        if (!active) return;
        if (!['expired', 'failed'].includes(o.payment_state))
          throw new Error(
            'Resume the existing payment before starting another checkout.',
          );
        const s = o.snapshot;
        setEmail(s.contact.email);
        setAddress(s.shipping_address);
        setBilling(s.billing_address);
        setSame(
          JSON.stringify(s.shipping_address) ===
            JSON.stringify(s.billing_address),
        );
        setDelivery(s.delivery.code);
        setCode(s.discount_code || '');
      })
      .catch((e) => {
        if (active)
          setError(
            e instanceof Error
              ? e.message
              : 'Previous details could not be restored. Enter them below.',
          );
      })
      .finally(() => {
        if (active) setRestoring(false);
      });
    return () => {
      active = false;
    };
  }, [retry]);
  if (restoring)
    return (
      <p className="shop-recovery" role="status">
        Restoring your saved checkout details…
      </p>
    );

  if (loading && !bag)
    return (
      <p className="shop-recovery" role="status">
        Loading your bag…
      </p>
    );
  if (!bag || !bag.items.length)
    return (
      <section className="shop-recovery">
        <h1>Your bag is empty.</h1>
        {bagError && <p role="alert">{bagError}</p>}
        <Link href="/bag">Review your bag</Link>
      </section>
    );
  if (bag.active_order_id)
    return (
      <section className="shop-recovery">
        <h1>Continue your existing checkout</h1>
        <Link href={'/checkout/confirmation?order=' + bag.active_order_id}>
          Resume or check payment
        </Link>
      </section>
    );
  const wise = bag.payment_provider === 'wise_bank_transfer';
  const details: Details = {
    revision: bag.revision,
    email,
    address,
    ...(!same ? { billing_address: billing } : {}),
    delivery_method: delivery,
    discount_code: code,
  };
  async function review() {
    setBusy(true);
    setError('');
    try {
      const q = await commerce<Quote>('quote', details);
      setQuote(q);
      setAttempt(crypto.randomUUID());
      setStep(3);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not review your order.');
    } finally {
      setBusy(false);
    }
  }
  async function pay() {
    if (!quote || !terms) return;
    setBusy(true);
    setError('');
    try {
      const o = await commerce<Order>('checkout', {
        ...details,
        attempt_id: attempt,
        quote_hash: quote.quote_hash,
        terms: true,
      });
      window.location.assign('/checkout/confirmation?order=' + o.id);
    } catch (e) {
      setError(
        e instanceof Error ? e.message : 'Payment could not be started.',
      );
    } finally {
      setBusy(false);
    }
  }
  return (
    <section className="commerce-page">
      <p className="eyebrow">
        Guest checkout / {wise ? 'Wise bank transfer' : 'Stripe sandbox'}
      </p>
      <h1>
        {step === 1
          ? 'Your details'
          : step === 2
            ? 'Choose delivery'
            : 'Payment & review'}
      </h1>
      <p aria-label="Checkout progress">
        1 Contact & address / 2 Delivery / 3 Payment
      </p>
      <p className="shop-notice">
        Test checkout only. No real card details. Delivery and tax are test
        assumptions.
      </p>
      {error && (
        <p role="alert" className="shop-error">
          {error}
        </p>
      )}
      <div className="checkout-grid">
        <div>
          {step === 1 && (
            <form
              className="commerce-flow"
              onSubmit={(e) => {
                e.preventDefault();
                setError('');
                setStep(2);
              }}
            >
              <Field
                label="Email address"
                type="email"
                autoComplete="email"
                required
                maxLength={254}
                value={email}
                onChange={(e) => setEmail(e.target.value)}
              />
              <AddressFields
                label="Delivery address"
                value={address}
                onChange={setAddress}
              />
              <label className="checkout-check">
                <input
                  type="checkbox"
                  checked={same}
                  onChange={(e) => setSame(e.target.checked)}
                />
                Billing address is the same as delivery
              </label>
              {!same && (
                <AddressFields
                  label="Billing address"
                  value={billing}
                  onChange={setBilling}
                />
              )}
              <Button type="submit">Continue to delivery</Button>
            </form>
          )}
          {step === 2 && (
            <div className="commerce-flow">
              <p>
                Deliver to {address.first_name} {address.last_name},{' '}
                {address.city}, {address.postcode}
              </p>
              <fieldset className="delivery-methods">
                <legend>Delivery service</legend>
                {Object.entries(bag.rates).map(([key, r]) => (
                  <label key={key}>
                    <input
                      type="radio"
                      name="delivery"
                      value={key}
                      checked={delivery === key}
                      onChange={() => setDelivery(key)}
                    />
                    <strong>
                      {r.name} / {money(r.minor)}
                    </strong>
                    <span>{r.estimate}</span>
                  </label>
                ))}
              </fieldset>
              <Field
                label="Test discount code (optional)"
                value={code}
                maxLength={40}
                onChange={(e) => setCode(e.target.value)}
              />
              <p>
                Test code TEST10: 10% off merchandise when subtotal is at least
                £50. Test fixture only.
              </p>
              <Button busy={busy} onClick={() => void review()}>
                Review final total
              </Button>
              <Button
                variant="secondary"
                disabled={busy}
                onClick={() => setStep(1)}
              >
                Change address
              </Button>
            </div>
          )}
          {step === 3 && quote && (
            <div className="commerce-flow">
              <h2>Delivery & billing</h2>
              <p>
                {address.first_name} {address.last_name}
                <br />
                {address.line1}, {address.line2}
                <br />
                {address.city}, {address.postcode}, GB
              </p>
              <p>
                {same
                  ? 'Billing address matches delivery.'
                  : `Billing: ${billing.line1}, ${billing.city}, ${billing.postcode}`}
              </p>
              <p>
                {quote.snapshot.delivery.name}.{' '}
                {wise
                  ? 'Place your order to receive GBP bank-transfer instructions and a unique payment reference. Your order remains unpaid until store staff confirm receipt in Wise.'
                  : 'Payment card details are collected only on Stripe’s hosted page.'}
              </p>
              <label className="checkout-check">
                <input
                  type="checkbox"
                  checked={terms}
                  onChange={(e) => setTerms(e.target.checked)}
                />
                I accept the <Link href="/terms">terms of purchase</Link>.
              </label>
              {!bag.checkout_enabled && (
                <p role="status">
                  {wise
                    ? 'Wise bank-transfer checkout is not configured yet.'
                    : 'Sandbox payments are unavailable.'}{' '}
                  Your bag is saved; no order or payment has been created.
                </p>
              )}
              <Button
                busy={busy}
                disabled={!terms || !bag.checkout_enabled}
                onClick={() => void pay()}
              >
                {wise
                  ? 'Place order — pay by bank transfer'
                  : 'Continue to Stripe'}{' '}
                / {money(quote.snapshot.total_minor)}
              </Button>
              <Button
                variant="secondary"
                disabled={busy}
                onClick={() => {
                  setQuote(null);
                  setStep(2);
                  setTerms(false);
                }}
              >
                Change delivery or discount
              </Button>
            </div>
          )}
        </div>
        {quote && step === 3 ? (
          <Totals s={quote.snapshot} />
        ) : (
          <aside className="checkout-summary">
            <h2>Your pieces</h2>
            {bag.items.map((l) => (
              <p key={l.variant_id}>
                {l.title} / {l.colour} / {l.size} / Qty {l.quantity} —{' '}
                {money(l.line_minor || 0)}
              </p>
            ))}
            <p>Final server total will be shown before payment.</p>
          </aside>
        )}
      </div>
    </section>
  );
}
