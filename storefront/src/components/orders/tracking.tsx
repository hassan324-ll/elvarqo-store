'use client';
import { useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { Button, Field } from '../../ui/components';
type Progress = {
  reference: string;
  order_state: string;
  payment_state: string;
  fulfilment_state: string;
  sandbox: boolean;
  shipments: {
    id: string;
    state: string;
    tracking_number: string;
    dispatched_at: string | null;
    sandbox: boolean;
    items: { title: string; colour: string; size: string; quantity: number }[];
    events: { state: string; occurred_at: string }[];
  }[];
  support: { id: string; subject: string; state: string; created_at: string }[];
};
async function request<T>(action: string, body?: unknown): Promise<T> {
  const r = await fetch('/api/tracking/' + action, {
    method: body === undefined ? 'GET' : 'POST',
    cache: 'no-store',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      ...(body === undefined ? {} : { 'Content-Type': 'application/json' }),
    },
    ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    signal: AbortSignal.timeout(20000),
  });
  const j = await r.json();
  if (!r.ok)
    throw new Error(
      Object.values(j.error?.details || {})
        .flat()
        .join(' ') ||
        j.error?.message ||
        'Tracking request failed.',
    );
  return j.data as T;
}
export default function TrackingPage({
  payment = false,
}: {
  payment?: boolean;
}) {
  const [reference, setReference] = useState('');
  const [email, setEmail] = useState('');
  const [token, setToken] = useState('');
  const [progress, setProgress] = useState<Progress | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [subject, setSubject] = useState('');
  const [message, setMessage] = useState('');
  const [supportKey, setSupportKey] = useState('');
  const [checked, setChecked] = useState(false);
  const captured = useRef('');
  useEffect(() => {
    let timer: ReturnType<typeof setTimeout>;
    let active = true;
    function readLink() {
      const candidate = new URLSearchParams(window.location.hash.slice(1)).get(
        'token',
      );
      if (candidate) {
        captured.current = candidate;
        history.replaceState(null, '', window.location.pathname);
      }
      clearTimeout(timer);
      timer = setTimeout(() => {
        if (!active) return;
        setChecked(true);
        if (captured.current) {
          setProgress(null);
          setError('');
          setNotice('');
          if (/^[a-f0-9]{64}$/.test(captured.current))
            setToken(captured.current);
          else
            setError('This verification link is invalid. Request a new link.');
        } else if (!payment) {
          void request<Progress>('order')
            .then((p) => {
              if (active) setProgress(p);
            })
            .catch(() => {});
        }
      }, 0);
    }
    readLink();
    window.addEventListener('hashchange', readLink);
    return () => {
      active = false;
      clearTimeout(timer);
      window.removeEventListener('hashchange', readLink);
    };
  }, [payment]);
  async function send() {
    setBusy(true);
    setError('');
    try {
      const j = await request<{ message: string }>('request', {
        reference,
        email,
      });
      setNotice(j.message);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Link could not be requested.');
    } finally {
      setBusy(false);
    }
  }
  async function verify() {
    setBusy(true);
    setError('');
    try {
      const r = await request<{ order_id: string }>(
        payment ? 'payment-redeem' : 'redeem',
        { token },
      );
      captured.current = '';
      setToken('');
      if (payment) {
        window.location.assign('/checkout/confirmation?order=' + r.order_id);
        return;
      }
      setProgress(await request<Progress>('order'));
      setNotice('Secure tracking access verified for this order.');
    } catch (e) {
      captured.current = '';
      setToken('');
      setError(
        e instanceof Error
          ? e.message
          : 'Verification failed. Request a new link.',
      );
    } finally {
      setBusy(false);
    }
  }
  async function refresh() {
    setBusy(true);
    setError('');
    try {
      setProgress(await request<Progress>('order'));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Tracking unavailable.');
      setProgress(null);
    } finally {
      setBusy(false);
    }
  }
  async function support() {
    setBusy(true);
    setError('');
    const key = supportKey || crypto.randomUUID();
    setSupportKey(key);
    try {
      await request('support', { operation_id: key, subject, message });
      setSupportKey('');
      setSubject('');
      setMessage('');
      setNotice('Your support request has been saved for staff review.');
      setProgress(await request<Progress>('order'));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Support request unavailable.');
    } finally {
      setBusy(false);
    }
  }
  async function logout() {
    setBusy(true);
    setError('');
    try {
      await request('logout', {});
      setProgress(null);
      setNotice('Tracking access closed on this browser.');
    } catch (e) {
      setError(
        e instanceof Error ? e.message : 'Could not close tracking access.',
      );
    } finally {
      setBusy(false);
    }
  }
  return (
    <section className="commerce-page tracking-page">
      <p className="eyebrow">ELVARQO / Secure guest access</p>
      <h1>{payment ? 'Open your payment invitation' : 'Track your order'}</h1>
      {error && (
        <p role="alert" className="shop-error">
          {error}
        </p>
      )}
      {notice && (
        <p role="status" className="shop-notice">
          {notice}
        </p>
      )}
      {!checked && <p role="status">Checking secure access…</p>}
      {token && (
        <div className="commerce-flow">
          <p>
            This one-use link grants access to one order. Verify to continue.
            Links expire after 30 minutes.
          </p>
          <Button busy={busy} onClick={() => void verify()}>
            {payment
              ? 'Open secure payment invitation'
              : 'Verify tracking access'}
          </Button>
        </div>
      )}
      {!token && !progress && !payment && (
        <form
          className="commerce-flow tracking-request"
          onSubmit={(e) => {
            e.preventDefault();
            void send();
          }}
        >
          <p>
            Enter your order reference and the email used at checkout. A one-use
            verification link is required before order progress can be shown.
          </p>
          <Field
            label="Order reference"
            required
            maxLength={60}
            value={reference}
            onChange={(e) => setReference(e.target.value)}
          />
          <Field
            label="Order email address"
            type="email"
            required
            maxLength={254}
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
          <Button type="submit" busy={busy}>
            Send secure tracking link
          </Button>
          <p className="muted">
            Local testing: links are saved in private sandbox email copies.
            External email delivery is not configured.
          </p>
        </form>
      )}
      {!token && payment && (
        <p>
          A payment invitation from your order email is required. If the link
          expired, contact the store for a new invitation. Payments remain in
          sandbox.
        </p>
      )}
      {progress && (
        <>
          <h2>Order {progress.reference}</h2>
          <p>
            Order: {progress.order_state} / Payment: {progress.payment_state} /
            Fulfilment: {progress.fulfilment_state}
          </p>
          <p className="shop-notice">
            Courier sandbox: these are local test shipment records. No real
            carrier shipment or postage exists.
          </p>
          <div className="selection-actions">
            <Button
              busy={busy}
              variant="secondary"
              onClick={() => void refresh()}
            >
              Refresh order progress
            </Button>
            <Button
              disabled={busy}
              variant="secondary"
              onClick={() => void logout()}
            >
              Close tracking access
            </Button>
          </div>
          <p>
            <a href="/returns" className="text-link">
              Request or review a return
            </a>
          </p>
          {!progress.shipments.length && (
            <p>
              No confirmed shipment booking yet. Tracking updates appear after
              dispatch.
            </p>
          )}
          {progress.shipments.map((s) => (
            <article key={s.id} className="tracking-shipment">
              <h3>Sandbox delivery / {s.state}</h3>
              <p>Tracking number: {s.tracking_number}</p>
              {s.dispatched_at && (
                <p>Dispatched: {new Date(s.dispatched_at).toLocaleString()}</p>
              )}
              <ul>
                {s.items.map((i, index) => (
                  <li key={index}>
                    {i.title} / {i.colour} / {i.size} / Quantity {i.quantity}
                  </li>
                ))}
              </ul>
              <ol>
                {s.events.map((e, index) => (
                  <li key={index}>
                    {e.state} / {new Date(e.occurred_at).toLocaleString()}
                  </li>
                ))}
              </ol>
            </article>
          ))}
          <section className="tracking-support">
            <h2>Need help with this order?</h2>
            <form
              className="commerce-flow"
              onSubmit={(e) => {
                e.preventDefault();
                void support();
              }}
            >
              <Field
                label="Support subject"
                required
                minLength={3}
                maxLength={120}
                value={subject}
                onChange={(e) => {
                  setSubject(e.target.value);
                  setSupportKey('');
                }}
              />
              <label>
                Support message
                <textarea
                  required
                  minLength={5}
                  maxLength={2000}
                  value={message}
                  onChange={(e) => {
                    setMessage(e.target.value);
                    setSupportKey('');
                  }}
                />
              </label>
              <Button type="submit" busy={busy}>
                Send order support request
              </Button>
            </form>
            {progress.support.map((s) => (
              <p key={s.id}>
                {s.subject} / {s.state} /{' '}
                {new Date(s.created_at).toLocaleString()}
              </p>
            ))}
          </section>
        </>
      )}
      <p>
        <Link href="/shop">Continue shopping</Link>
      </p>
    </section>
  );
}
