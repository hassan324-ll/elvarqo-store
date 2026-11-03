import { useCallback, useEffect, useState } from 'react';
import {
  staffRequest,
  staffDownload,
  type StaffIdentity,
} from '../lib/staff-api';
import { Button, DataTable, Field, Select, Modal } from '../ui/components';
import { type Row, type Detail, money } from './types';
import ShipmentForm from './ShipmentForm';
import ManualOrder from './ManualOrder';
import WisePayment from './WisePayment';
type Listing = {
  items: Row[];
  page: number;
  last_page: number;
  total: number;
  configuration: {
    courier: string;
    mail: string;
    provider: string;
    enabled: boolean;
  };
};
export default function Orders({ staff }: { staff: StaffIdentity }) {
  const manage = staff.permissions.includes('fulfilment.manage');
  const [data, setData] = useState<Listing | null>(null);
  const [detail, setDetail] = useState<Detail | null>(null);
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState({
    q: '',
    payment: '',
    fulfilment: '',
  });
  const [applied, setApplied] = useState(filters);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [note, setNote] = useState('');
  const [manual, setManual] = useState(false);
  const [confirm, setConfirm] = useState<{ id: string; action: string } | null>(
    null,
  );
  const load = useCallback(async () => {
    setBusy(true);
    setError('');
    try {
      setData(
        await staffRequest<Listing>(
          'orders?' + new URLSearchParams({ page: String(page), ...applied }),
        ),
      );
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Orders unavailable.');
    } finally {
      setBusy(false);
    }
  }, [page, applied]);
  useEffect(() => {
    const timer = setTimeout(() => void load(), 0);
    return () => clearTimeout(timer);
  }, [load]);
  async function show(id: string) {
    setBusy(true);
    setError('');
    try {
      setDetail(await staffRequest<Detail>('orders/' + id));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Order unavailable.');
    } finally {
      setBusy(false);
    }
  }
  async function reload() {
    if (detail) await show(detail.id);
    await load();
  }
  async function addNote() {
    if (!detail) return;
    setBusy(true);
    setError('');
    try {
      await staffRequest(`orders/${detail.id}/notes`, {
        operation_id: crypto.randomUUID(),
        body: note,
      });
      setNote('');
      setNotice('Internal note saved.');
      await show(detail.id);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Note could not be saved.');
    } finally {
      setBusy(false);
    }
  }
  async function act() {
    if (!confirm) return;
    setBusy(true);
    setError('');
    try {
      await staffRequest(`shipments/${confirm.id}/${confirm.action}`, {
        confirmed: true,
      });
      setConfirm(null);
      setNotice('Shipment updated.');
      await reload();
    } catch (e) {
      setError(
        e instanceof Error ? e.message : 'Shipment could not be updated.',
      );
    } finally {
      setBusy(false);
    }
  }
  async function download(id: string) {
    setBusy(true);
    setError('');
    try {
      await staffDownload(
        `shipments/${id}/label`,
        'ELVARQO-SANDBOX-' + id + '.svg',
      );
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Label unavailable.');
    } finally {
      setBusy(false);
    }
  }
  async function invitation() {
    if (!detail) return;
    setBusy(true);
    setError('');
    try {
      await staffRequest(`orders/${detail.id}/payment-invitation`, {
        confirmed: true,
      });
      setNotice('Private payment invitation queued.');
      await show(detail.id);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Invitation unavailable.');
    } finally {
      setBusy(false);
    }
  }
  if (manual)
    return (
      <ManualOrder
        onClose={() => setManual(false)}
        onCreated={async (id) => {
          setManual(false);
          setNotice(
            'Unpaid manual order saved. Private payment invitation queued.',
          );
          await show(id);
          await load();
        }}
      />
    );
  return (
    <section className="panel order-workspace">
      <p className="eyebrow">Order operations / Local courier sandbox</p>
      <h1>Orders workspace</h1>
      <p>
        Manage real saved order records. Sandbox labels do not purchase postage
        or create a real carrier shipment.
      </p>
      {error && (
        <p role="alert" className="auth-error">
          {error}
        </p>
      )}
      {notice && <p role="status">{notice}</p>}
      <div className="order-actions">
        <Button
          busy={busy}
          onClick={() => {
            setDetail(null);
            void load();
          }}
        >
          Refresh orders
        </Button>
        {manage && (
          <Button variant="secondary" onClick={() => setManual(true)}>
            Create manual order
          </Button>
        )}
      </div>
      <form
        className="order-filter"
        onSubmit={(e) => {
          e.preventDefault();
          setPage(1);
          setApplied({ ...filters });
        }}
      >
        <Field
          label="Search orders by reference or customer name"
          value={filters.q}
          maxLength={100}
          onChange={(e) => setFilters({ ...filters, q: e.target.value })}
        />
        <Select
          label="Payment filter"
          value={filters.payment}
          onChange={(e) => setFilters({ ...filters, payment: e.target.value })}
        >
          <option value="">All payment states</option>
          {[
            'paid',
            'exchange_credit',
            'awaiting_exchange_payment',
            'open',
            'creating',
            'pending',
            'failed',
            'expired',
            'review',
            'awaiting_payment_link',
            'awaiting_bank_transfer',
          ].map((s) => (
            <option key={s}>{s}</option>
          ))}
        </Select>
        <Select
          label="Fulfilment filter"
          value={filters.fulfilment}
          onChange={(e) =>
            setFilters({ ...filters, fulfilment: e.target.value })
          }
        >
          <option value="">All fulfilment states</option>
          {[
            'unfulfilled',
            'partially_fulfilled',
            'fulfilled',
            'delivered',
            'on_hold',
          ].map((s) => (
            <option key={s}>{s}</option>
          ))}
        </Select>
        <Button type="submit" disabled={busy}>
          Apply order filters
        </Button>
      </form>
      {data && (
        <>
          <p>
            {data.total} orders / Page {data.page} of {data.last_page}
          </p>
          <DataTable
            caption="Current orders"
            columns={[
              'Reference',
              'Order',
              'Payment',
              'Fulfilment',
              'Total',
              'Details',
            ]}
            rows={data.items.map((o) => [
              o.reference,
              o.order_state,
              o.payment_state,
              o.fulfilment_state,
              money(o.total_minor),
              <Button
                key={o.id}
                variant="secondary"
                disabled={busy}
                onClick={() => void show(o.id)}
              >
                View {o.reference}
              </Button>,
            ])}
          />
          {!data.items.length && <p>No matching orders.</p>}
          <div className="order-actions">
            <Button
              variant="secondary"
              disabled={busy || page <= 1}
              onClick={() => setPage(page - 1)}
            >
              Previous
            </Button>
            <Button
              variant="secondary"
              disabled={busy || page >= data.last_page}
              onClick={() => setPage(page + 1)}
            >
              Next
            </Button>
          </div>
          <details>
            <summary>Payment & development configuration</summary>
            <p>
              {data.configuration.provider} /{' '}
              {data.configuration.enabled ? 'Configured' : 'Setup pending'}
            </p>
            <p>{data.configuration.courier}</p>
            <p>{data.configuration.mail}</p>
            <p>
              Customer delivery/tax rates remain test assumptions. Wise bank
              transfers require owner confirmation of funds received.
            </p>
          </details>
        </>
      )}
      {detail && (
        <section className="order-detail">
          <h2>Order {detail.reference}</h2>
          <p>
            {detail.manual ? 'Manual order / ' : ''}
            {detail.order_state} / {detail.payment_state} /{' '}
            {detail.fulfilment_state}
          </p>
          <WisePayment
            key={detail.id}
            order={detail}
            canConfirm={staff.permissions.includes('finance.confirm')}
            onSaved={reload}
          />
          <p>Contact: {detail.snapshot.contact.email}</p>
          <p>
            Created: {new Date(detail.created_at).toLocaleString()} /
            Confirmation: {detail.mail_status}
          </p>
          <DataTable
            caption="Immutable order items"
            columns={[
              'Piece',
              'SKU',
              'Choice',
              'Quantity',
              'Unit price',
              'Line total',
            ]}
            rows={detail.snapshot.items.map((l) => [
              l.title,
              l.sku,
              l.colour + ' / ' + l.size,
              l.quantity,
              money(l.unit_minor),
              money(l.line_minor),
            ])}
          />
          <p>
            Subtotal {money(detail.snapshot.subtotal_minor)} / Discount{' '}
            {money(detail.snapshot.discount_minor)} / Delivery{' '}
            {money(detail.snapshot.shipping_minor)} / Tax{' '}
            {money(detail.snapshot.tax_minor)} / Total{' '}
            {money(detail.snapshot.total_minor)}
          </p>
          <p>
            {detail.snapshot.tax_note} / {detail.snapshot.delivery.name}
          </p>
          <div className="order-form-grid">
            <div>
              <h3>Delivery address snapshot</h3>
              <p>
                {Object.values(detail.snapshot.shipping_address)
                  .filter(Boolean)
                  .join(', ')}
              </p>
            </div>
            <div>
              <h3>Billing address snapshot</h3>
              <p>
                {Object.values(detail.snapshot.billing_address)
                  .filter(Boolean)
                  .join(', ')}
              </p>
            </div>
          </div>
          <h3>Remaining fulfilment quantities</h3>
          <DataTable
            caption="Remaining order quantities"
            columns={['SKU', 'Ordered', 'Allocated', 'Remaining']}
            rows={detail.remaining.map((l) => [
              l.sku,
              l.ordered,
              l.allocated,
              l.remaining,
            ])}
          />
          {manage &&
            (detail.payment_state === 'paid' ||
              (detail.payment_state === 'exchange_credit' &&
                detail.funded_exchange)) &&
            detail.order_state === 'confirmed' &&
            detail.fulfilment_state !== 'on_hold' &&
            detail.remaining.some((l) => l.remaining > 0) && (
              <ShipmentForm
                key={detail.id + JSON.stringify(detail.remaining)}
                order={detail}
                onSaved={reload}
              />
            )}
          {detail.payment_state !== 'paid' && !detail.funded_exchange && (
            <p role="status">
              This order is unpaid or unverified. Shipment booking and dispatch
              are unavailable.
            </p>
          )}
          {manage &&
            detail.manual &&
            ['awaiting_payment_link', 'awaiting_bank_transfer'].includes(
              detail.payment_state,
            ) && (
              <Button
                variant="secondary"
                disabled={busy}
                onClick={() => void invitation()}
              >
                Queue another private payment invitation
              </Button>
            )}
          <h3>Shipments</h3>
          {!detail.shipments.length && <p>No parcels allocated yet.</p>}
          {detail.shipments.map((s) => (
            <article className="order-tool" key={s.id}>
              <h4>Sandbox parcel {s.id.slice(0, 8)}</h4>
              <p>
                State: <strong>{s.state}</strong> / {s.service} / Test label
                cost: {money(s.cost_minor)}
              </p>
              <p>
                Tracking: {s.tracking_number || 'Awaiting booking'} / Provider:{' '}
                {s.provider_reference || 'No confirmed receipt'}
              </p>
              <p>
                Parcel: {s.parcel.weight_grams}g / {s.parcel.length_cm} ×{' '}
                {s.parcel.width_cm} × {s.parcel.height_cm}cm
              </p>
              <ul>
                {s.items.map((i) => (
                  <li key={i.order_item_id}>
                    {
                      detail.remaining.find(
                        (l) => l.order_item_id === i.order_item_id,
                      )?.sku
                    }
                    : {i.quantity}
                  </li>
                ))}
              </ul>
              {s.last_error && <p role="status">{s.last_error}</p>}
              <div className="order-actions">
                {s.label_available && (
                  <Button
                    variant="secondary"
                    disabled={busy}
                    onClick={() => void download(s.id)}
                  >
                    Download sandbox label {s.id.slice(0, 8)}
                  </Button>
                )}
                {manage &&
                  ['draft', 'unknown', 'booking', 'failed'].includes(
                    s.state,
                  ) && (
                    <Button
                      disabled={busy}
                      onClick={() => setConfirm({ id: s.id, action: 'retry' })}
                    >
                      Retry same booking {s.id.slice(0, 8)}
                    </Button>
                  )}
                {manage && s.state === 'booked' && (
                  <Button
                    disabled={busy}
                    onClick={() => setConfirm({ id: s.id, action: 'dispatch' })}
                  >
                    Dispatch parcel {s.id.slice(0, 8)}
                  </Button>
                )}
                {manage && ['draft', 'failed', 'booked'].includes(s.state) && (
                  <Button
                    variant="secondary"
                    disabled={busy}
                    onClick={() => setConfirm({ id: s.id, action: 'cancel' })}
                  >
                    Cancel parcel {s.id.slice(0, 8)}
                  </Button>
                )}
              </div>
              <DataTable
                caption={'Booking history ' + s.id.slice(0, 8)}
                columns={['Action', 'Outcome', 'Receipt', 'Time']}
                rows={s.attempts.map((a) => [
                  a.action,
                  a.outcome,
                  a.provider_reference || '—',
                  new Date(a.created_at).toLocaleString(),
                ])}
              />
              <DataTable
                caption={'Tracking events ' + s.id.slice(0, 8)}
                columns={['State', 'Applied', 'Time']}
                rows={s.events.map((e) => [
                  e.state,
                  e.applied ? 'Yes' : 'Ignored safely',
                  new Date(e.occurred_at).toLocaleString(),
                ])}
              />
            </article>
          ))}
          <h3>Internal notes</h3>
          {detail.notes.map((n) => (
            <article key={n.id} className="order-note">
              <p>{n.body}</p>
              <small>
                {n.staff_name} / {new Date(n.created_at).toLocaleString()}
              </small>
            </article>
          ))}
          {manage && (
            <form
              onSubmit={(e) => {
                e.preventDefault();
                void addNote();
              }}
            >
              <Field
                label="Internal order note"
                value={note}
                maxLength={2000}
                required
                onChange={(e) => setNote(e.target.value)}
              />
              <Button type="submit" busy={busy} disabled={!note.trim()}>
                Save internal note
              </Button>
            </form>
          )}
          <h3>Customer support requests</h3>
          {detail.support_requests.length ? (
            detail.support_requests.map((s) => (
              <article key={s.id} className="order-note">
                <h4>{s.subject}</h4>
                <p>{s.message}</p>
                <small>
                  {s.state} / {new Date(s.created_at).toLocaleString()}
                </small>
              </article>
            ))
          ) : (
            <p>No customer support requests.</p>
          )}
          <h3>Notification history</h3>
          <DataTable
            caption="Traceable sandbox notifications"
            columns={['Type', 'Created', 'Delivery']}
            rows={detail.notifications.map((n) => [
              n.kind,
              new Date(n.created_at).toLocaleString(),
              n.delivered_at
                ? 'Private sandbox copy saved'
                : 'Queued — run scheduler and worker',
            ])}
          />
          <details>
            <summary>Inventory reservations and automated stock ledger</summary>
            <DataTable
              caption="Order stock reservations"
              columns={['Variant', 'Balance', 'Quantity', 'State']}
              rows={detail.reservations.map((r) => [
                r.variant_id,
                r.balance_id,
                r.quantity,
                r.state,
              ])}
            />
            <DataTable
              caption="Commerce stock events"
              columns={[
                'Balance',
                'Event',
                'On-hand change',
                'Reserved change',
              ]}
              rows={detail.stock_events.map((r) => [
                r.balance_id,
                r.kind,
                r.on_hand_delta,
                r.reserved_delta,
              ])}
            />
          </details>
        </section>
      )}
      <Modal
        open={!!confirm}
        title="Confirm shipment action"
        onClose={() => {
          if (!busy) setConfirm(null);
        }}
      >
        {confirm && (
          <>
            <p>
              {confirm.action === 'dispatch'
                ? 'Confirm physical dispatch of this sandbox parcel. This queues one private notification and does not deduct stock again.'
                : confirm.action === 'cancel'
                  ? 'Cancel this eligible sandbox parcel and void its sandbox receipt. This releases fulfilment allocation but does not refund or restock the paid order.'
                  : 'Retry the same booking identity. Any existing sandbox receipt is reused; no second label purchase is created.'}
            </p>
            <Button busy={busy} onClick={() => void act()}>
              Confirm {confirm.action}
            </Button>
            <Button
              variant="secondary"
              disabled={busy}
              onClick={() => setConfirm(null)}
            >
              Keep unchanged
            </Button>
          </>
        )}
      </Modal>
    </section>
  );
}
