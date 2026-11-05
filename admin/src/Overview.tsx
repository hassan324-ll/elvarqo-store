import { useCallback, useEffect, useState } from 'react';
import { Badge, Button, DataTable, EmptyState } from './ui/components';
import { staffRequest } from './lib/staff-api';

type OverviewData = {
  catalogue: null | {
    products: number;
    published: number;
    drafts: number;
    variants: number;
    available_units: number;
    stock_tracked?: boolean;
    recent: { id: number; title: string; status: string }[];
  };
  orders: null | {
    total: number;
    recent: {
      id: string;
      reference: string;
      payment_state: string;
      fulfilment_state: string;
      total_minor: number;
      currency: string;
    }[];
  };
  paid_totals:
    null | { currency: string; amount_minor: number; orders: number }[];
};
function money(amount: number, currency = 'GBP') {
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency }).format(
    amount / 100,
  );
}
export default function Overview() {
  const [data, setData] = useState<OverviewData | null>(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(true);
  const load = useCallback(async () => {
    setBusy(true);
    setError('');
    try {
      setData(await staffRequest<OverviewData>('overview'));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Overview unavailable.');
    } finally {
      setBusy(false);
    }
  }, []);
  useEffect(() => {
    let active = true;
    staffRequest<OverviewData>('overview')
      .then((result) => {
        if (active) setData(result);
      })
      .catch((e: unknown) => {
        if (active)
          setError(e instanceof Error ? e.message : 'Overview unavailable.');
      })
      .finally(() => {
        if (active) setBusy(false);
      });
    return () => {
      active = false;
    };
  }, []);
  const catalogue = data?.catalogue;
  const orders = data?.orders;
  const metrics: [string, string | number, string][] = [];
  if (catalogue)
    metrics.push(
      ['Products', catalogue.products, 'All non-archived products'],
      [
        'Published products',
        catalogue.published,
        'Published in your catalogue',
      ],
      ['Draft products', catalogue.drafts, 'Not yet published'],
      ['Active variants', catalogue.variants, 'Sizes and colours'],
      [
        'Available stock',
        catalogue.stock_tracked === false
          ? 'In stock'
          : catalogue.available_units,
        catalogue.stock_tracked === false
          ? 'Always available; quantities not counted'
          : 'Active locations; reservations deducted',
      ],
    );
  if (orders)
    metrics.push([
      'Orders',
      orders.total,
      'All recorded orders, including unpaid',
    ]);
  return (
    <>
      <header className="admin-page-heading">
        <div>
          <p className="eyebrow">ELVARQO / STORE OPERATIONS</p>
          <h1>Store overview</h1>
          <p className="muted">Your database records · All time</p>
        </div>
        <Button busy={busy} onClick={() => void load()}>
          Refresh overview
        </Button>
      </header>
      {error && (
        <p role="alert" className="auth-error">
          {error}{' '}
          {data
            ? 'Previously loaded figures are shown; refresh to retry.'
            : 'No figures could be loaded.'}
        </p>
      )}
      {busy && <p role="status">Loading store records…</p>}
      {data && (
        <>
          <div className="metric-grid">
            {metrics.map(([label, value, hint]) => (
              <article className="metric" key={label}>
                <p>{label}</p>
                <h2>{value}</h2>
                <small>{hint}</small>
              </article>
            ))}
          </div>
          {data.paid_totals !== null && (
            <section className="panel">
              <h2>Recorded paid order totals</h2>
              <p className="muted">
                Gross order totals, including delivery and tax, before refunds.
                Unpaid orders and exchange credit are excluded. Sandbox payments
                are test records; this is not a net sales report.
              </p>
              {data.paid_totals.length ? (
                data.paid_totals.map((t) => (
                  <p key={t.currency}>
                    {money(Number(t.amount_minor), t.currency)} · {t.orders}{' '}
                    paid orders ({t.currency})
                  </p>
                ))
              ) : (
                <p>{money(0)} · No paid orders</p>
              )}
            </section>
          )}
          {catalogue && (
            <section className="panel orders-panel">
              <div className="card-heading">
                <h2>Recently added products</h2>
                <a href="?view=products">Manage products →</a>
              </div>
              {catalogue.recent.length ? (
                <DataTable
                  caption="Latest 10 non-archived products"
                  columns={['Product', 'Status']}
                  rows={catalogue.recent.map((p) => [
                    <a key="product" href={`?view=products&product=${p.id}`}>
                      {p.title}
                    </a>,
                    <Badge
                      key="status"
                      tone={p.status === 'published' ? 'success' : 'warning'}
                    >
                      {p.status}
                    </Badge>,
                  ])}
                />
              ) : (
                <EmptyState title="No products yet">
                  Add your first product in Products. It will appear here,
                  including while it is a draft.
                </EmptyState>
              )}
            </section>
          )}
          {orders && (
            <section className="panel orders-panel">
              <div className="card-heading">
                <h2>Recent orders</h2>
                <a href="?view=orders">Manage orders →</a>
              </div>
              {orders.recent.length ? (
                <DataTable
                  caption="Latest 10 recorded orders"
                  columns={['Reference', 'Payment', 'Fulfilment', 'Total']}
                  rows={orders.recent.map((o) => [
                    o.reference,
                    o.payment_state.replaceAll('_', ' '),
                    o.fulfilment_state.replaceAll('_', ' '),
                    money(o.total_minor, o.currency),
                  ])}
                />
              ) : (
                <EmptyState title="No orders yet">
                  New orders will appear here when they are created.
                </EmptyState>
              )}
            </section>
          )}
          {!catalogue && !orders && data.paid_totals === null && (
            <EmptyState title="No overview permissions">
              Your staff role does not have access to catalogue, order or
              finance summaries.
            </EmptyState>
          )}
          <p className="muted">
            Figures load when you open Overview. Use Refresh overview to load
            changes made in another tab. Adding products changes catalogue
            totals; it does not create sales.
          </p>
        </>
      )}
    </>
  );
}
