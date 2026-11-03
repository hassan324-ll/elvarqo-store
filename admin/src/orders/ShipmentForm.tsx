import { useState } from 'react';
import { Button, Field, Select, Modal } from '../ui/components';
import { staffRequest } from '../lib/staff-api';
import { type Detail, type Quote, money } from './types';
export default function ShipmentForm({
  order,
  onSaved,
}: {
  order: Detail;
  onSaved: () => Promise<void>;
}) {
  const [quantities, setQuantities] = useState<Record<number, number>>({});
  const [location, setLocation] = useState(
    String(order.locations[0]?.id || ''),
  );
  const [service, setService] = useState('sandbox_standard');
  const [parcel, setParcel] = useState({
    weight_grams: 500,
    length_cm: 30,
    width_cm: 24,
    height_cm: 8,
  });
  const [instructions, setInstructions] = useState('');
  const [quote, setQuote] = useState<Quote | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  async function getQuote() {
    setBusy(true);
    setError('');
    try {
      setQuote(
        await staffRequest<Quote>(`orders/${order.id}/shipment-quotes`, {
          items: order.remaining
            .filter((l) => (quantities[l.order_item_id] || 0) > 0)
            .map((l) => ({
              order_item_id: l.order_item_id,
              quantity: quantities[l.order_item_id],
            })),
          location_id: Number(location),
          service,
          parcel,
          instructions,
        }),
      );
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Quote unavailable.');
    } finally {
      setBusy(false);
    }
  }
  async function book() {
    if (!quote) return;
    setBusy(true);
    setError('');
    try {
      await staffRequest(`orders/${order.id}/shipments`, {
        quote_id: quote.id,
        confirmed: true,
      });
      setQuote(null);
      setQuantities({});
      await onSaved();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Booking unavailable.');
    } finally {
      setBusy(false);
    }
  }
  return (
    <section className="order-tool">
      <h3>Book a shipment</h3>
      <p>
        Local courier sandbox. Labels are not valid for postage. Only remaining
        units at their committed location can be allocated.
      </p>
      {error && <p role="alert">{error}</p>}
      <form
        onSubmit={(e) => {
          e.preventDefault();
          void getQuote();
        }}
      >
        <fieldset>
          <legend>Items in this parcel</legend>
          {order.remaining.map((l) => (
            <Field
              key={l.order_item_id}
              label={'Ship quantity / ' + l.sku}
              type="number"
              min={0}
              max={l.remaining}
              value={quantities[l.order_item_id] || 0}
              disabled={busy || l.remaining === 0}
              onChange={(e) =>
                setQuantities({
                  ...quantities,
                  [l.order_item_id]: Number(e.target.value),
                })
              }
              hint={`${l.colour} / ${l.size}: ${l.ordered} ordered, ${l.allocated} allocated, ${l.remaining} remaining`}
            />
          ))}
        </fieldset>
        <div className="order-form-grid">
          <Select
            label="Dispatch location"
            value={location}
            required
            onChange={(e) => setLocation(e.target.value)}
          >
            {order.locations.map((l) => (
              <option key={l.id} value={l.id}>
                {l.name}
              </option>
            ))}
          </Select>
          <Select
            label="Courier service"
            value={service}
            onChange={(e) => setService(e.target.value)}
          >
            {Object.entries(order.courier.services).map(([k, s]) => (
              <option key={k} value={k}>
                {s.name}
              </option>
            ))}
          </Select>
          {(
            ['weight_grams', 'length_cm', 'width_cm', 'height_cm'] as const
          ).map((k) => (
            <Field
              key={k}
              label={
                {
                  weight_grams: 'Parcel weight grams',
                  length_cm: 'Parcel length cm',
                  width_cm: 'Parcel width cm',
                  height_cm: 'Parcel height cm',
                }[k]
              }
              type="number"
              min={1}
              max={k === 'weight_grams' ? 30000 : 120}
              required
              value={parcel[k]}
              onChange={(e) =>
                setParcel({ ...parcel, [k]: Number(e.target.value) })
              }
            />
          ))}
        </div>
        <Field
          label="Delivery instructions (optional)"
          value={instructions}
          maxLength={240}
          onChange={(e) => setInstructions(e.target.value)}
        />
        <Button
          type="submit"
          busy={busy}
          disabled={!Object.values(quantities).some((n) => n > 0) || !location}
        >
          Get shipping quote
        </Button>
      </form>
      <p className="muted">{order.courier.origin_note}</p>
      <Modal
        open={!!quote}
        title="Review sandbox label booking"
        onClose={() => {
          if (!busy) setQuote(null);
        }}
      >
        {quote && (
          <>
            <p>{quote.warning}</p>
            <p>
              {quote.payload.service_name} / Origin:{' '}
              {quote.payload.location_name}
            </p>
            <p>
              Destination:{' '}
              {Object.values(quote.payload.destination)
                .filter(Boolean)
                .join(', ')}
            </p>
            <p>
              Parcel: {quote.payload.parcel.weight_grams}g /{' '}
              {quote.payload.parcel.length_cm} × {quote.payload.parcel.width_cm}{' '}
              × {quote.payload.parcel.height_cm}cm
            </p>
            <ul>
              {quote.payload.items.map((l) => (
                <li key={l.order_item_id}>
                  {
                    order.remaining.find(
                      (i) => i.order_item_id === l.order_item_id,
                    )?.sku
                  }
                  : {l.quantity}
                </li>
              ))}
            </ul>
            <p>
              Sandbox label cost: <strong>{money(quote.cost_minor)}</strong>. No
              money will be charged. Valid until{' '}
              {new Date(quote.expires_at).toLocaleTimeString()}.
            </p>
            <Button busy={busy} onClick={() => void book()}>
              Confirm sandbox label booking
            </Button>
            <Button
              variant="secondary"
              disabled={busy}
              onClick={() => setQuote(null)}
            >
              Cancel review
            </Button>
          </>
        )}
      </Modal>
    </section>
  );
}
