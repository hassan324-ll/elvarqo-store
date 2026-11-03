import { useState } from 'react';
import { Button, Field, Select, Modal } from '../ui/components';
import { staffRequest } from '../lib/staff-api';
import type { Product, Page } from '../catalogue/types';
import { type Address, type Snapshot, type Row, money } from './types';
const empty: Address = {
  first_name: '',
  last_name: '',
  line1: '',
  line2: '',
  city: '',
  postcode: '',
  country: 'GB',
};
export default function ManualOrder({
  onCreated,
  onClose,
}: {
  onCreated: (id: string) => Promise<void>;
  onClose: () => void;
}) {
  const [search, setSearch] = useState('');
  const [products, setProducts] = useState<Product[]>([]);
  const [items, setItems] = useState<
    { variant_id: number; quantity: number; label: string }[]
  >([]);
  const [email, setEmail] = useState('');
  const [address, setAddress] = useState<Address>(empty);
  const [billing, setBilling] = useState<Address>(empty);
  const [same, setSame] = useState(true);
  const [delivery, setDelivery] = useState('standard');
  const [discount, setDiscount] = useState('');
  const [review, setReview] = useState<{
    snapshot: Snapshot;
    quote_hash: string;
  } | null>(null);
  const [operation, setOperation] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const details = {
    email,
    address,
    ...(same ? {} : { billing_address: billing }),
    delivery_method: delivery,
    discount_code: discount,
    items: items.map(({ variant_id, quantity }) => ({ variant_id, quantity })),
  };
  async function find() {
    setBusy(true);
    setError('');
    try {
      setProducts(
        (
          await staffRequest<Page<Product>>(
            'catalogue/products?status=published&q=' +
              encodeURIComponent(search),
          )
        ).items,
      );
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Search unavailable.');
    } finally {
      setBusy(false);
    }
  }
  async function quote() {
    setBusy(true);
    setError('');
    try {
      setReview(await staffRequest('orders/manual/quote', details));
      setOperation(crypto.randomUUID());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Review unavailable.');
    } finally {
      setBusy(false);
    }
  }
  async function create() {
    if (!review) return;
    setBusy(true);
    setError('');
    try {
      const o = await staffRequest<Row>('orders/manual/create', {
        ...details,
        quote_hash: review.quote_hash,
        operation_id: operation,
        confirmed: true,
      });
      await onCreated(o.id);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Order could not be created.');
    } finally {
      setBusy(false);
    }
  }
  function fields(label: string, value: Address, set: (a: Address) => void) {
    return (
      <fieldset>
        <legend>{label}</legend>
        <div className="order-form-grid">
          {(
            [
              'first_name',
              'last_name',
              'line1',
              'line2',
              'city',
              'postcode',
            ] as const
          ).map((k) => (
            <Field
              key={k}
              label={
                label +
                ' / ' +
                {
                  first_name: 'First name',
                  last_name: 'Last name',
                  line1: 'Address line 1',
                  line2: 'Address line 2',
                  city: 'City',
                  postcode: 'Postcode',
                }[k]
              }
              required={k !== 'line2'}
              value={value[k]}
              maxLength={k === 'postcode' ? 10 : 160}
              onChange={(e) => set({ ...value, [k]: e.target.value })}
            />
          ))}
        </div>
        <p>United Kingdom / GB / Sandbox market</p>
      </fieldset>
    );
  }
  return (
    <section className="panel order-workspace">
      <h1>Create manual order</h1>
      <p>
        Unpaid order with a one-use secure payment invitation. Stripe capture is
        deferred; this order cannot be dispatched until payment is verified.
        Test delivery/tax rates only.
      </p>
      {error && <p role="alert">{error}</p>}
      <form
        onSubmit={(e) => {
          e.preventDefault();
          void find();
        }}
      >
        <Field
          label="Find published product or SKU"
          value={search}
          maxLength={100}
          onChange={(e) => setSearch(e.target.value)}
        />
        <Button type="submit" busy={busy}>
          Find variants
        </Button>
      </form>
      <ul className="manual-products">
        {products.flatMap((p) =>
          p.variants
            .filter((v) => v.active)
            .map((v) => (
              <li key={v.id}>
                {p.title} / {v.sku} / {v.colour} / {v.size} /{' '}
                {money(v.price_minor)} / {v.available} available{' '}
                <Button
                  variant="secondary"
                  disabled={busy || items.some((i) => i.variant_id === v.id)}
                  onClick={() =>
                    setItems([
                      ...items,
                      {
                        variant_id: v.id!,
                        quantity: 1,
                        label: p.title + ' / ' + v.sku,
                      },
                    ])
                  }
                >
                  Add {v.sku}
                </Button>
              </li>
            )),
        )}
      </ul>
      <form
        className="order-form"
        onSubmit={(e) => {
          e.preventDefault();
          void quote();
        }}
      >
        {items.map((l) => (
          <div key={l.variant_id}>
            <Field
              label={'Quantity / ' + l.label}
              type="number"
              min={1}
              max={10}
              value={l.quantity}
              onChange={(e) =>
                setItems(
                  items.map((i) =>
                    i.variant_id === l.variant_id
                      ? { ...i, quantity: Number(e.target.value) }
                      : i,
                  ),
                )
              }
            />
            <Button
              variant="secondary"
              onClick={() =>
                setItems(items.filter((i) => i.variant_id !== l.variant_id))
              }
            >
              Remove {l.label}
            </Button>
          </div>
        ))}
        <Field
          label="Guest email"
          type="email"
          required
          maxLength={254}
          value={email}
          onChange={(e) => setEmail(e.target.value)}
        />
        {fields('Shipping address', address, setAddress)}
        <label className="order-checkbox">
          <input
            type="checkbox"
            checked={same}
            onChange={(e) => setSame(e.target.checked)}
          />
          Billing matches shipping
        </label>
        {!same && fields('Billing address', billing, setBilling)}
        <Select
          label="Customer delivery rate"
          value={delivery}
          onChange={(e) => setDelivery(e.target.value)}
        >
          <option value="standard">Standard test rate / £4.95</option>
          <option value="express">Express test rate / £8.95</option>
        </Select>
        <Field
          label="Test discount code"
          value={discount}
          maxLength={40}
          onChange={(e) => setDiscount(e.target.value)}
        />
        <div className="order-actions">
          <Button type="submit" busy={busy} disabled={!items.length}>
            Review manual order
          </Button>
          <Button variant="secondary" disabled={busy} onClick={onClose}>
            Back to orders
          </Button>
        </div>
      </form>
      <Modal
        open={!!review}
        title="Review unpaid manual order"
        onClose={() => {
          if (!busy) setReview(null);
        }}
      >
        {review && (
          <>
            <ul>
              {review.snapshot.items.map((i) => (
                <li key={i.variant_id}>
                  {i.title} / {i.colour} / {i.size} / Qty {i.quantity}:{' '}
                  {money(i.line_minor)}
                </li>
              ))}
            </ul>
            <p>
              {Object.values(review.snapshot.shipping_address)
                .filter(Boolean)
                .join(', ')}
            </p>
            <p>
              Subtotal {money(review.snapshot.subtotal_minor)} / Test discount{' '}
              {money(review.snapshot.discount_minor)} / Delivery{' '}
              {money(review.snapshot.shipping_minor)} / Test tax{' '}
              {money(review.snapshot.tax_minor)}
            </p>
            <p>
              Total <strong>{money(review.snapshot.total_minor)}</strong>
            </p>
            <p>
              This reserves stock and queues a private sandbox payment
              invitation. It records no payment. No external email is sent.
            </p>
            <Button busy={busy} onClick={() => void create()}>
              Create unpaid order and invitation
            </Button>
            <Button
              variant="secondary"
              disabled={busy}
              onClick={() => setReview(null)}
            >
              Change details
            </Button>
          </>
        )}
      </Modal>
    </section>
  );
}
