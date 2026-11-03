import { Button, Modal } from '../ui/components';
import { useState, useEffect, useCallback } from 'react';
import { staffRequest, type StaffIdentity } from '../lib/staff-api';
import './after-sales.css';
type Case = {
  id: string;
  reference: string;
  quantity: number;
  credit_minor: number;
  refundable_minor: number;
  resolution: string;
  state: string;
  comments: string;
  reason: string;
  item: { title: string; sku: string };
  inspection: { outcome: string; reason: string } | null;
  locations: { id: number; name: string }[];
  exchange_options: {
    variant_id: number;
    sku: string;
    size: string;
    colour: string;
    line_minor: number;
    available: number;
    unavailable: boolean;
  }[];
  exchanges: { reference: string; state: string; difference_minor: number }[];
  refunds: {
    id: string;
    amount_minor: number;
    state: string;
    last_error: string | null;
    reason: string;
    provider_reference: string | null;
  }[];
  timeline: { kind: string; created_at: string }[];
};
const money = (n: number) =>
  new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(
    n / 100,
  );
export default function AfterSales({
  staff,
  finance = false,
}: {
  staff: StaffIdentity;
  finance?: boolean;
}) {
  const [data, setData] = useState<{
    items: Case[];
    policy: string;
    page: number;
    last_page: number;
  } | null>(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [page, setPage] = useState(1);
  const load = useCallback(async () => {
    setData(
      await staffRequest(
        'after-sales/' + (finance ? 'refunds' : 'returns') + '?page=' + page,
      ),
    );
  }, [finance, page]);
  useEffect(() => {
    const t = setTimeout(
      () => void load().catch((e) => setError(e.message)),
      0,
    );
    return () => clearTimeout(t);
  }, [load]);
  async function act(path: string, body: Record<string, unknown>) {
    if (busy) return;
    setBusy(true);
    setError('');
    try {
      await staffRequest('after-sales/' + path, body);
      await load();
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  }
  return (
    <>
      <header className="admin-page-heading">
        <div>
          <h1>{finance ? 'Refund sandbox' : 'Returns & exchanges'}</h1>
          <p>{data?.policy}</p>
        </div>
      </header>
      <div className="panel">
        <Button onClick={() => void load().catch((e) => setError(e.message))}>
          Refresh cases
        </Button>
        {error && <p role="alert">{error}</p>}
        {data?.items.length === 0 && <p>No return requests yet.</p>}
        {data?.items.map((r) => (
          <ReturnCase
            key={r.id}
            r={r}
            finance={finance}
            staff={staff}
            busy={busy}
            act={act}
          />
        ))}
        {data && (
          <div>
            <Button
              disabled={page <= 1 || busy}
              onClick={() => setPage((n) => n - 1)}
            >
              Previous cases
            </Button>
            <span>
              Page {page} / {data.last_page}
            </span>
            <Button
              disabled={page >= data.last_page || busy}
              onClick={() => setPage((n) => n + 1)}
            >
              Next cases
            </Button>
          </div>
        )}
      </div>
    </>
  );
}
function ReturnCase({
  r,
  finance,
  staff,
  busy,
  act,
}: {
  r: Case;
  finance: boolean;
  staff: StaffIdentity;
  busy: boolean;
  act: (path: string, body: Record<string, unknown>) => Promise<void>;
}) {
  const [pendingRefund, setPendingRefund] = useState<Record<
    string,
    unknown
  > | null>(null);
  const [variant, setVariant] = useState('');
  const [operation, setOperation] = useState<string | null>(null);
  const target = r.exchange_options.find(
    (v) => String(v.variant_id) === variant,
  );
  const manage = staff.permissions.includes('fulfilment.manage');
  const refund = staff.permissions.includes('finance.refund');
  const key = () => {
    const k = operation || crypto.randomUUID();
    setOperation(k);
    return k;
  };
  return (
    <article className="after-sales-case">
      <Modal
        open={pendingRefund !== null}
        title="Confirm sandbox refund"
        onClose={() => {
          if (!busy) setPendingRefund(null);
        }}
      >
        {pendingRefund && (
          <>
            <p>
              Order {r.reference} · Exact amount{' '}
              {money(Number(pendingRefund.amount_minor))}
            </p>
            <p>Reason: {String(pendingRefund.reason)}</p>
            <p>
              Local sandbox only. No real money moves. Pending or unknown
              outcomes hold this credit until reconciled.
            </p>
            <Button
              type="button"
              disabled={busy}
              onClick={() =>
                void act(r.id + '/refund', pendingRefund).then(() =>
                  setPendingRefund(null),
                )
              }
            >
              Confirm exact sandbox amount
            </Button>
            <Button
              type="button"
              variant="secondary"
              disabled={busy}
              onClick={() => setPendingRefund(null)}
            >
              Back to review
            </Button>
          </>
        )}
      </Modal>
      <h2>
        {r.reference} / {r.item.title}
      </h2>
      <p>
        {r.item.sku} × {r.quantity} · {r.state} · {r.resolution} · {r.reason}
      </p>
      <p>
        Merchandise credit: {money(r.credit_minor)} · Remaining inspected
        balance: {money(r.refundable_minor)}
      </p>
      <p>{r.comments}</p>
      <small>Case {r.id}</small>
      {!finance && manage && !r.inspection && (
        <form
          className="after-sales-form"
          onSubmit={(e) => {
            e.preventDefault();
            const f = new FormData(e.currentTarget);
            void act(r.id + '/inspect', {
              received_quantity: Number(f.get('received_quantity')),
              location_id: Number(f.get('location_id')),
              outcome: f.get('outcome'),
              reason: f.get('reason'),
              confirmed: f.get('confirmed') === 'on',
            });
          }}
        >
          <h3>Record physical inspection</h3>
          <label>
            Received quantity
            <input
              name="received_quantity"
              type="number"
              min={r.quantity}
              max={r.quantity}
              defaultValue={r.quantity}
              required
            />
          </label>
          <label>
            Inspection outcome
            <select name="outcome" aria-label="Inspection outcome">
              <option value="accepted_damaged">
                Accepted, damaged — no restock
              </option>
              <option value="accepted_resellable">
                Accepted, resellable — restock once
              </option>
              <option value="rejected">Rejected — no restock / credit</option>
            </select>
          </label>
          <label>
            Receiving location
            <select name="location_id" aria-label="Receiving location" required>
              <option value="">Choose location</option>
              {r.locations.map((l) => (
                <option key={l.id} value={l.id}>
                  {l.name}
                </option>
              ))}
            </select>
          </label>
          <label>
            Inspection reason
            <textarea name="reason" minLength={3} maxLength={1500} required />
          </label>
          <label>
            <input type="checkbox" name="confirmed" required />I physically
            inspected all units and confirm the stock outcome.
          </label>
          <Button disabled={busy}>Confirm inspection</Button>
        </form>
      )}
      {r.inspection && (
        <p>
          Inspection: {r.inspection.outcome} / {r.inspection.reason}
        </p>
      )}
      {!finance &&
        manage &&
        r.state === 'accepted' &&
        r.resolution === 'exchange' &&
        !r.exchanges.some((e) => e.state !== 'expired') && (
          <>
            <form
              className="after-sales-form"
              onChange={() => setOperation(null)}
              onSubmit={(e) => {
                e.preventDefault();
                const f = new FormData(e.currentTarget);
                if (target)
                  void act(r.id + '/exchange', {
                    operation_id: key(),
                    variant_id: target.variant_id,
                    expected_difference_minor:
                      target.line_minor - r.credit_minor,
                    confirmed: f.get('confirmed') === 'on',
                  });
              }}
            >
              <h3>Review replacement</h3>
              <label>
                Variant
                <select
                  aria-label="Variant"
                  value={variant}
                  onChange={(e) => setVariant(e.target.value)}
                  required
                >
                  <option value="">Select current replacement</option>
                  {r.exchange_options.map((v) => (
                    <option
                      key={v.variant_id}
                      value={v.variant_id}
                      disabled={v.unavailable}
                    >
                      {v.sku} / {v.colour} / {v.size} — {v.available} available
                    </option>
                  ))}
                </select>
              </label>
              {target && (
                <p>
                  Replacement {money(target.line_minor)} · Original credit{' '}
                  {money(r.credit_minor)} · Difference{' '}
                  {money(target.line_minor - r.credit_minor)}.{' '}
                  {target.line_minor > r.credit_minor
                    ? 'Top-up deferred: unpaid replacement cannot ship; hold expires after 30 minutes.'
                    : 'Funded replacement can ship. A cheaper difference remains eligible for refund review.'}
                </p>
              )}
              <label>
                <input type="checkbox" name="confirmed" required />I confirm
                variant, total and difference.
              </label>
              <Button disabled={busy || !target}>Create replacement</Button>
            </form>
            <form
              className="after-sales-form"
              onSubmit={(e) => {
                e.preventDefault();
                const f = new FormData(e.currentTarget);
                void act(r.id + '/resolution', {
                  reason: f.get('reason'),
                  confirmed: f.get('confirmed') === 'on',
                });
              }}
            >
              <label>
                Reason to switch to refund
                <input name="reason" minLength={3} maxLength={1500} required />
              </label>
              <label>
                <input type="checkbox" name="confirmed" required />
                Confirm refund review instead of exchange
              </label>
              <Button disabled={busy}>Switch to refund review</Button>
            </form>
          </>
        )}
      {r.exchanges.map((e, i) => (
        <p key={i}>
          Replacement {e.reference}: {e.state} · Difference{' '}
          {money(e.difference_minor)}
        </p>
      ))}
      {finance && refund && r.refundable_minor > 0 && (
        <form
          className="after-sales-form"
          onChange={() => setOperation(null)}
          onSubmit={(e) => {
            e.preventDefault();
            const f = new FormData(e.currentTarget);
            setPendingRefund({
              operation_id: key(),
              amount_minor: Number(f.get('amount_minor')),
              reason: f.get('reason'),
              scenario: f.get('scenario'),
              confirmed: f.get('confirmed') === 'on',
            });
          }}
        >
          <h3>Sandbox refund approval</h3>
          <label>
            Amount in GBP pence
            <input
              name="amount_minor"
              type="number"
              min="1"
              max={r.refundable_minor}
              required
            />
          </label>
          <label>
            Reason
            <textarea name="reason" minLength={3} maxLength={1500} required />
          </label>
          <label>
            Local outcome
            <select name="scenario" aria-label="Local outcome">
              <option value="succeed">Succeed</option>
              <option value="pending">Pending asynchronous result</option>
              <option value="fail">Fail</option>
              <option value="timeout_after_once">
                Response lost after one success
              </option>
            </select>
          </label>
          <label>
            <input type="checkbox" name="confirmed" required />I confirm this
            exact amount and reason. Local sandbox only; no real money moves.
          </label>
          <Button disabled={busy}>Confirm sandbox refund</Button>
        </form>
      )}
      {r.refunds.map((f) => (
        <div key={f.id}>
          <p>
            Attempt {f.id} · Provider reference{' '}
            {f.provider_reference || 'Not received'} · Reason: {f.reason}
          </p>
          <p>
            {money(f.amount_minor)} / {f.state} / {f.last_error} · No real money
            moved.
          </p>
          {finance &&
            refund &&
            ['sandbox_pending', 'sandbox_unknown'].includes(f.state) && (
              <Button
                disabled={busy}
                onClick={() => void act(f.id + '/retry', { confirmed: true })}
              >
                Reconcile same refund attempt
              </Button>
            )}
        </div>
      ))}
      <details>
        <summary>Case timeline</summary>
        <ol>
          {r.timeline.map((t, i) => (
            <li key={i}>
              {t.kind} / {t.created_at}
            </li>
          ))}
        </ol>
      </details>
    </article>
  );
}
