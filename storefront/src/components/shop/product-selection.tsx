'use client';
import { useCallback, useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { Button, Modal } from '../../ui/components';
import { type Product, money, loadSaved } from '../../lib/catalogue-types';
import { SavePiece } from './wishlist';
import ProductImage from './product-image';
import { useBag } from '../checkout/bag-provider';
export default function ProductSelection({
  initial,
  initialVariant,
  compact = false,
}: {
  initial: Product;
  initialVariant?: number;
  compact?: boolean;
}) {
  const first = initial.variants.find((v) => v.id === initialVariant);
  const [product, setProduct] = useState(initial);
  const [colour, setColour] = useState(
    first?.colour || initial.variants[0]?.colour || '',
  );
  const [size, setSize] = useState(first?.size || '');
  const [image, setImage] = useState(0);
  const [guide, setGuide] = useState(false);
  const [units, setUnits] = useState('cm');
  const [state, setState] = useState('checking');
  const [error, setError] = useState('');
  const [checked, setChecked] = useState('');
  const [added, setAdded] = useState(false);
  const [adding, setAdding] = useState(false);
  const { add } = useBag();
  const flight = useRef<AbortController | null>(null);
  const refresh = useCallback(async () => {
    flight.current?.abort();
    const controller = new AbortController();
    flight.current = controller;
    await Promise.resolve();
    if (controller.signal.aborted) return null;
    setState('checking');
    setError('');
    try {
      const result = await loadSaved([initial.id], controller.signal);
      if (controller.signal.aborted) return null;
      const p = result.items[0];
      if (!p) {
        setState('unavailable');
        return null;
      }
      setProduct(p);
      setState('current');
      setChecked(new Date().toISOString());
      return p;
    } catch (e) {
      if (!controller.signal.aborted) {
        setState('failed');
        setError(
          e instanceof Error ? e.message : 'We could not refresh availability.',
        );
      }
      return null;
    }
  }, [initial.id]);
  useEffect(() => {
    const initialRequest = setTimeout(() => void refresh(), 0);
    const timer = setInterval(() => {
      if (document.visibilityState === 'visible') void refresh();
    }, 30000);
    const focus = () => {
      if (document.visibilityState === 'visible') void refresh();
    };
    window.addEventListener('focus', focus);
    document.addEventListener('visibilitychange', focus);
    return () => {
      clearTimeout(initialRequest);
      clearInterval(timer);
      flight.current?.abort();
      window.removeEventListener('focus', focus);
      document.removeEventListener('visibilitychange', focus);
    };
  }, [refresh]);
  const selected = product.variants.find(
    (v) => v.colour === colour && v.size === size,
  );
  const colours = [...new Set(product.variants.map((v) => v.colour))];
  const sizes = [...new Set(product.variants.map((v) => v.size))];
  const colourIds = product.variants
    .filter((v) => v.colour === colour)
    .map((v) => v.id);
  const gallery = product.media
    .filter(
      (m) =>
        !m.variant_id ||
        (selected
          ? m.variant_id === selected.id
          : colourIds.includes(m.variant_id)),
    )
    .sort((a, b) => Number(!a.variant_id) - Number(!b.variant_id));
  const main = gallery[Math.min(image, Math.max(0, gallery.length - 1))];
  async function review() {
    const p = await refresh();
    if (!p) return;
    const v = p.variants.find((v) => v.colour === colour && v.size === size);
    if (!v || v.available < 1) {
      setError('This choice is unavailable. Choose another size or colour.');
      return;
    }
    setAdding(true);
    try {
      await add(v.id);
      setAdded(true);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not add this piece.');
    } finally {
      setAdding(false);
    }
  }
  if (state === 'unavailable')
    return (
      <section className="shop-recovery" role="status">
        <h2>This piece is no longer available.</h2>
        <p>
          It may have been withdrawn from the collection. Saved pieces can be
          removed from your wishlist.
        </p>
        <Link href="/shop" className="button button-primary">
          Explore available pieces
        </Link>
      </section>
    );
  const displayPrice =
    selected?.price_minor ??
    Math.min(
      ...product.variants
        .filter((v) => v.colour === colour)
        .map((v) => v.price_minor),
    );
  const price = Number.isFinite(displayPrice)
    ? displayPrice
    : Math.min(...product.variants.map((v) => v.price_minor));
  const measurementRows = product.variants
    .filter((v) => v.colour === colour)
    .map((v) => ({
      ...product.measurements.find(
        (m) => m.size.toLowerCase() === v.size.toLowerCase(),
      ),
      ...v.measurements,
      size: v.size,
      id: v.id,
    }));
  return (
    <>
      <div
        className={`product-detail-grid ${compact ? 'quick-selection' : ''}`}
      >
        <section
          className="product-gallery"
          aria-label={`${product.title} gallery`}
        >
          <ProductImage key={main?.id || 'missing'} media={main} priority />
          {gallery.length > 1 && (
            <div
              className="gallery-thumbnails"
              aria-label="Choose product image"
            >
              {gallery.map((m, i) => (
                <button
                  key={m.id}
                  type="button"
                  aria-label={`Show image ${i + 1}: ${m.alt}`}
                  aria-pressed={m.id === main?.id}
                  onClick={() => setImage(i)}
                >
                  <img
                    src={m.sources[0]?.url || m.url}
                    alt=""
                    width={80}
                    height={100}
                  />
                </button>
              ))}
            </div>
          )}
        </section>
        <section className="product-information">
          <p className="eyebrow">
            {product.collections[0]?.title || product.product_type}
          </p>
          {compact ? <h2>{product.title}</h2> : <h1>{product.title}</h1>}
          <p className="product-price">
            {!selected ? 'From ' : ''}
            {money(price)}{' '}
            {selected?.compare_at_minor && (
              <del>{money(selected.compare_at_minor)}</del>
            )}
          </p>
          <p className="product-copy">{product.description}</p>
          <fieldset className="choice-fieldset">
            <legend>Colour: {colour || 'Choose a colour'}</legend>
            <div className="variant-options">
              {colours.map((c) => (
                <label className="variant-option" key={c}>
                  <input
                    type="radio"
                    name={'colour-' + initial.id + (compact ? '-quick' : '')}
                    value={c}
                    checked={colour === c}
                    onChange={() => {
                      setColour(c);
                      setSize('');
                      setImage(0);
                      setError('');
                    }}
                  />
                  <span>{c}</span>
                </label>
              ))}
            </div>
          </fieldset>
          <fieldset className="choice-fieldset">
            <legend>Select size</legend>
            <div className="variant-options">
              {sizes.map((s) => {
                const v = product.variants.find(
                  (v) => v.colour === colour && v.size === s,
                );
                return (
                  <label
                    className={`variant-option ${!v || v.available < 1 ? 'unavailable-option' : ''}`}
                    key={s}
                  >
                    <input
                      type="radio"
                      name={'size-' + initial.id + (compact ? '-quick' : '')}
                      value={s}
                      checked={size === s}
                      disabled={!v}
                      onChange={() => {
                        setSize(s);
                        setImage(0);
                        setError('');
                      }}
                    />
                    <span>
                      {s}
                      {!v
                        ? ' — not offered'
                        : v.available < 1
                          ? ' — sold out'
                          : ''}
                    </span>
                  </label>
                );
              })}
            </div>
          </fieldset>
          <button
            className="text-link size-guide-trigger"
            type="button"
            onClick={() => setGuide(true)}
          >
            Size guide
          </button>
          <p className="selection-status" role="status" aria-live="polite">
            {state === 'checking'
              ? 'Checking current price and availability…'
              : state === 'failed'
                ? 'Availability could not be verified. Refresh before reviewing a selection.'
                : selected
                  ? selected.available > 0
                    ? `${selected.colour} / ${selected.size} · In stock`
                    : 'This size is sold out. Choose another size or colour.'
                  : size
                    ? 'This combination is no longer offered. Choose another size.'
                    : 'Choose a colour and size to see the exact price and availability.'}
          </p>
          {error && (
            <p role="alert" className="shop-error">
              {error}
            </p>
          )}
          <div className="selection-actions">
            <Button
              onClick={() => void refresh()}
              variant="secondary"
              busy={state === 'checking'}
            >
              Refresh availability
            </Button>
            <SavePiece id={product.id} variant={selected?.id ?? null} />
            <Button
              disabled={
                !selected ||
                selected.available < 1 ||
                state !== 'current' ||
                adding
              }
              onClick={() => void review()}
            >
              {adding ? 'Adding…' : 'Add to bag'}
            </Button>
          </div>
          <p className="muted purchase-note">
            Prices and quantities are checked by the server. Bag items do not
            reserve stock. Sandbox payments only.
          </p>
          {checked && (
            <small>
              Availability checked{' '}
              <time dateTime={checked}>
                {new Date(checked).toLocaleTimeString('en-GB')}
              </time>
            </small>
          )}
          {!compact && (
            <div className="product-accordions">
              <details open>
                <summary>Material & construction</summary>
                <p>{product.material}</p>
              </details>
              <details open>
                <summary>Fit & measurements</summary>
                <p>{selected?.fit || product.fit}</p>
                <button
                  type="button"
                  className="text-link"
                  onClick={() => setGuide(true)}
                >
                  View garment measurements
                </button>
              </details>
              <details open>
                <summary>Care instructions</summary>
                <p>{selected?.care || product.care}</p>
              </details>
            </div>
          )}
          {compact && (
            <Link
              href={`/products/${product.slug}${selected ? '?variant=' + selected.id : ''}`}
              className="text-link"
              prefetch={false}
            >
              View full product details
            </Link>
          )}
        </section>
      </div>
      <Modal open={guide} title="Find your fit" onClose={() => setGuide(false)}>
        <p>
          {product.title} / {colour} / garment measurements
        </p>
        <fieldset className="unit-switch">
          <legend>Measurement units</legend>
          {['cm', 'in'].map((unit) => (
            <label key={unit}>
              <input
                type="radio"
                name={'units-' + product.id + (compact ? '-quick' : '')}
                checked={units === unit}
                onChange={() => setUnits(unit)}
              />
              {unit === 'cm' ? 'Centimetres' : 'Inches'}
            </label>
          ))}
        </fieldset>
        <table className="size-table">
          <caption>Supplier-entered garment measurements</caption>
          <thead>
            <tr>
              {['Size', 'Chest', 'Length', 'Shoulder'].map((s) => (
                <th scope="col" key={s}>
                  {s}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {measurementRows.map((m) => (
              <tr key={m.id}>
                <th scope="row">{m.size}</th>
                {(['chest_cm', 'length_cm', 'shoulder_cm'] as const).map(
                  (k) => (
                    <td key={k}>
                      {typeof m[k] === 'number'
                        ? `${units === 'cm' ? m[k] : Number((m[k]! / 2.54).toFixed(1))} ${units}`
                        : 'Not supplied'}
                    </td>
                  ),
                )}
              </tr>
            ))}
          </tbody>
        </table>
        <p className="muted">
          Garment measurements may differ from body measurements. Check the
          product fit information before choosing.
        </p>
        <div className="variant-options">
          {measurementRows.map((m) => (
            <Button
              key={m.id}
              variant="secondary"
              onClick={() => {
                setSize(m.size);
                setImage(0);
                setGuide(false);
              }}
            >
              Choose size {m.size}
            </Button>
          ))}
        </div>
      </Modal>
      <Modal
        open={added}
        title="Added to your bag"
        onClose={() => setAdded(false)}
      >
        <p>
          {product.title} / {colour} / {size}
        </p>
        <p>
          Your selection is saved. Prices and availability are checked again at
          checkout.
        </p>
        <Link href="/bag" className="button button-primary">
          View bag
        </Link>
        <Button variant="secondary" onClick={() => setAdded(false)}>
          Continue shopping
        </Button>
      </Modal>
    </>
  );
}
