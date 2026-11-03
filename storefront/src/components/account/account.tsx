'use client';
import { Button } from '../../ui/components';
import { useState, useEffect, useRef, type FormEvent } from 'react';
import { accountRequest } from './api';
import { useAccount } from './provider';
import { ReturnPanel, money, type AfterSales } from './returns';
type Order = {
  id: string;
  reference: string;
  payment_state: string;
  fulfilment_state: string;
  order_state: string;
  total_minor: number;
  currency: string;
};
type Detail = Order & {
  snapshot: {
    items: {
      title: string;
      colour: string;
      size: string;
      quantity: number;
      line_minor: number;
    }[];
    shipping_address: Record<string, string>;
    shipping_minor: number;
    discount_minor: number;
    tax_minor: number;
  };
  after_sales: AfterSales;
  tracking: {
    shipments: { id: string; state: string; tracking_number: string }[];
  };
};
type Address = {
  id: string;
  version: number;
  is_default: boolean;
  address: Record<string, string>;
};
const addressFields = [
  'first_name',
  'last_name',
  'line1',
  'line2',
  'city',
  'postcode',
];
type Fragment = { token: string; purpose: string };
export default function Account() {
  const { customer } = useAccount();
  const [fragment, setFragment] = useState<{
    token: string;
    purpose: string;
  } | null>(null);
  const captured = useRef<{ token: string; purpose: string } | null>(null);
  useEffect(() => {
    function capture() {
      const f = new URLSearchParams(location.hash.slice(1));
      if (f.get('token')) {
        captured.current = {
          token: f.get('token')!,
          purpose: f.get('purpose') || 'verify',
        };
        history.replaceState(null, '', location.pathname);
      }
      if (captured.current) setFragment(captured.current);
    }
    const t = setTimeout(capture, 0);
    window.addEventListener('hashchange', capture);
    return () => {
      clearTimeout(t);
      window.removeEventListener('hashchange', capture);
    };
  }, []);
  return (
    <AccountContent
      key={customer?.id ?? 'guest'}
      fragment={fragment}
      onConsumed={() => {
        setFragment(null);
        captured.current = null;
      }}
    />
  );
}
function AccountContent({
  fragment,
  onConsumed,
}: {
  fragment: Fragment | null;
  onConsumed: () => void;
}) {
  const { customer, ready, refresh } = useAccount();
  const [mode, setMode] = useState('login');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);
  const [orders, setOrders] = useState<{
    items: Order[];
    page: number;
    last_page: number;
  } | null>(null);
  const [detail, setDetail] = useState<Detail | null>(null);
  const [addresses, setAddresses] = useState<Address[]>([]);
  const [editing, setEditing] = useState<Address | null>(null);
  async function load(page = 1) {
    setOrders(await accountRequest('orders?page=' + page));
    setAddresses(await accountRequest('addresses'));
  }
  useEffect(() => {
    const c = customer?.id;
    const t = setTimeout(() => {
      if (c) void load().catch((e) => setNotice(e.message));
      else {
        setOrders(null);
        setDetail(null);
        setAddresses([]);
        setEditing(null);
      }
    }, 0);
    return () => clearTimeout(t);
  }, [customer?.id]);
  async function run(action: () => Promise<void>) {
    if (busy) return;
    setBusy(true);
    setNotice('');
    try {
      await action();
    } catch (e) {
      setNotice((e as Error).message);
    } finally {
      setBusy(false);
    }
  }
  function values(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    return Object.fromEntries(new FormData(e.currentTarget));
  }
  return (
    <div className="account-page">
      <p className="eyebrow">ELVARQO / YOUR ACCOUNT</p>
      <h1>{customer ? `Welcome, ${customer.name}` : 'Your ELVARQO account'}</h1>
      <p>
        <a href="/shop">Continue shopping</a> ·{' '}
        <a href="/track">Guest order tracking</a> ·{' '}
        <a href="/returns">Guest returns</a>
      </p>
      <p>
        Guest checkout remains available. Account and order email links are
        saved as private local test copies; external email delivery is pending.
      </p>
      {notice && (
        <p className="account-notice" role="status">
          {notice}
        </p>
      )}
      {fragment && (
        <section className="account-card">
          <h2>
            {fragment.purpose === 'reset'
              ? 'Reset your password'
              : 'Complete email verification'}
          </h2>
          <form
            onSubmit={(e) => {
              const f = values(e);
              void run(async () => {
                await accountRequest('redeem', {
                  ...fragment,
                  ...(fragment.purpose === 'reset'
                    ? { password: f.password }
                    : {}),
                });
                setMode('login');
                onConsumed();
                await refresh();
                if (customer) await load();
                setNotice('Verification completed. Sign in to continue.');
              });
            }}
          >
            {fragment.purpose === 'reset' && (
              <label>
                New password
                <input
                  type="password"
                  name="password"
                  autoComplete="new-password"
                  minLength={14}
                  maxLength={128}
                  required
                />
              </label>
            )}
            {['claim', 'email'].includes(fragment.purpose) && !customer ? (
              <p>
                Sign in to the account that requested this link, then confirm.
              </p>
            ) : (
              <Button disabled={busy}>Confirm {fragment.purpose}</Button>
            )}
          </form>
        </section>
      )}
      {!ready ? (
        <p>Loading account…</p>
      ) : !customer ? (
        <section className="account-card">
          <h2>
            {mode === 'register'
              ? 'Create account'
              : mode === 'recover'
                ? 'Recover account'
                : 'Sign in'}
          </h2>
          <nav className="account-tabs">
            {['login', 'register', 'recover'].map((m) => (
              <Button
                type="button"
                key={m}
                aria-pressed={m === mode}
                onClick={() => setMode(m)}
              >
                {m === 'login'
                  ? 'Sign in'
                  : m === 'register'
                    ? 'Register'
                    : 'Forgot password'}
              </Button>
            ))}
          </nav>
          <form
            onSubmit={(e) => {
              const f = values(e);
              void run(async () => {
                const d = await accountRequest<{ message?: string }>(mode, {
                  ...f,
                  terms: f.terms === 'on',
                });
                if (mode === 'login') {
                  await refresh();
                  window.dispatchEvent(new Event('elvarqo-account'));
                  setNotice('Signed in.');
                } else
                  setNotice(
                    d.message || 'Check your private local email copy.',
                  );
              });
            }}
          >
            {mode === 'register' && (
              <label>
                Name
                <input
                  name="name"
                  autoComplete="name"
                  maxLength={100}
                  required
                />
              </label>
            )}
            <label>
              Email
              <input
                type="email"
                name="email"
                autoComplete="email"
                maxLength={254}
                required
              />
            </label>
            {mode !== 'recover' && (
              <label>
                Password
                <input
                  type="password"
                  name="password"
                  aria-label="Password"
                  minLength={mode === 'register' ? 14 : 1}
                  maxLength={128}
                  autoComplete={
                    mode === 'register' ? 'new-password' : 'current-password'
                  }
                  required
                />
                {mode === 'register' && (
                  <small>Use at least 14 characters.</small>
                )}
              </label>
            )}
            {mode === 'register' && (
              <label className="account-check">
                <input type="checkbox" name="terms" required />I accept the{' '}
                <a href="/terms">terms</a> and have read the{' '}
                <a href="/privacy">privacy information</a>.
              </label>
            )}
            <Button disabled={busy}>
              {busy
                ? 'Please wait…'
                : mode === 'login'
                  ? 'Sign in'
                  : mode === 'register'
                    ? 'Create account'
                    : 'Request reset link'}
            </Button>
          </form>
        </section>
      ) : (
        <>
          <Button
            disabled={busy}
            onClick={() =>
              void run(async () => {
                await accountRequest('logout', {});
                setDetail(null);
                setOrders(null);
                await refresh();
                window.dispatchEvent(new Event('elvarqo-account'));
                setNotice('Signed out.');
              })
            }
          >
            Sign out
          </Button>
          <div className="account-grid">
            <section className="account-card">
              <h2>Order history</h2>
              <form
                onSubmit={(e) => {
                  const f = values(e);
                  void run(async () => {
                    const d = await accountRequest<{ message: string }>(
                      'claim',
                      f,
                    );
                    setNotice(d.message);
                  });
                }}
              >
                <label>
                  Link a guest order
                  <input
                    name="reference"
                    placeholder="EV-…"
                    maxLength={40}
                    required
                  />
                </label>
                <p>
                  A single-use proof link goes to that order’s original email.
                  Matching an email here cannot link an order.
                </p>
                <Button disabled={busy}>Request order ownership link</Button>
              </form>
              {orders?.items.length === 0 && <p>No linked orders yet.</p>}
              {orders?.items.map((o) => (
                <article key={o.id} className="account-record">
                  <h3>{o.reference}</h3>
                  <p>
                    {o.payment_state} / {o.fulfilment_state} /{' '}
                    {money(o.total_minor)}
                  </p>
                  <Button
                    disabled={busy}
                    onClick={() =>
                      void run(async () =>
                        setDetail(
                          await accountRequest<Detail>('order?id=' + o.id),
                        ),
                      )
                    }
                  >
                    View order {o.reference}
                  </Button>
                </article>
              ))}
              {orders && orders.last_page > 1 && (
                <nav className="account-tabs">
                  <Button
                    disabled={busy || orders.page <= 1}
                    onClick={() => void run(() => load(orders.page - 1))}
                  >
                    Previous orders
                  </Button>
                  <span>
                    {orders.page} / {orders.last_page}
                  </span>
                  <Button
                    disabled={busy || orders.page >= orders.last_page}
                    onClick={() => void run(() => load(orders.page + 1))}
                  >
                    Next orders
                  </Button>
                </nav>
              )}
            </section>
            <section className="account-card">
              <h2>Your profile</h2>
              <form
                key={customer.id}
                onSubmit={(e) => {
                  const f = values(e);
                  void run(async () => {
                    await accountRequest('profile', f);
                    await refresh();
                    setNotice('Profile saved.');
                  });
                }}
              >
                <label>
                  Name
                  <input
                    name="name"
                    defaultValue={customer.name}
                    maxLength={100}
                    required
                  />
                </label>
                <p>Verified email: {customer.email}</p>
                <Button disabled={busy}>Save profile</Button>
              </form>
              <details>
                <summary>Change email</summary>
                <form
                  onSubmit={(e) => {
                    const f = values(e);
                    void run(async () => {
                      const d = await accountRequest<{ message: string }>(
                        'email',
                        f,
                      );
                      setNotice(d.message);
                    });
                  }}
                >
                  <label>
                    New email
                    <input
                      name="email"
                      type="email"
                      autoComplete="email"
                      required
                    />
                  </label>
                  <label>
                    Current password
                    <input
                      name="password"
                      type="password"
                      autoComplete="current-password"
                      required
                    />
                  </label>
                  <Button disabled={busy}>Verify new email</Button>
                </form>
              </details>
              <p>
                <a href="/wishlist">Your saved pieces</a> merge with this
                account after sign-in.
              </p>
            </section>
          </div>
          {detail && (
            <section className="account-card">
              <h2>Order {detail.reference}</h2>
              <p>
                {detail.payment_state} / {detail.fulfilment_state} /{' '}
                {money(detail.total_minor)}
              </p>
              {detail.snapshot.items.map((i, n) => (
                <p key={n}>
                  {i.title} / {i.colour} / {i.size} × {i.quantity} —{' '}
                  {money(i.line_minor)}
                </p>
              ))}
              <p>
                Discount {money(detail.snapshot.discount_minor)} · Delivery{' '}
                {money(detail.snapshot.shipping_minor)} · Tax{' '}
                {money(detail.snapshot.tax_minor)}
              </p>
              <h3>Delivery address at purchase</h3>
              <p>
                {Object.values(detail.snapshot.shipping_address)
                  .filter(Boolean)
                  .join(', ')}
              </p>
              {detail.tracking.shipments.map((s) => (
                <p key={s.id}>
                  Local shipment {s.tracking_number}: {s.state}
                </p>
              ))}
              <ReturnPanel
                data={detail.after_sales}
                submit={async (body) => {
                  await accountRequest('returns', {
                    order_id: detail.id,
                    ...body,
                  });
                  setDetail(await accountRequest('order?id=' + detail.id));
                }}
              />
            </section>
          )}
          <section className="account-card">
            <h2>Address book</h2>
            <p>Editing addresses here does not change existing orders.</p>
            {addresses.map((a) => (
              <article key={a.id} className="account-record">
                <p>
                  {a.is_default ? 'Default: ' : ''}
                  {Object.values(a.address).filter(Boolean).join(', ')}
                </p>
                <Button disabled={busy} onClick={() => setEditing(a)}>
                  Edit address
                </Button>
                <Button
                  disabled={busy}
                  onClick={() => {
                    if (
                      window.confirm(
                        'Delete this saved address? Existing orders keep their original address.',
                      )
                    )
                      void run(async () => {
                        setAddresses(
                          await accountRequest('addresses', {
                            id: a.id,
                            version: a.version,
                            action: 'delete',
                            confirmed: true,
                          }),
                        );
                        if (editing?.id === a.id) setEditing(null);
                      });
                  }}
                >
                  Delete address
                </Button>
              </article>
            ))}
            <h3>{editing ? 'Edit saved address' : 'Add address'}</h3>
            <form
              key={editing?.id || 'new'}
              onSubmit={(e) => {
                const f = values(e);
                void run(async () => {
                  const address = Object.fromEntries(
                    addressFields.map((k) => [k, f[k]]),
                  );
                  setAddresses(
                    await accountRequest('addresses', {
                      ...(editing
                        ? { id: editing.id, version: editing.version }
                        : {}),
                      action: 'save',
                      address: { ...address, country: 'GB' },
                      is_default: f.is_default === 'on',
                      confirmed: true,
                    }),
                  );
                  setEditing(null);
                  setNotice('Address saved.');
                });
              }}
            >
              {addressFields.map((k) => (
                <label key={k}>
                  {k.replaceAll('_', ' ')}
                  <input
                    name={k}
                    defaultValue={editing?.address[k] || ''}
                    maxLength={k === 'postcode' ? 12 : 120}
                    required={k !== 'line2'}
                  />
                </label>
              ))}
              <p>Country: United Kingdom</p>
              <label className="account-check">
                <input
                  name="is_default"
                  type="checkbox"
                  defaultChecked={editing?.is_default}
                />
                Use as default saved address
              </label>
              <Button disabled={busy}>Save address</Button>
              {editing && (
                <Button type="button" onClick={() => setEditing(null)}>
                  Cancel editing
                </Button>
              )}
            </form>
          </section>
        </>
      )}
    </div>
  );
}
