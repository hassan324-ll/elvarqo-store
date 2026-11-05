import { useId, type InputHTMLAttributes } from 'react';

type Kind =
  | 'text'
  | 'price'
  | 'size'
  | 'colour'
  | 'sku'
  | 'link'
  | 'image'
  | 'search'
  | 'measure';
function FieldIcon({ kind }: { kind: Kind }) {
  const paths: Record<Kind, string> = {
    text: 'M4 5h16M4 12h12M4 19h8',
    price: 'M18 5a5 5 0 0 0-9 3v5c0 3-2 5-4 6h14M5 12h11',
    size: 'M3 8V4h4M17 4h4v4M21 16v4h-4M7 20H3v-4M8 8h8v8H8Z',
    colour: 'M12 3C10 7 5 11 5 15a7 7 0 0 0 14 0c0-4-5-8-7-12Z',
    sku: 'M3 5v14M6 5v14M10 5v14M12 5v14M17 5v14M21 5v14',
    link: 'm10 13 4-4M8 15l-2 2a3 3 0 0 1-4-4l5-5a3 3 0 0 1 4 0M13 16a3 3 0 0 0 4 0l5-5a3 3 0 0 0-4-4l-2 2',
    image: 'M3 3h18v18H3ZM3 17l5-5 4 4 3-3 6 6M16 7h.01',
    search: 'm21 21-5-5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0',
    measure: 'm3 16 13-13 5 5L8 21ZM12 7l2 2M8 11l2 2M4 15l2 2',
  };
  return (
    <svg
      className="product-field-symbol"
      aria-hidden="true"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.6"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <path d={paths[kind]} />
    </svg>
  );
}
export function ProductField({
  label,
  hint,
  icon,
  error,
  id: supplied,
  ...props
}: InputHTMLAttributes<HTMLInputElement> & {
  label: string;
  hint?: string;
  icon?: Kind;
  error?: string;
}) {
  const generated = useId();
  const id = supplied || generated;
  const lower = label.toLowerCase();
  const kind =
    icon ||
    (lower.includes('price')
      ? 'price'
      : lower.includes('size')
        ? 'size'
        : lower.includes('colour')
          ? 'colour'
          : lower.includes('sku') || lower.includes('barcode')
            ? 'sku'
            : lower.includes('slug')
              ? 'link'
              : lower.includes('photo') || lower.includes('image')
                ? 'image'
                : lower.includes('find')
                  ? 'search'
                  : lower.includes(' cm') || lower.includes('grams')
                    ? 'measure'
                    : 'text');
  return (
    <div className="field product-field">
      <label htmlFor={id}>
        <FieldIcon kind={kind} />
        {label}
      </label>
      <input
        {...props}
        id={id}
        aria-invalid={!!error}
        aria-describedby={
          error ? `${id}-error` : hint ? `${id}-hint` : undefined
        }
      />
      {hint && <small id={`${id}-hint`}>{hint}</small>}
      {error && (
        <p className="field-error" id={`${id}-error`} role="alert">
          {error}
        </p>
      )}
    </div>
  );
}
