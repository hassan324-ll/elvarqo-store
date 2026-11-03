import { useEffect, useState, useCallback, useRef } from 'react';
import {
  Badge,
  Button,
  DataTable,
  EmptyState,
  Field,
  Modal,
  Pagination,
  Select,
} from '../ui/components';
import type { StaffIdentity } from '../lib/staff-api';
import { Feedback, request, TextArea, useAction } from './common';
import type { InventoryRow, Location, Movement, Page } from './types';
export default function Inventory({ staff }: { staff: StaffIdentity }) {
  const manage = staff.permissions.includes('catalogue.manage');
  const [rows, setRows] = useState<Page<InventoryRow> | null>(null);
  const [locations, setLocations] = useState<Location[]>([]);
  const [q, setQ] = useState('');
  const [page, setPage] = useState(1);
  const [locationFilter, setLocationFilter] = useState('');
  const [selected, setSelected] = useState<InventoryRow | null>(null);
  const [history, setHistory] = useState<Page<Movement> | null>(null);
  const [historyPage, setHistoryPage] = useState(1);
  const [mode, setMode] = useState('adjust');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [quantity, setQuantity] = useState('');
  const [reason, setReason] = useState('');
  const [reference, setReference] = useState('');
  const [note, setNote] = useState('');
  const operation = useRef<{ fingerprint: string; id: string } | null>(null);
  const [review, setReview] = useState(false);
  const [locName, setLocName] = useState('');
  const [locCode, setLocCode] = useState('');
  const action = useAction();
  const { run } = action;
  const mutation = useAction();
  const load = useCallback(async () => {
    const [stock, l] = await Promise.all([
      request<Page<InventoryRow>>(
        `inventory?page=${page}&q=${encodeURIComponent(q)}${locationFilter ? '&location_id=' + locationFilter : ''}`,
      ),
      request<{ items: Location[] }>('locations'),
    ]);
    setRows(stock);
    setLocations(l.items);
  }, [page, q, locationFilter]);
  const loadHistory = useCallback(async (variant: number, p = 1) => {
    setHistory(
      await request<Page<Movement>>(
        `movements?variant_id=${variant}&page=${p}`,
      ),
    );
  }, []);
  useEffect(() => {
    void run(load);
  }, [run, load]);
  useEffect(() => {
    if (selected) void run(() => loadHistory(selected.id, historyPage));
  }, [selected, historyPage, run, loadHistory]);
  function select(row: InventoryRow) {
    setSelected(row);
    setHistory(null);
    setHistoryPage(1);
    setFrom(String(row.balances[0]?.location_id || locations[0]?.id || ''));
    setTo('');
    setQuantity('');
    setReason('');
    setReference('');
    setNote('');
  }
  const balance = selected?.balances.find(
    (b) => b.location_id === Number(from),
  );
  const onHand = balance?.on_hand || 0;
  const reserved = balance?.reserved || 0;
  const result =
    onHand + (mode === 'adjust' ? Number(quantity) : -Number(quantity));
  async function commit() {
    setReview(false);
    const fingerprint = JSON.stringify([
      selected?.id,
      mode,
      from,
      to,
      quantity,
      reason,
      reference,
      note,
    ]);
    const key =
      operation.current?.fingerprint === fingerprint
        ? operation.current.id
        : crypto.randomUUID();
    operation.current = { fingerprint, id: key };
    await mutation.run(async () => {
      await request(mode === 'adjust' ? 'adjustments' : 'transfers', {
        operation_id: key,
        variant_id: selected!.id,
        location_id: Number(from),
        ...(mode === 'adjust'
          ? { delta: Number(quantity) }
          : { to_location_id: Number(to), quantity: Number(quantity) }),
        reason,
        reference: reference || null,
        note: note || null,
      });
      const data = await request<Page<InventoryRow>>(
        'inventory?q=' + encodeURIComponent(selected!.sku),
      );
      setSelected(data.items.find((v) => v.id === selected!.id) || null);
      await loadHistory(selected!.id);
      await load();
      setQuantity('');
      setReason('');
      operation.current = null;
      mutation.setNotice(
        'Stock movement recorded. The ledger retains your reason and staff ID.',
      );
    });
  }
  return (
    <>
      <header className="admin-page-heading">
        <div>
          <p className="eyebrow">ELVARQO / STOCK CONTROL</p>
          <h1>Inventory workspace</h1>
          <p className="muted">
            Trace every movement. On-hand, reserved and available stay distinct.
          </p>
        </div>
        <Badge key="cell-1">Live records</Badge>
      </header>
      <section className="panel">
        <form
          className="catalogue-filters"
          onSubmit={(e) => {
            e.preventDefault();
            setPage(1);
            void run(load);
          }}
        >
          <Field
            label="Search inventory"
            type="search"
            value={q}
            onChange={(e) => setQ(e.target.value)}
          />
          <Select
            label="Location filter"
            value={locationFilter}
            onChange={(e) => setLocationFilter(e.target.value)}
          >
            <option value="">All locations</option>
            {locations.map((l) => (
              <option key={l.id} value={l.id}>
                {l.name}
              </option>
            ))}
          </Select>
          <Button
            key="cell-2"
            variant="secondary"
            busy={action.busy}
            type="submit"
          >
            Search stock
          </Button>
        </form>
        <Feedback action={action} />
        {rows?.items.length ? (
          <>
            <DataTable
              caption="Active variants and warehouse balances"
              columns={[
                'Variant',
                'Location',
                'On-hand',
                'Reserved',
                'Available',
                'Status',
                'Action',
              ]}
              rows={rows.items.flatMap((row) => {
                const b = row.balances.length
                  ? row.balances
                  : [
                      {
                        location_id: 0,
                        location: 'No balance recorded',
                        on_hand: 0,
                        reserved: 0,
                        available: 0,
                      },
                    ];
                return b.map((balance) => [
                  <span key="cell-3">
                    <strong key="cell-4">{row.title}</strong>
                    <br />
                    {row.sku}
                  </span>,
                  balance.location,
                  balance.on_hand,
                  balance.reserved,
                  balance.available,
                  <Badge
                    key="cell-5"
                    tone={
                      balance.available > row.low_stock_threshold
                        ? 'success'
                        : 'warning'
                    }
                  >
                    {balance.available === 0
                      ? 'Out of stock'
                      : balance.available <= row.low_stock_threshold
                        ? 'Low stock'
                        : 'In stock'}
                  </Badge>,
                  <Button
                    key="cell-6"
                    variant="quiet"
                    onClick={() => select(row)}
                  >
                    Inspect {row.sku}
                  </Button>,
                ]);
              })}
            />
            <Pagination
              page={rows.page}
              total={rows.last_page}
              onChange={setPage}
            />
          </>
        ) : (
          <EmptyState title="No matching variants">
            Create and save product variants before recording stock.
          </EmptyState>
        )}
      </section>
      {selected && (
        <section className="panel" id="stock-details">
          <div className="card-heading">
            <h2>
              {selected.title} / {selected.sku}
            </h2>
            <Button
              key="cell-7"
              variant="quiet"
              onClick={() => setSelected(null)}
            >
              Close stock details
            </Button>
          </div>
          <Feedback action={mutation} />
          {manage && (
            <fieldset className="catalogue-fieldset" disabled={mutation.busy}>
              <h3>Stock adjustment & transfer</h3>
              <div className="catalogue-grid">
                <Select
                  label="Movement type"
                  value={mode}
                  onChange={(e) => setMode(e.target.value)}
                >
                  <option value="adjust">Adjustment</option>
                  <option value="transfer">Transfer</option>
                </Select>
                <Select
                  label="From / adjustment location"
                  value={from}
                  onChange={(e) => setFrom(e.target.value)}
                >
                  <option value="">Choose location</option>
                  {locations
                    .filter((l) => l.active)
                    .map((l) => (
                      <option key={l.id} value={l.id}>
                        {l.name}
                      </option>
                    ))}
                </Select>
                {mode === 'transfer' && (
                  <Select
                    label="Destination location"
                    value={to}
                    onChange={(e) => setTo(e.target.value)}
                  >
                    <option value="">Choose destination</option>
                    {locations
                      .filter((l) => l.active && l.id !== Number(from))
                      .map((l) => (
                        <option key={l.id} value={l.id}>
                          {l.name}
                        </option>
                      ))}
                  </Select>
                )}
                <Field
                  label={
                    mode === 'adjust'
                      ? 'Signed adjustment'
                      : 'Transfer quantity'
                  }
                  type="number"
                  step={1}
                  value={quantity}
                  onChange={(e) => setQuantity(e.target.value)}
                />
                <Field
                  label="Movement reason"
                  value={reason}
                  onChange={(e) => setReason(e.target.value)}
                  maxLength={240}
                />
                <Field
                  label="Reference"
                  value={reference}
                  onChange={(e) => setReference(e.target.value)}
                />
              </div>
              <TextArea
                label="Internal stock note"
                value={note}
                onChange={(e) => setNote(e.target.value)}
              />
              <p>
                Current on-hand: <strong key="cell-8">{onHand}</strong> /
                Reserved: <strong key="cell-9">{reserved}</strong> / Projected
                on-hand:{' '}
                <strong key="cell-10">
                  {Number.isFinite(result) ? result : '—'}
                </strong>
              </p>
              <Button
                key="cell-11"
                disabled={
                  !from ||
                  !quantity ||
                  !Number.isInteger(Number(quantity)) ||
                  Number(quantity) === 0 ||
                  reason.trim().length < 3 ||
                  (mode === 'transfer' && (!to || Number(quantity) < 1))
                }
                onClick={() => setReview(true)}
              >
                Review stock movement
              </Button>
            </fieldset>
          )}
          <h3>Movement history</h3>
          <DataTable
            caption="Immutable signed stock ledger"
            columns={[
              'Time (UTC)',
              'Location ID',
              'Change',
              'On-hand after',
              'Reserved after',
              'Reason / reference',
              'Actor ID',
              'Operation',
            ]}
            rows={(history?.items || []).map((m) => [
              m.created_at + ' UTC',
              m.location_id,
              <strong key="cell-12">
                {m.delta > 0 ? '+' : ''}
                {m.delta}
              </strong>,
              m.on_hand_after,
              m.reserved_after,
              <span key="cell-13">
                {m.reason}
                <br />
                {m.reference || '—'}
              </span>,
              m.staff_user_id,
              <code key="cell-14" className="operation-id">
                {m.operation_id}
              </code>,
            ])}
          />
          {history && (
            <Pagination
              page={history.page}
              total={history.last_page}
              onChange={setHistoryPage}
            />
          )}
          <p className="muted">
            Reserved stock is read-only here. Checkout reservation and release
            arrive in Phase 6.
          </p>
        </section>
      )}
      {manage && (
        <section className="panel">
          <h2>Add stock location</h2>
          <div className="catalogue-grid">
            <Field
              label="Location name"
              value={locName}
              onChange={(e) => setLocName(e.target.value)}
            />
            <Field
              label="Location code"
              value={locCode}
              onChange={(e) => setLocCode(e.target.value.toUpperCase())}
            />
          </div>
          <Button
            key="cell-15"
            busy={action.busy}
            onClick={() =>
              void action.run(async () => {
                await request('locations', { name: locName, code: locCode });
                setLocName('');
                setLocCode('');
                await load();
                action.setNotice('Stock location created.');
              })
            }
          >
            Save location
          </Button>
        </section>
      )}
      <Modal
        open={review}
        title="Confirm stock movement"
        onClose={() => setReview(false)}
      >
        <p>
          {selected?.sku}:{' '}
          {mode === 'adjust'
            ? `apply ${quantity} units`
            : `transfer ${quantity} units to location ${to}`}
          . Resulting source on-hand: {result}; reserved: {reserved}.
        </p>
        <p>Reason: {reason}</p>
        <Button key="cell-16" onClick={commit}>
          Confirm stock movement
        </Button>
        <Button
          key="cell-17"
          variant="secondary"
          onClick={() => setReview(false)}
        >
          Cancel
        </Button>
      </Modal>
    </>
  );
}
