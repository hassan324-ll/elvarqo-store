import { useEffect, useRef, useState } from 'react';
import { Button } from '../ui/components';
import { ProductField as Field } from './ProductField';
import SizePicker from './SizePicker';
import {
  colourPalette,
  optionList,
  priceMinor,
  suggestColour,
  variantCombinations,
  variantPrices,
} from './variant-helper';
import './quick-variants.css';

export type VariantPhoto = { id: string; url: string; alt: string };
type PhotoColour = VariantPhoto & {
  colour: string;
  hex: string;
  selected: boolean;
  failed: boolean;
};
type Row = ReturnType<typeof variantCombinations>[number] & {
  compare_at_minor: number | null;
};
export default function QuickVariants({
  photos,
  existing,
  productId,
  onGenerate,
  onPrice,
}: {
  photos: VariantPhoto[];
  existing: { colour: string; size: string; sku: string }[];
  productId: number;
  onGenerate: (rows: Row[]) => void;
  onPrice: (price: string, sale: string) => void;
}) {
  const [review, setReview] = useState<PhotoColour[]>([]);
  const [manualColours, setManualColours] = useState('');
  const [sizes, setSizes] = useState('');
  const [price, setPrice] = useState('');
  const [sale, setSale] = useState('');
  let pricing: ReturnType<typeof variantPrices> | null = null;
  let pricingError = '';
  try {
    pricing = variantPrices(price, sale);
  } catch (error) {
    if (sale)
      pricingError =
        error instanceof Error ? error.message : 'Check the prices.';
  }
  const [confirmed, setConfirmed] = useState(false);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('');
  const controller = useRef<AbortController | null>(null);
  useEffect(() => {
    return () => {
      controller.current?.abort();
    };
  }, []);
  const colours = optionList(
    [
      ...review.filter((p) => p.selected).map((p) => p.colour),
      ...optionList(manualColours),
    ].join(','),
  );
  async function detect() {
    controller.current?.abort();
    const control = new AbortController();
    controller.current = control;
    setBusy(true);
    setMessage('');
    setConfirmed(false);
    const results: PhotoColour[] = [];
    for (const photo of photos) {
      if (control.signal.aborted) return;
      let suggestion = null;
      try {
        const url = new URL(photo.url, location.href);
        const api = new URL(
          import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1',
        );
        if (
          url.protocol !== 'blob:' &&
          url.origin !== api.origin &&
          url.origin !== location.origin
        )
          throw Error('Unsupported photo source');
        const response = await fetch(url, {
          credentials: 'include',
          signal: AbortSignal.any([control.signal, AbortSignal.timeout(15000)]),
        });
        if (!response.ok) throw Error('Photo unavailable');
        const blob = await response.blob();
        if (
          blob.size > 8 * 1024 * 1024 ||
          !['image/jpeg', 'image/png', 'image/webp'].includes(blob.type)
        )
          throw Error('Unsupported photo');
        const bitmap = await createImageBitmap(blob, {
          resizeWidth: 64,
          resizeHeight: 64,
        });
        try {
          const canvas = document.createElement('canvas');
          canvas.width = canvas.height = 64;
          const context = canvas.getContext('2d', { willReadFrequently: true });
          if (!context) throw Error('Image analysis unavailable');
          context.drawImage(bitmap, 0, 0, 64, 64);
          suggestion = suggestColour(
            context.getImageData(0, 0, 64, 64).data,
            64,
            64,
          );
        } finally {
          bitmap.close();
        }
      } catch {
        /* Keep an editable, unselected photo when decoding or access fails. */
      }
      if (control.signal.aborted) return;
      results.push({
        ...photo,
        colour: suggestion?.name || '',
        hex: suggestion?.hex || '#eeeeee',
        selected: !!suggestion,
        failed: !suggestion,
      });
      setMessage(`Checking photo ${results.length} of ${photos.length}…`);
    }
    setReview(results);
    setConfirmed(false);
    setBusy(false);
    setMessage(
      'Suggestions ready. Check every selected photo and correct its colour before generating variants.',
    );
  }
  function generate() {
    try {
      if (review.some((p) => p.selected && !p.colour.trim()))
        throw Error(
          'Enter a colour for every selected photo, or exclude that photo.',
        );
      const prefix = `EV${productId || 'NEW'}-${crypto.randomUUID().slice(0, 5)}`;
      const rows = variantCombinations(
        colours,
        optionList(sizes),
        existing,
        price,
        prefix,
      );
      if (!rows.length) {
        setMessage(
          'These colour and size combinations already exist. Existing prices were kept.',
        );
        return;
      }
      const prices = variantPrices(price, sale);
      onGenerate(rows.map((row) => ({ ...row, ...prices })));
      setMessage(
        `${rows.length} variants added to the form. Save draft to store them. Existing variants and prices were kept.`,
      );
    } catch (error) {
      setMessage(
        error instanceof Error ? error.message : 'Check your options.',
      );
    }
  }
  return (
    <div className="quick-variants">
      <div className="card-heading">
        <div>
          <h3>Quick variant builder</h3>
          <p>
            Review photo colours, enter sizes and set regular or sale prices.
          </p>
        </div>
        <Button
          variant="secondary"
          disabled={!photos.length || busy}
          onClick={() => void detect()}
        >
          {busy
            ? 'Checking photos…'
            : review.length
              ? 'Check photos again'
              : 'Suggest colours from photos'}
        </Button>
      </div>
      <p className="muted">
        Colour suggestions use the centre of each photo on this device.
        Lighting, backgrounds and patterns can affect results; edit or exclude
        any incorrect suggestion. Photos stay private and unchanged.
      </p>
      {review.length > 0 && (
        <div className="colour-photo-grid">
          {review.map((photo, i) => (
            <article
              className={
                'colour-photo-card' + (!photo.selected ? ' excluded' : '')
              }
              key={photo.id}
            >
              <img
                src={photo.url}
                alt={photo.alt || `Product photo ${i + 1}`}
                loading="lazy"
              />
              <label className="catalogue-check">
                <input
                  type="checkbox"
                  checked={photo.selected}
                  onChange={(e) => {
                    setReview((rows) =>
                      rows.map((p) =>
                        p.id === photo.id
                          ? { ...p, selected: e.target.checked }
                          : p,
                      ),
                    );
                    setConfirmed(false);
                  }}
                />
                Use photo {i + 1}
              </label>
              <div className="colour-name">
                <span
                  style={{ backgroundColor: photo.hex }}
                  aria-hidden="true"
                />
                <Field
                  label={`Colour for photo ${i + 1}`}
                  value={photo.colour}
                  maxLength={80}
                  list="variant-colour-names"
                  placeholder="Enter colour"
                  onChange={(e) => {
                    setReview((rows) =>
                      rows.map((p) =>
                        p.id === photo.id
                          ? { ...p, colour: e.target.value }
                          : p,
                      ),
                    );
                    setConfirmed(false);
                  }}
                />
              </div>
              {photo.failed && (
                <small>
                  Could not suggest a colour. Enter it and select this photo if
                  appropriate.
                </small>
              )}
            </article>
          ))}
        </div>
      )}
      <datalist id="variant-colour-names">
        {colourPalette.map((c) => (
          <option value={c.name} key={c.name} />
        ))}
      </datalist>
      <div className="catalogue-grid">
        <Field
          label="Additional colours"
          placeholder="e.g. Sage, Charcoal"
          value={manualColours}
          maxLength={1000}
          hint="Comma-separated. Use this without photo analysis too."
          onChange={(e) => {
            setManualColours(e.target.value);
            setConfirmed(false);
          }}
        />
        <SizePicker
          value={sizes}
          onChange={(value) => {
            setSizes(value);
            setConfirmed(false);
          }}
        />
        <Field
          label="Regular price for new variants (GBP)"
          value={price}
          type="number"
          step="0.01"
          min="0.01"
          max="1000000"
          inputMode="decimal"
          hint="Use the arrows to change the price by £0.01."
          placeholder="0.00"
          onChange={(e) => setPrice(e.target.value)}
        />
        <Field
          label="Sale price for new variants (GBP, optional)"
          value={sale}
          type="number"
          step="0.01"
          min="0.01"
          max="1000000"
          inputMode="decimal"
          hint="Leave empty for no sale. Must be lower than the regular price."
          placeholder="Optional"
          error={pricingError}
          onChange={(e) => setSale(e.target.value)}
        />
        {pricing && pricing.compare_at_minor !== null && (
          <p className="catalogue-notice">
            Sale: <del>£{(pricing.compare_at_minor / 100).toFixed(2)}</del>{' '}
            <strong>£{(pricing.price_minor / 100).toFixed(2)}</strong>
          </p>
        )}
        <div className="quick-option-summary">
          <strong>
            {colours.length} colours × {optionList(sizes).length} sizes
          </strong>
          <p>{colours.join(', ') || 'No colours selected yet'}</p>
          <small>
            Repeated photos of a colour create one colour option. Existing
            combinations are skipped.
          </small>
        </div>
      </div>
      <label className="catalogue-check">
        <input
          type="checkbox"
          checked={confirmed}
          onChange={(e) => setConfirmed(e.target.checked)}
        />
        I have checked the selected colours and confirmed these sizes are
        available from the supplier.
      </label>
      <div className="quick-variant-actions">
        <Button
          disabled={
            busy ||
            !confirmed ||
            !colours.length ||
            !optionList(sizes).length ||
            priceMinor(price) === null ||
            !pricing
          }
          onClick={generate}
        >
          Generate variants
        </Button>
        {!!existing.length && (
          <Button
            variant="secondary"
            disabled={busy || priceMinor(price) === null || !pricing}
            onClick={() => {
              if (
                window.confirm(
                  `Apply regular price £${(priceMinor(price)! / 100).toFixed(2)} and ${sale.trim() ? `sale price £${(priceMinor(sale)! / 100).toFixed(2)}` : 'remove any sale price'} to all ${existing.length} variants in this form? Save draft afterwards.`,
                )
              ) {
                onPrice(
                  (priceMinor(price)! / 100).toFixed(2),
                  sale.trim() ? (priceMinor(sale)! / 100).toFixed(2) : '',
                );
                setMessage(
                  'Prices updated in the form. Save draft to store them.',
                );
              }
            }}
          >
            Apply prices to all variants
          </Button>
        )}
      </div>
      <p className="muted">
        Local SKUs are generated automatically. Supplier IDs, stock quantities
        and composition are not inferred. Link saved photos to variants after
        saving.
      </p>
      {message && (
        <p role="status" className="catalogue-notice">
          {message}
        </p>
      )}
    </div>
  );
}
