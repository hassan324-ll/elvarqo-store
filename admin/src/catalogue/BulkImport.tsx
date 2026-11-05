import { useEffect, useState } from 'react';
import { Button, Field } from '../ui/components';
import { Feedback, request, useAction } from './common';
import './bulk-import.css';

type Match = {
  file: string;
  alt: string;
  matched: boolean;
  width?: number;
  height?: number;
};
type ImportItem = {
  id: number;
  position: number;
  status: 'ready' | 'invalid' | 'duplicate' | 'imported' | 'failed';
  product_id: number | null;
  attempts: number;
  errors: Record<string, string[]>;
  preview: {
    title: string;
    slug?: string;
    supplier?: { name: string; product_id: string; url: string };
    groups: { id: number; title: string; kind: string; active: boolean }[];
    images: Match[];
    warnings: string[];
    variant_count?: number;
    pending_variant_count?: number;
  };
};
type Batch = {
  id: string;
  expires_at: string;
  expired: boolean;
  items: ImportItem[];
  summary: Record<string, number>;
};
const api = (
  import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1'
).replace(/\/$/, '');

function download(name: string, value: unknown) {
  const url = URL.createObjectURL(
    new Blob([JSON.stringify(value, null, 2)], { type: 'application/json' }),
  );
  const link = document.createElement('a');
  link.href = url;
  link.download = name;
  link.click();
  URL.revokeObjectURL(url);
}

export default function BulkImport({ onBack }: { onBack: () => void }) {
  const [json, setJson] = useState<File | null>(null);
  const [zip, setZip] = useState<File | null>(null);
  const [batch, setBatch] = useState<Batch | null>(null);
  const [confirmed, setConfirmed] = useState(false);
  const [processing, setProcessing] = useState(false);
  const [history, setHistory] = useState<{ id: string; created_at: string }[]>(
    [],
  );
  const action = useAction();
  const { run } = action;
  useEffect(() => {
    void run(async () => {
      const result = await request<{
        items: { id: string; created_at: string }[];
      }>('imports');
      setHistory(result.items);
    });
  }, [run]);
  const eligible =
    batch?.items.filter((item) => ['ready', 'failed'].includes(item.status)) ||
    [];
  async function preview() {
    await run(async () => {
      if (!json) throw new Error('Choose a product JSON file.');
      const form = new FormData();
      form.set('products', json);
      if (zip) form.set('images', zip);
      const result = await request<{ batch: Batch }>('imports/preview', form);
      setBatch(result.batch);
      setConfirmed(false);
      setHistory((previous) =>
        [
          { id: result.batch.id, created_at: new Date().toISOString() },
          ...previous.filter((item) => item.id !== result.batch.id),
        ].slice(0, 25),
      );
    });
  }
  async function load(id: string) {
    await run(async () => {
      const result = await request<{ batch: Batch }>('imports/' + id);
      setBatch(result.batch);
      setConfirmed(false);
    });
  }
  async function importItems(items: ImportItem[]) {
    if (!batch || !confirmed || processing) return;
    setProcessing(true);
    await run(async () => {
      for (const item of items) {
        const body = new FormData();
        body.set('confirm_matches', '1');
        const result = await request<{ batch: Batch }>(
          `imports/${batch.id}/items/${item.id}`,
          body,
        );
        setBatch(result.batch);
      }
    });
    setProcessing(false);
  }
  return (
    <div className="bulk-import">
      <header className="admin-page-heading">
        <div>
          <p className="eyebrow">ELVARQO / CATALOGUE</p>
          <h1>Bulk Import</h1>
          <p className="muted">
            Review your catalogue data and image matches, then create private
            drafts.
          </p>
        </div>
        <Button
          variant="secondary"
          onClick={onBack}
          disabled={processing || action.busy}
        >
          Back to catalogue
        </Button>
      </header>
      <Feedback action={action} />
      <section className="catalogue-panel bulk-upload">
        <h2>1. Choose your files</h2>
        <p>
          Up to 50 products and an optional image ZIP. Supplier IDs prevent
          repeat imports; existing products are never overwritten.
        </p>
        <div className="bulk-file-grid">
          <Field
            label="Product JSON"
            type="file"
            accept=".json,application/json"
            disabled={processing || action.busy}
            onChange={(event) => {
              setJson(event.target.files?.[0] || null);
              setBatch(null);
              setConfirmed(false);
            }}
          />
          <Field
            label="Image ZIP (optional)"
            type="file"
            accept=".zip,application/zip"
            disabled={processing || action.busy}
            onChange={(event) => {
              setZip(event.target.files?.[0] || null);
              setBatch(null);
              setConfirmed(false);
            }}
          />
        </div>
        <p className="muted">
          JPEG, PNG or WebP only; 8 MiB per image, 32 MiB ZIP, 128 MiB expanded.
          Convert AVIF copies to WebP first and keep original source files.
        </p>
        <div className="bulk-actions">
          <Button
            onClick={() => void preview()}
            disabled={!json || processing || action.busy}
          >
            Preview import
          </Button>
          <Button
            variant="secondary"
            disabled={action.busy || processing}
            onClick={() =>
              void run(async () =>
                download(
                  'elvarqo-products.example.json',
                  await request('imports/template'),
                ),
              )
            }
          >
            Download example template
          </Button>
        </div>
      </section>
      {history.length > 0 && (
        <section className="catalogue-panel">
          <h2>Resume a recent import</h2>
          <label htmlFor="import-history">Your preview batches</label>
          <select
            id="import-history"
            value={batch?.id || ''}
            disabled={processing || action.busy}
            onChange={(event) => {
              if (event.target.value) void load(event.target.value);
            }}
          >
            <option value="">Select an import</option>
            {history.map((item) => (
              <option key={item.id} value={item.id}>
                {new Date(item.created_at).toLocaleString('en-GB')} ·{' '}
                {item.id.slice(0, 8)}
              </option>
            ))}
          </select>
        </section>
      )}
      {batch && (
        <>
          <section className="catalogue-panel">
            <h2>2. Review product and image matches</h2>
            <p>
              Batch {batch.id.slice(0, 8)} · Preview expires{' '}
              {new Date(batch.expires_at).toLocaleString('en-GB')}. Supplier
              information stays private to catalogue managers.
            </p>
            <p role="status">
              {Object.entries(batch.summary)
                .map(([status, count]) => `${count} ${status}`)
                .join(' · ')}
            </p>
            {batch.expired && (
              <p role="alert">
                This preview expired. Upload a fresh preview; already imported
                supplier IDs remain protected.
              </p>
            )}
            <div className="bulk-actions">
              <Button
                variant="secondary"
                disabled={processing || action.busy}
                onClick={() => void load(batch.id)}
              >
                Refresh report
              </Button>
              <Button
                variant="secondary"
                onClick={() =>
                  download(`elvarqo-import-${batch.id}.json`, batch)
                }
              >
                Download report
              </Button>
            </div>
          </section>
          {batch.items.map((item) => (
            <article className="catalogue-panel bulk-item" key={item.id}>
              <div className="bulk-item-heading">
                <h3>
                  {item.position}. {item.preview.title}
                </h3>
                <span className={`bulk-status bulk-status-${item.status}`}>
                  {item.status}
                </span>
              </div>
              {item.preview.supplier && (
                <p>
                  AliExpress ID: {item.preview.supplier.product_id} ·{' '}
                  <a
                    href={item.preview.supplier.url}
                    target="_blank"
                    rel="noreferrer"
                  >
                    Supplier listing
                  </a>
                </p>
              )}
              <p>
                Groups:{' '}
                {item.preview.groups
                  .map(
                    (group) =>
                      `${group.title} (${group.kind}, ID ${group.id}${group.active ? '' : ', hidden'})`,
                  )
                  .join(' → ') || 'None resolved'}
              </p>
              {item.preview.variant_count !== undefined && (
                <p>
                  {item.preview.variant_count} complete local variants ·{' '}
                  {item.preview.pending_variant_count} pending supplier variants
                </p>
              )}
              <div className="bulk-images">
                {item.preview.images.map((image, index) => (
                  <figure key={image.file}>
                    {image.matched && item.preview.slug && (
                      <img
                        src={`${api}/staff/catalogue/imports/${batch.id}/items/${item.id}/images/${index}`}
                        alt={image.alt}
                        loading="lazy"
                      />
                    )}
                    <figcaption>
                      {image.file}
                      <br />
                      <strong>
                        {image.matched ? 'File matched' : 'Missing image'}
                      </strong>
                      <br />
                      {image.alt}
                    </figcaption>
                  </figure>
                ))}
              </div>
              {!item.preview.images.length && (
                <p className="muted">No images supplied.</p>
              )}
              {item.preview.warnings.length > 0 && (
                <details>
                  <summary>Review before publication</summary>
                  <ul>
                    {item.preview.warnings.map((warning) => (
                      <li key={warning}>{warning}</li>
                    ))}
                  </ul>
                </details>
              )}
              {Object.keys(item.errors).length > 0 && (
                <ul role="alert" className="bulk-errors">
                  {Object.entries(item.errors).map(([field, messages]) => (
                    <li key={field}>
                      <strong>{field}:</strong> {messages.join(' ')}
                    </li>
                  ))}
                </ul>
              )}
              {item.product_id && (
                <p>
                  Created draft product ID {item.product_id}.{' '}
                  <a href={`?view=products&product=${item.product_id}`}>
                    Open product
                  </a>
                </p>
              )}
              {item.status === 'failed' && (
                <Button
                  variant="secondary"
                  disabled={
                    !confirmed || batch.expired || processing || action.busy
                  }
                  onClick={() => void importItems([item])}
                >
                  Retry this product
                </Button>
              )}
            </article>
          ))}
          <section className="catalogue-panel">
            <h2>3. Create drafts</h2>
            <label className="bulk-confirm">
              <input
                type="checkbox"
                checked={confirmed}
                disabled={processing}
                onChange={(event) => setConfirmed(event.target.checked)}
              />
              I have checked the collection/category assignments and each
              provisional supplier-to-image match.
            </label>
            <p>
              Valid rows import independently. Failed rows roll back completely
              and can be retried. Refresh or reopen this batch after a lost
              connection; completed rows are safe to repeat. Invalid and
              duplicate rows are skipped.
            </p>
            <Button
              disabled={
                !confirmed ||
                !eligible.length ||
                batch.expired ||
                processing ||
                action.busy
              }
              onClick={() => void importItems(eligible)}
            >
              {processing
                ? 'Creating drafts…'
                : `Import ${eligible.length} eligible products as drafts`}
            </Button>
            <p className="muted">
              Catalogue import only. This does not place or fulfil AliExpress or
              DSers orders.
            </p>
          </section>
        </>
      )}
    </div>
  );
}
