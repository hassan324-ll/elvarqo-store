'use client';
import { Button } from '../../ui/components';
import { useState, useEffect } from 'react';
export type ReturnItem = {
  id: number;
  title: string;
  size: string;
  colour: string;
  quantity: number;
  eligible_quantity: number;
  eligible_credit_minor: number;
  exchange_options: { id: number; sku: string; price_minor: number }[];
};
export type ReturnRecord = {
  id: string;
  quantity: number;
  credit_minor: number;
  state: string;
  resolution: string;
  reason: string;
  refundable_minor: number;
  inspection: { outcome: string; reason: string } | null;
  exchanges: {
    reference: string;
    state: string;
    difference_minor: number;
    replacement_order_id: string;
  }[];
  refunds: {
    id: string;
    amount_minor: number;
    state: string;
    last_error: string | null;
  }[];
  timeline: { kind: string; created_at: string }[];
};
export type AfterSales = {
  policy: string;
  items: ReturnItem[];
  returns: ReturnRecord[];
};
export const money = (n: number) =>
  new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(
    n / 100,
  );
export function ReturnPanel({
  data,
  submit,
}: {
  data: AfterSales;
  submit: (body: Record<string, unknown>) => Promise<void>;
}) {
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState('');
  const [item, setItem] = useState('');
  const [resolution, setResolution] = useState('refund');
  const [operation, setOperation] = useState<string | null>(null);
  const selected = data.items.find((i) => String(i.id) === item);
  return (
    <section className="account-card">
      <h2>Returns & exchanges</h2>
      <p>{data.policy}</p>
      {data.items.every((i) => !i.eligible_quantity) ? (
        <p>
          No delivered units currently qualify. Already claimed units and
          deliveries outside the test window are excluded.
        </p>
      ) : (
        <form
          onChange={() => setOperation(null)}
          onSubmit={async (e) => {
            e.preventDefault();
            if (busy) return;
            setBusy(true);
            setNotice('');
            const f = new FormData(e.currentTarget);
            const key = operation || crypto.randomUUID();
            setOperation(key);
            try {
              await submit({
                operation_id: key,
                order_item_id: Number(item),
                quantity: Number(f.get('quantity')),
                reason: f.get('reason'),
                resolution,
                exchange_variant_id:
                  resolution === 'exchange'
                    ? Number(f.get('exchange_variant_id'))
                    : null,
                comments: f.get('comments'),
                confirmed: f.get('confirmed') === 'on',
              });
              setNotice(
                'Request saved. Await return instructions before sending. Refund credit requires inspection.',
              );
              setOperation(null);
            } catch (error) {
              setNotice((error as Error).message);
            } finally {
              setBusy(false);
            }
          }}
        >
          <label>
            Delivered item
            <select
              aria-label="Delivered item"
              required
              value={item}
              onChange={(e) => setItem(e.target.value)}
            >
              <option value="">Choose an eligible item</option>
              {data.items
                .filter((i) => i.eligible_quantity)
                .map((i) => (
                  <option key={i.id} value={i.id}>
                    {i.title} / {i.colour} / {i.size} ({i.eligible_quantity}{' '}
                    eligible)
                  </option>
                ))}
            </select>
          </label>
          <label>
            Quantity
            <input
              name="quantity"
              type="number"
              min="1"
              max={selected?.eligible_quantity || 1}
              defaultValue="1"
              required
            />
          </label>
          <label>
            Reason
            <select name="reason" aria-label="Reason">
              <option value="size">Size / fit</option>
              <option value="changed_mind">Changed my mind</option>
              <option value="damaged">Damaged</option>
              <option value="wrong_item">Wrong item</option>
            </select>
          </label>
          <label>
            Resolution
            <select
              aria-label="Resolution"
              value={resolution}
              onChange={(e) => setResolution(e.target.value)}
            >
              <option value="refund">Refund review</option>
              <option value="exchange">Exchange</option>
            </select>
          </label>
          {resolution === 'exchange' && (
            <label>
              Replacement variant
              <select
                name="exchange_variant_id"
                aria-label="Replacement variant"
                required
              >
                <option value="">Choose a replacement</option>
                {selected?.exchange_options.map((v) => (
                  <option key={v.id} value={v.id}>
                    {v.sku} / {money(v.price_minor)} per unit
                  </option>
                ))}
              </select>
              <small>
                Stock is checked now and again after inspection. A price top-up
                remains unpaid while Stripe is deferred.
              </small>
            </label>
          )}
          <label>
            Comments
            <textarea name="comments" maxLength={1500} />
          </label>
          <label className="account-check">
            <input type="checkbox" name="confirmed" required />I understand this
            is the test-only inspection and refund policy.
          </label>
          <Button disabled={busy} type="submit">
            {busy ? 'Saving…' : 'Request return / exchange'}
          </Button>
        </form>
      )}
      {notice && <p role="status">{notice}</p>}
      {data.returns.map((r) => (
        <article className="account-record" key={r.id}>
          <h3>
            {r.quantity} unit(s) / {r.state}
          </h3>
          <p>
            {r.resolution} / {r.reason} / merchandise credit{' '}
            {money(r.credit_minor)}
          </p>
          {r.inspection && (
            <p>
              Inspection: {r.inspection.outcome}. {r.inspection.reason}
            </p>
          )}
          {r.exchanges.map((e) => (
            <p key={e.replacement_order_id}>
              Replacement {e.reference}: {e.state}. Difference{' '}
              {money(e.difference_minor)}.{' '}
              {e.state === 'awaiting_top_up'
                ? 'Payment integration deferred; this replacement cannot ship.'
                : ''}
            </p>
          ))}
          {r.refunds.map((f) => (
            <p key={f.id}>
              Local refund sandbox: {money(f.amount_minor)} / {f.state}.{' '}
              {f.last_error} No real money moved.
            </p>
          ))}
          <ol>
            {r.timeline.map((t, i) => (
              <li key={i}>
                {t.kind} — {t.created_at}
              </li>
            ))}
          </ol>
        </article>
      ))}
    </section>
  );
}
export function GuestReturns() {
  const [data, setData] = useState<AfterSales | null>(null);
  const [error, setError] = useState('');
  async function load() {
    const r = await fetch('/api/tracking/returns', { cache: 'no-store' });
    const d = await r.json();
    if (!r.ok)
      throw Error(d.error?.message || 'Open a verified tracking link first.');
    setData(d.data);
  }
  useEffect(() => {
    const t = setTimeout(
      () => void load().catch((e) => setError(e.message)),
      0,
    );
    return () => clearTimeout(t);
  }, []);
  return (
    <div className="account-page">
      <h1>Guest returns</h1>
      <p>
        Open your verified <a href="/track">order tracking link</a> to request
        or review a return. An account is optional.
      </p>
      {error && <p role="status">{error}</p>}
      {data && (
        <ReturnPanel
          data={data}
          submit={async (body) => {
            const r = await fetch('/api/tracking/returns', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(body),
            });
            const d = await r.json();
            if (!r.ok)
              throw Error(
                Object.values(d.error?.details || {})
                  .flat()
                  .join(' ') || d.error?.message,
              );
            await load();
          }}
        />
      )}
    </div>
  );
}
