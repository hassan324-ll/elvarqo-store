'use client';
import {
  useEffect,
  useId,
  useRef,
  useState,
  type ReactNode,
  type ButtonHTMLAttributes,
  type InputHTMLAttributes,
  type SelectHTMLAttributes,
} from 'react';

export function Icon({
  name,
  size = 20,
}: {
  name:
    | 'menu'
    | 'search'
    | 'user'
    | 'bag'
    | 'close'
    | 'arrow'
    | 'plus'
    | 'grid'
    | 'box'
    | 'chart'
    | 'settings'
    | 'check'
    | 'edit'
    | 'trash'
    | 'eye';
  size?: number;
}) {
  const paths = {
    menu: 'M3 6h18M3 12h18M3 18h18',
    search: 'm21 21-5-5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0',
    user: 'M20 21v-2a8 8 0 0 0-16 0v2M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
    bag: 'M4 7h16l1 14H3L4 7ZM8 7V5a4 4 0 0 1 8 0v2',
    close: 'm6 6 12 12M6 18 18 6',
    arrow: 'M4 12h16m-6-6 6 6-6 6',
    plus: 'M12 4v16M4 12h16',
    grid: 'M3 3h7v7H3ZM14 3h7v7h-7ZM3 14h7v7H3ZM14 14h7v7h-7Z',
    box: 'm3 7 9-5 9 5v10l-9 5-9-5V7Zm0 0 9 5 9-5M12 12v10',
    chart: 'M4 21V10M12 21V3M20 21v-7',
    settings: 'M4 7h16M4 17h16M8 3v8M16 13v8',
    check: 'm5 12 4 4L19 6',
    edit: 'm15 5 4 4M4 20l4-1L21 6l-4-4L4 15l-1 6Z',
    trash: 'M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7',
    eye: 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Zm13 0a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
  };
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.5"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d={paths[name]} />
    </svg>
  );
}
export function Button({
  variant = 'primary',
  busy = false,
  children,
  className = '',
  disabled,
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: 'primary' | 'secondary' | 'quiet';
  busy?: boolean;
}) {
  return (
    <button
      {...props}
      disabled={disabled || busy}
      aria-busy={busy || undefined}
      className={`button button-${variant} ${className}`}
    >
      {busy ? 'Please wait...' : children}
    </button>
  );
}
export function Badge({
  children,
  tone = 'neutral',
}: {
  children: ReactNode;
  tone?: 'neutral' | 'success' | 'warning';
}) {
  return <span className={`badge badge-${tone}`}>{children}</span>;
}
export function Field({
  label,
  error,
  hint,
  id: supplied,
  ...props
}: InputHTMLAttributes<HTMLInputElement> & {
  label: string;
  error?: string;
  hint?: string;
}) {
  const generated = useId();
  const id = supplied || generated;
  return (
    <div className="field">
      <label htmlFor={id}>{label}</label>
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
export function Select({
  label,
  children,
  id: supplied,
  ...props
}: SelectHTMLAttributes<HTMLSelectElement> & { label: string }) {
  const generated = useId();
  const id = supplied || generated;
  return (
    <div className="field">
      <label htmlFor={id}>{label}</label>
      <select id={id} {...props}>
        {children}
      </select>
    </div>
  );
}
export function Modal({
  open,
  title,
  onClose,
  children,
  drawer = false,
}: {
  open: boolean;
  title: string;
  onClose: () => void;
  children: ReactNode;
  drawer?: boolean;
}) {
  const ref = useRef<HTMLDialogElement>(null);
  const id = useId();
  useEffect(() => {
    const dialog = ref.current;
    if (!open || !dialog) return;
    const trigger = document.activeElement as HTMLElement | null;
    const overflow = document.body.style.overflow;
    dialog.showModal();
    document.body.style.overflow = 'hidden';
    return () => {
      dialog.close();
      document.body.style.overflow = overflow;
      if (trigger?.isConnected) trigger.focus();
    };
  }, [open]);
  return (
    <dialog
      ref={ref}
      aria-labelledby={id}
      className={`modal ${drawer ? 'drawer' : ''}`}
      onKeyDown={(e) => {
        if (e.key === 'Escape') {
          e.preventDefault();
          e.stopPropagation();
          onClose();
          return;
        }
        if (e.key !== 'Tab') return;
        const controls = Array.from(
          e.currentTarget.querySelectorAll<HTMLElement>(
            'button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex="0"]',
          ),
        ).filter((el) => el.getClientRects().length > 0);
        const first = controls[0];
        const last = controls[controls.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last?.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first?.focus();
        }
      }}
      onCancel={(e) => {
        e.preventDefault();
        onClose();
      }}
      onClick={(e) => {
        const b = e.currentTarget.getBoundingClientRect();
        if (
          e.target === e.currentTarget &&
          (e.clientX < b.left ||
            e.clientX > b.right ||
            e.clientY < b.top ||
            e.clientY > b.bottom)
        )
          onClose();
      }}
    >
      <div className="modal-heading">
        <h2 id={id}>{title}</h2>
        <button
          type="button"
          className="icon-button"
          aria-label={`Close ${title}`}
          onClick={onClose}
          autoFocus
        >
          <Icon name="close" />
        </button>
      </div>
      <div className="modal-body">{children}</div>
    </dialog>
  );
}
export type ProductPreview = {
  id: string;
  name: string;
  category: string;
  colour: string;
  detail: string;
  priceMinor: number;
  currency: 'GBP';
  image: string;
  alt: string;
};
export function ProductCard({
  product,
  onPreview,
}: {
  product: ProductPreview;
  onPreview: () => void;
}) {
  return (
    <article className="product-card">
      <div className="product-image">
        <img
          src={product.image}
          alt={product.alt}
          width="1024"
          height="1536"
          loading="lazy"
        />
        <button
          className="quick-add icon-button"
          aria-label={`Preview ${product.name}`}
          onClick={onPreview}
        >
          <Icon name="plus" />
        </button>
        <span className="image-note">Concept image</span>
      </div>
      <div className="product-title">
        <h3>{product.name}</h3>
        <span>
          {new Intl.NumberFormat('en-GB', {
            style: 'currency',
            currency: product.currency,
            maximumFractionDigits: 0,
          }).format(product.priceMinor / 100)}
        </span>
      </div>
      <p>
        {product.colour} / {product.detail}
      </p>
      <span
        className={`colour-dot ${product.id}`}
        aria-label={product.colour}
        role="img"
      />
    </article>
  );
}
export function ProductGrid({ products }: { products: ProductPreview[] }) {
  const [selected, setSelected] = useState<ProductPreview | null>(null);
  return (
    <>
      <div className="product-grid">
        {products.map((product) => (
          <ProductCard
            key={product.id}
            product={product}
            onPreview={() => setSelected(product)}
          />
        ))}
      </div>
      <Modal
        open={!!selected}
        title="Product preview"
        onClose={() => setSelected(null)}
      >
        {selected && (
          <div className="product-preview">
            <img
              src={selected.image}
              alt={selected.alt}
              width="320"
              height="480"
            />
            <div>
              <Badge tone="warning">Design concept</Badge>
              <h3>{selected.name}</h3>
              <p>
                {selected.colour} / {selected.detail}
              </p>
              <p>
                Illustrative price: £{selected.priceMinor / 100}. Product
                information and availability are unverified.
              </p>
              <p>Shopping functionality follows in Phase 5.</p>
              <Button disabled>Add to bag unavailable</Button>
            </div>
          </div>
        )}
      </Modal>
    </>
  );
}
export function EmptyState({
  title,
  children,
}: {
  title: string;
  children: ReactNode;
}) {
  return (
    <div className="empty-state">
      <Icon name="bag" size={32} />
      <h3>{title}</h3>
      <p>{children}</p>
    </div>
  );
}
export function Skeleton({ label = 'Loading content' }: { label?: string }) {
  return (
    <div className="skeleton" role="status">
      <span className="sr-only">{label}</span>
      <span />
      <span />
      <span />
    </div>
  );
}
export function DataTable({
  caption,
  columns,
  rows,
}: {
  caption: string;
  columns: string[];
  rows: ReactNode[][];
}) {
  return (
    <table className="data-table">
      <caption>{caption}</caption>
      <thead>
        <tr>
          {columns.map((c) => (
            <th key={c} scope="col">
              {c}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {rows.map((row, i) => (
          <tr key={i}>
            {row.map((cell, j) => (
              <td key={j} data-label={columns[j]}>
                {cell}
              </td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  );
}
export function Pagination({
  page,
  total,
  onChange,
}: {
  page: number;
  total: number;
  onChange: (p: number) => void;
}) {
  return (
    <nav className="pagination" aria-label="Pagination">
      <Button
        variant="secondary"
        disabled={page <= 1}
        onClick={() => onChange(page - 1)}
      >
        Previous
      </Button>
      <span aria-live="polite">
        Page {page} of {total}
      </span>
      <Button
        variant="secondary"
        disabled={page >= total}
        onClick={() => onChange(page + 1)}
      >
        Next
      </Button>
    </nav>
  );
}
export function ComponentGallery() {
  const [dialog, setDialog] = useState(false);
  const [email, setEmail] = useState('');
  const [error, setError] = useState('');
  const [sent, setSent] = useState(false);
  const [page, setPage] = useState(1);
  return (
    <div className="component-gallery">
      <header>
        <p className="eyebrow">ELVARQO / UI REFERENCE</p>
        <h1>Considered, consistent controls.</h1>
        <p>
          Shared components and states. This form is a local demonstration; no
          information is sent or saved.
        </p>
      </header>
      <section className="panel">
        <h2>Actions & feedback</h2>
        <div className="button-row">
          <Button onClick={() => setDialog(true)}>Open example dialog</Button>
          <Button variant="secondary">Secondary action</Button>
          <Button disabled>Unavailable</Button>
          <Button busy>Loading</Button>
        </div>
        <div className="button-row">
          <Badge tone="success">Available</Badge>
          <Badge tone="warning">Action required</Badge>
          <Badge>Concept</Badge>
        </div>
      </section>
      <section className="panel">
        <h2>Forms & validation</h2>
        <form
          noValidate
          onSubmit={(e) => {
            e.preventDefault();
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
              setError('Enter a valid email address.');
              setSent(false);
            } else {
              setError('');
              setSent(true);
            }
          }}
        >
          <Field
            label="Email address"
            type="email"
            autoComplete="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            error={error}
            hint="Demonstration only"
          />
          <Select label="Preferred size" defaultValue="">
            <option value="" disabled>
              Choose a size
            </option>
            {['S', 'M', 'L', 'XL'].map((s) => (
              <option key={s}>{s}</option>
            ))}
          </Select>
          <Button type="submit">Validate example</Button>
          {sent && (
            <p role="status">Example validated. Nothing was submitted.</p>
          )}
        </form>
      </section>
      <section className="panel">
        <h2>Loading & empty states</h2>
        <Skeleton />
        <EmptyState title="Nothing here yet">
          A useful next step belongs beside every empty state.
        </EmptyState>
        <p className="field-error" role="status">
          Example connection error. Your input is preserved; retry is available.
        </p>
      </section>
      <section className="panel">
        <h2>Table & pagination</h2>
        <DataTable
          caption="Illustrative component records"
          columns={['Reference', 'Status', 'Description']}
          rows={[
            [
              `DEMO-00${page}`,
              <Badge key="status">Draft</Badge>,
              'Local fixture only',
            ],
          ]}
        />
        <Pagination page={page} total={3} onChange={setPage} />
      </section>
      <Modal
        open={dialog}
        title="Example dialog"
        onClose={() => setDialog(false)}
      >
        <p>
          Tab stays inside this dialog. Escape closes it and returns focus to
          the button.
        </p>
        <Field label="Example name" autoComplete="off" />
        <Button onClick={() => setDialog(false)}>Done</Button>
      </Modal>
    </div>
  );
}
