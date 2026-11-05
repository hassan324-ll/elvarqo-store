import SupplierDetails from './SupplierDetails';
import QuickVariants from './QuickVariants';
import {
  useEffect,
  useState,
  useCallback,
  useRef,
  useImperativeHandle,
  type Ref,
} from 'react';
import { ProductField as Field } from './ProductField';
import { sizeOptions } from './size-options';
import { isUnusedVariant, priceFields, variantPrices } from './variant-helper';
import {
  Badge,
  Button,
  DataTable,
  Icon,
  Modal,
  Select,
} from '../ui/components';
import { Feedback, request, TextArea, useAction } from './common';
import {
  type Collection,
  type Product,
  type ProductMedia,
  type Variant,
  money,
} from './types';
type PendingImage = { id: string; file: File; preview: string; alt: string };
type EditVariant = Variant & { price: string; sale: string };
const blank = (): EditVariant => ({
  sku: '',
  barcode: null,
  colour: '',
  size: '',
  price_minor: 0,
  compare_at_minor: null,
  price: '',
  sale: '',
  currency: 'GBP',
  weight_grams: null,
  low_stock_threshold: 5,
  active: true,
  measurements: null,
  fit: null,
  care: null,
});
const newProduct = (): Product => ({
  id: 0,
  title: '',
  slug: '',
  product_type: '',
  description: '',
  material: '',
  fit: '',
  care: '',
  seo_title: '',
  seo_description: '',
  measurements: [],
  version: 1,
  status: 'draft',
  collections: [],
  variants: [],
  media: [],
});
export type ProductEditorHandle = { requestClose: () => void };
export default function ProductEditor({
  id,
  canManage,
  onBack,
  initialCollectionId,
  closeRef,
}: {
  id: number | 'new';
  canManage: boolean;
  onBack: () => void;
  initialCollectionId?: number | null;
  closeRef?: Ref<ProductEditorHandle>;
}) {
  const [product, setProduct] = useState<Product>(newProduct);
  const editorElement = useRef<HTMLDivElement>(null);
  const [saved, setSaved] = useState<Product | null>(null);
  const [variants, setVariants] = useState<EditVariant[]>([]);
  const [collectionIds, setCollectionIds] = useState<number[]>(
    initialCollectionId ? [initialCollectionId] : [],
  );
  const [collections, setCollections] = useState<Collection[]>([]);
  const [categorySearch, setCategorySearch] = useState('');
  const [dirty, setDirty] = useState(false);
  const [preview, setPreview] = useState(false);
  const [confirm, setConfirm] = useState<
    '' | 'publish' | 'unpublish' | 'archive'
  >('');
  const [ready, setReady] = useState(id === 'new');
  const action = useAction();
  const { run } = action;
  const mediaAction = useAction();
  const [pendingImages, setPendingImages] = useState<PendingImage[]>([]);
  const [fileInputKey, setFileInputKey] = useState(0);
  const [imageProgress, setImageProgress] = useState('');
  const [slugTouched, setSlugTouched] = useState(false);
  const imageUrls = useRef(new Set<string>());
  useEffect(() => {
    const urls = imageUrls.current;
    return () => {
      for (const url of urls) URL.revokeObjectURL(url);
      urls.clear();
    };
  }, []);
  function removePending(image: PendingImage) {
    URL.revokeObjectURL(image.preview);
    imageUrls.current.delete(image.preview);
    setPendingImages((images) => images.filter((i) => i.id !== image.id));
  }
  function chooseImages(files: FileList | null) {
    if (!files?.length) return;
    const selected = Array.from(files);
    if (selected.length + pendingImages.length + product.media.length > 30) {
      action.setError(
        'Choose up to 30 images in total, including saved images.',
      );
      return;
    }
    if (
      selected.some(
        (file) =>
          !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
          file.size > 8 * 1024 * 1024,
      )
    ) {
      action.setError(
        'Choose JPEG, PNG or WebP images, each no larger than 8MB.',
      );
      return;
    }
    const next = selected.map((file) => {
      const preview = URL.createObjectURL(file);
      imageUrls.current.add(preview);
      return {
        id: crypto.randomUUID(),
        file,
        preview,
        alt:
          product.title.trim().slice(0, 240) ||
          file.name
            .replace(/\.[^.]+$/, '')
            .replace(/[-_]/g, ' ')
            .slice(0, 240),
      };
    });
    action.setError('');
    setPendingImages((images) => [...images, ...next]);
    setFileInputKey((n) => n + 1);
  }
  const [mediaEdits, setMediaEdits] = useState<ProductMedia[]>([]);
  const accept = useCallback((p: Product) => {
    setProduct(p);
    setSaved(p);
    setVariants(
      p.variants.map((v) => ({
        ...v,
        ...priceFields(v),
      })),
    );
    setCollectionIds(p.collections.map((c) => c.id));
    setMediaEdits(p.media);
    setDirty(false);
    setReady(true);
    const u = new URL(location.href);
    u.searchParams.set('product', String(p.id));
    history.replaceState(null, '', u);
  }, []);
  useEffect(() => {
    void run(async () => {
      const c = await request<{ items: Collection[] }>('collections');
      setCollections(c.items);
      if (id !== 'new')
        accept((await request<{ product: Product }>('products/' + id)).product);
    });
  }, [id, run, accept]);
  useEffect(() => {
    const handler = (e: BeforeUnloadEvent) => {
      if (dirty || pendingImages.length) {
        e.preventDefault();
      }
    };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [dirty, pendingImages.length]);
  const editable = canManage && product.status === 'draft';
  function field(key: keyof Product, value: unknown) {
    if (key === 'slug') setSlugTouched(true);
    setProduct((p) => ({
      ...p,
      [key]: value,
      ...(key === 'title' && !saved && !slugTouched
        ? {
            slug: String(value)
              .toLowerCase()
              .normalize('NFKD')
              .replace(/[\u0300-\u036f]/g, '')
              .replace(/[^a-z0-9]+/g, '-')
              .replace(/^-|-$/g, '')
              .slice(0, 180),
          }
        : {}),
    }));
    setDirty(true);
  }
  function variant(i: number, key: keyof EditVariant, value: unknown) {
    setVariants((v) =>
      v.map((row, n) => (n === i ? { ...row, [key]: value } : row)),
    );
    setDirty(true);
  }
  function closeEditor() {
    if (action.busy || mediaAction.busy) return;
    const mediaDirty =
      saved && JSON.stringify(mediaEdits) !== JSON.stringify(saved.media);
    if (
      (!dirty && !pendingImages.length && !mediaDirty) ||
      window.confirm('Discard unsaved product changes and selected images?')
    )
      onBack();
  }
  useImperativeHandle(closeRef, () => ({ requestClose: closeEditor }));
  async function save(addAnother = false) {
    await action.run(async () => {
      if (pendingImages.some((i) => !i.alt.trim()))
        throw Error('Add a description for each selected image.');
      const usedVariants = variants.filter((v) => !isUnusedVariant(v));
      for (const v of usedVariants) {
        const missing = [
          !v.sku.trim() && 'SKU',
          !v.colour.trim() && 'colour',
          !v.size.trim() && 'size',
        ].filter(Boolean);
        if (missing.length) {
          const index = variants.indexOf(v);
          const row = editorElement.current?.querySelector<HTMLDetailsElement>(
            `[data-variant-index="${index}"]`,
          );
          if (row) {
            row.open = true;
            row.scrollIntoView({ block: 'center', behavior: 'smooth' });
            row.querySelector('input')?.focus({ preventScroll: true });
          }
          throw Error(
            `Variant ${index + 1} is missing ${missing.join(', ')}. Complete it or remove that row. In Quick variant builder, click Generate variants before saving; selecting options alone does not create variants.`,
          );
        }
      }
      const body = {
        title: product.title,
        slug: product.slug,
        product_type: product.product_type,
        description: product.description,
        material: product.material,
        fit: product.fit,
        care: product.care,
        seo_title: product.seo_title || product.title.slice(0, 70),
        seo_description:
          product.seo_description ||
          (product.description || product.title)
            .replace(/\s+/g, ' ')
            .trim()
            .slice(0, 160),
        measurements: product.measurements,
        version: product.version,
        collection_ids: collectionIds,
        variants: usedVariants.map((v) => ({
          ...(v.id ? { id: v.id } : {}),
          sku: v.sku,
          barcode: v.barcode || null,
          colour: v.colour,
          size: v.size,
          ...variantPrices(v.price, v.sale),
          currency: 'GBP',
          weight_grams: v.weight_grams,
          low_stock_threshold: v.low_stock_threshold,
          active: v.active,
          measurements: v.measurements,
          fit: v.fit,
          care: v.care,
        })),
      };
      const result = await request<{ product: Product }>(
        product.id ? 'products/' + product.id : 'products',
        body,
      );
      let current = result.product;
      accept(current);
      for (let n = 0; n < pendingImages.length; n++) {
        const image = pendingImages[n];
        setImageProgress(`Saving image ${n + 1} of ${pendingImages.length}…`);
        const body = new FormData();
        body.set('version', String(current.version));
        body.set('alt', image.alt.trim());
        body.set('file', image.file);
        try {
          current = (
            await request<{ product: Product }>(
              `products/${current.id}/media`,
              body,
            )
          ).product;
          accept(current);
          removePending(image);
        } catch (error) {
          setImageProgress('');
          throw Error(
            `Product draft saved. ${image.file.name} was not confirmed uploaded. Remaining images are kept here. ${error instanceof Error ? error.message : 'Try saving again.'}`,
          );
        }
      }
      setImageProgress('');
      if (addAnother) {
        setProduct(newProduct());
        setSaved(null);
        setVariants([]);
        setMediaEdits([]);
        setPendingImages([]);
        setDirty(false);
        setSlugTouched(false);
        const url = new URL(location.href);
        url.searchParams.set('product', 'new');
        history.replaceState(null, '', url);
        action.setNotice(
          'Product and images saved. Ready for the next product; your collection selection is kept.',
        );
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } else {
        action.setNotice(
          pendingImages.length
            ? 'Product and images saved. Preview your product before publishing.'
            : 'Draft saved. Preview the saved version before publishing.',
        );
      }
    });
  }
  async function transition() {
    const task = confirm;
    setConfirm('');
    await action.run(async () => {
      const result = await request<{ product?: Product }>(
        'products/' + product.id + '/' + task,
        {
          version: product.version,
          ...(task === 'archive' ? { confirmation: 'ARCHIVE' } : {}),
        },
      );
      if (result.product) accept(result.product);
      if (task === 'archive') onBack();
      else
        action.setNotice(
          task === 'publish'
            ? 'Product published. Public catalogue access is now enabled.'
            : 'Product unpublished. It and its images are now private.',
        );
    });
  }
  function moveMedia(index: number, direction: number) {
    setMediaEdits((items) => {
      const next = [...items];
      [next[index], next[index + direction]] = [
        next[index + direction],
        next[index],
      ];
      return next;
    });
  }
  if (!ready)
    return (
      <section className="panel">
        <h1>Load product</h1>
        <Feedback action={action} />
        {canManage && product.id > 0 && (
          <SupplierDetails productId={product.id} />
        )}
        <Button busy={action.busy} onClick={() => location.reload()}>
          Retry loading
        </Button>
        <Button variant="quiet" onClick={onBack}>
          Back to products
        </Button>
      </section>
    );
  return (
    <div ref={editorElement} className="catalogue-workspace product-editor">
      <header className="admin-page-heading">
        <div>
          <p className="eyebrow">
            CATALOGUE / {product.id ? 'PRODUCT ' + product.id : 'NEW PRODUCT'}
          </p>
          <h1>
            {product.id ? product.title || 'Edit product' : 'Create product'}
          </h1>
          <p className="muted">
            Add product details and photos together, then save. Publish when
            ready.
          </p>
        </div>
        <Badge tone={product.status === 'published' ? 'success' : 'neutral'}>
          {product.status}
          {dirty ? ' / unsaved' : ''}
        </Badge>
      </header>
      <div className="catalogue-actions product-editor-toolbar">
        <Button
          variant="secondary"
          disabled={action.busy || mediaAction.busy}
          onClick={closeEditor}
        >
          Back to products
        </Button>
        {editable && (
          <>
            <Button busy={action.busy} onClick={() => void save()}>
              Save draft
            </Button>
            <Button
              variant="secondary"
              disabled={action.busy || mediaAction.busy}
              onClick={() => void save(true)}
            >
              Save &amp; add another
            </Button>
          </>
        )}
        <Button
          variant="secondary"
          disabled={!saved || dirty || pendingImages.length > 0 || action.busy}
          onClick={() => setPreview(true)}
        >
          Preview saved product
        </Button>
        {canManage && saved && (
          <>
            <Button
              disabled={
                dirty ||
                pendingImages.length > 0 ||
                action.busy ||
                mediaAction.busy
              }
              onClick={() =>
                setConfirm(
                  product.status === 'published' ? 'unpublish' : 'publish',
                )
              }
            >
              {product.status === 'published'
                ? 'Unpublish product'
                : 'Publish product'}
            </Button>
            <Button
              variant="quiet"
              disabled={
                dirty ||
                pendingImages.length > 0 ||
                action.busy ||
                mediaAction.busy
              }
              onClick={() => setConfirm('archive')}
            >
              Archive product
            </Button>
          </>
        )}
      </div>
      <Feedback action={action} />
      {imageProgress && <p role="status">{imageProgress}</p>}
      {product.status === 'published' && (
        <p className="catalogue-notice">
          Published content is locked. Unpublish before editing; inventory
          adjustments remain available.
        </p>
      )}
      {!canManage && (
        <p className="catalogue-notice">
          Your role has read-only catalogue access.
        </p>
      )}
      <fieldset
        disabled={!editable || action.busy || mediaAction.busy}
        className="catalogue-fieldset"
      >
        <section className="panel">
          <h2>Product details</h2>
          <p className="section-description">
            Introduce your product with clear, accurate information.
          </p>
          <div className="catalogue-grid">
            <Field
              label="Product title"
              placeholder="e.g. Classic cotton T-shirt"
              maxLength={160}
              value={product.title}
              onChange={(e) => field('title', e.target.value)}
            />
            <Field
              label="URL slug"
              placeholder="classic-cotton-t-shirt"
              value={product.slug}
              onChange={(e) => field('slug', e.target.value)}
              hint="Lowercase words with hyphens. Old published URLs are retained as redirects."
            />
            <Field
              label="Product type"
              value={product.product_type || ''}
              onChange={(e) => field('product_type', e.target.value)}
            />
            <div className="field">
              <span className="field-label">Product categories</span>
              <Field
                label="Find a category"
                placeholder="Search collection or category…"
                value={categorySearch}
                onChange={(e) => setCategorySearch(e.target.value)}
              />
              <small>
                {collections
                  .filter((c) => collectionIds.includes(c.id))
                  .map((c) => c.title)
                  .join(' / ') || 'Choose a category below'}
              </small>
              <div className="product-category-options">
                {collections.some(
                  (c) =>
                    (c.kind === 'category' && c.parent_id) ||
                    collectionIds.includes(c.id),
                ) ? (
                  collections
                    .filter(
                      (c) =>
                        ((c.kind === 'category' && c.parent_id) ||
                          collectionIds.includes(c.id)) &&
                        `${collections.find((p) => p.id === c.parent_id)?.title || ''} ${c.title}`
                          .toLowerCase()
                          .includes(categorySearch.toLowerCase()),
                    )
                    .map((c) => (
                      <label className="catalogue-check" key={c.id}>
                        <input
                          type="checkbox"
                          checked={collectionIds.includes(c.id)}
                          onChange={(e) => {
                            setCollectionIds((ids) =>
                              e.target.checked
                                ? [...ids, c.id]
                                : ids.filter((id) => id !== c.id),
                            );
                            setDirty(true);
                          }}
                        />
                        {c.kind === 'category'
                          ? `${collections.find((p) => p.id === c.parent_id)?.title || 'Unassigned'} / ${c.title}`
                          : `${c.title} (existing collection assignment)`}
                        {c.active ? '' : ' (inactive)'}
                      </label>
                    ))
                ) : (
                  <p>Create a collection and add a category inside it first.</p>
                )}
              </div>
            </div>
          </div>
          <TextArea
            label="Product description"
            value={product.description || ''}
            onChange={(e) => field('description', e.target.value)}
          />
          <div className="catalogue-grid">
            <TextArea
              label="Material & construction (optional)"
              value={product.material || ''}
              onChange={(e) => field('material', e.target.value)}
            />
            <TextArea
              label="Fit information (optional)"
              value={product.fit || ''}
              onChange={(e) => field('fit', e.target.value)}
            />
            <TextArea
              label="Care instructions (optional)"
              value={product.care || ''}
              onChange={(e) => field('care', e.target.value)}
            />
          </div>
          <p className="muted">
            Use supplier-verified content. Descriptions are plain text; HTML is
            not rendered.
          </p>
        </section>
        <section className="panel product-image-picker">
          <div className="card-heading">
            <div>
              <h2>Product images</h2>
              <p className="section-description">
                Choose photos now. Save draft uploads them with your product.
              </p>
            </div>
            <Badge>
              {product.media.length + pendingImages.length} / 30 images
            </Badge>
          </div>
          <div className="image-picker-zone">
            <Icon name="plus" size={24} />
            <div>
              <strong>Add your product photos</strong>
              <p>
                Choose several at once. JPEG, PNG or WebP · up to 8MB each · at
                least 200px per side.
              </p>
            </div>
            <Field
              key={fileInputKey}
              label="Product images"
              type="file"
              multiple
              accept="image/jpeg,image/png,image/webp"
              onChange={(e) => chooseImages(e.target.files)}
            />
          </div>
          {pendingImages.length > 0 && (
            <>
              <p className="muted">
                Selected photos are shared across variants. You can link saved
                photos to specific variants below after saving. Edit each
                description to match the photo.
              </p>
              <div className="pending-image-grid">
                {pendingImages.map((image, i) => (
                  <article className="pending-image-card" key={image.id}>
                    <div className="pending-image-preview">
                      <img src={image.preview} alt={image.alt} />
                      <Badge>
                        {product.media.length + i === 0
                          ? 'Main image'
                          : 'Selected'}
                      </Badge>
                    </div>
                    <p className="pending-image-name" title={image.file.name}>
                      {image.file.name}
                    </p>
                    <Field
                      label={`Image description ${i + 1}`}
                      value={image.alt}
                      maxLength={240}
                      onChange={(e) =>
                        setPendingImages((images) =>
                          images.map((x) =>
                            x.id === image.id
                              ? { ...x, alt: e.target.value }
                              : x,
                          ),
                        )
                      }
                    />
                    <Button
                      variant="quiet"
                      onClick={() => removePending(image)}
                      aria-label={`Remove selected image ${i + 1}`}
                    >
                      Remove photo
                    </Button>
                  </article>
                ))}
              </div>
            </>
          )}
        </section>
        <section className="panel">
          <div className="card-heading">
            <h2>Variants, SKU & pricing</h2>
            <Button
              variant="secondary"
              onClick={() => {
                setVariants((v) => [...v, blank()]);
                setDirty(true);
              }}
            >
              Add variant
            </Button>
          </div>
          <p className="muted">
            Enter each colour/size combination once. Prices are GBP; stock is
            managed in Inventory.
          </p>
          <QuickVariants
            key={`${product.id}:${product.media.map((m) => m.id).join(',')}:${pendingImages.map((m) => m.id).join(',')}`}
            productId={product.id}
            existing={variants.filter((v) => !isUnusedVariant(v))}
            photos={[
              ...product.media.map((m) => ({
                id: m.id,
                url: m.url,
                alt: m.alt,
              })),
              ...pendingImages.map((m) => ({
                id: m.id,
                url: m.preview,
                alt: m.alt,
              })),
            ]}
            onGenerate={(rows) => {
              setVariants((current) => [
                ...current.filter((v) => !isUnusedVariant(v)),
                ...rows.map((row) => ({
                  ...blank(),
                  ...row,
                  ...priceFields(row),
                  low_stock_threshold: 0,
                })),
              ]);
              setDirty(true);
            }}
            onPrice={(price, sale) => {
              setVariants((current) =>
                current
                  .filter((v) => !isUnusedVariant(v))
                  .map((v) => ({
                    ...v,
                    price,
                    sale,
                  })),
              );
              setDirty(true);
            }}
          />
          {variants.map((v, i) => (
            <details
              key={v.id || `new-${i}`}
              className="variant-card"
              data-variant-index={i}
            >
              <summary>
                {v.colour && v.size
                  ? `${v.colour} / ${v.size}`
                  : `Variant ${i + 1}`}
                {v.price
                  ? ` · £${v.sale || v.price}${v.sale ? ' sale' : ''}`
                  : ' · Add price'}
                {v.sku ? ` · ${v.sku}` : ''}
              </summary>
              <div className="catalogue-grid">
                <Field
                  label={`Colour ${i + 1}`}
                  value={v.colour}
                  onChange={(e) => variant(i, 'colour', e.target.value)}
                />
                <Field
                  label={`Size ${i + 1}`}
                  list="catalogue-size-options"
                  value={v.size}
                  onChange={(e) => variant(i, 'size', e.target.value)}
                />
                <Field
                  label={`SKU ${i + 1}`}
                  value={v.sku}
                  onChange={(e) =>
                    variant(i, 'sku', e.target.value.toUpperCase())
                  }
                />
                <Field
                  label={`Barcode ${i + 1}`}
                  value={v.barcode || ''}
                  onChange={(e) => variant(i, 'barcode', e.target.value)}
                />
                <Field
                  label={`Regular price GBP ${i + 1}`}
                  type="number"
                  min="0"
                  max="1000000"
                  step="0.01"
                  inputMode="decimal"
                  hint="Use the arrows to change the price by £0.01."
                  value={v.price}
                  onChange={(e) => variant(i, 'price', e.target.value)}
                />
                <Field
                  label={`Sale price GBP ${i + 1} (optional)`}
                  type="number"
                  min="0.01"
                  max="1000000"
                  step="0.01"
                  inputMode="decimal"
                  hint="Lower than the regular price. Leave empty or clear it to remove the sale."
                  value={v.sale}
                  onChange={(e) => variant(i, 'sale', e.target.value)}
                />
                <Field
                  label={`Weight grams ${i + 1}`}
                  type="number"
                  min={1}
                  value={v.weight_grams ?? ''}
                  onChange={(e) =>
                    variant(
                      i,
                      'weight_grams',
                      e.target.value ? Number(e.target.value) : null,
                    )
                  }
                />
                <Field
                  label={`Low-stock threshold ${i + 1}`}
                  type="number"
                  min={0}
                  value={v.low_stock_threshold}
                  onChange={(e) =>
                    variant(i, 'low_stock_threshold', Number(e.target.value))
                  }
                />
              </div>
              <details className="variant-extras">
                <summary>Optional fit, care & measurements</summary>
                <div className="catalogue-grid">
                  {' '}
                  <TextArea
                    label={`Variant fit override ${i + 1}`}
                    value={v.fit || ''}
                    onChange={(e) => variant(i, 'fit', e.target.value || null)}
                  />
                  <TextArea
                    label={`Variant care override ${i + 1}`}
                    value={v.care || ''}
                    onChange={(e) => variant(i, 'care', e.target.value || null)}
                  />
                  {(['chest_cm', 'length_cm', 'shoulder_cm'] as const).map(
                    (key) => (
                      <Field
                        key={key}
                        label={`Variant ${key.replace('_cm', '')} cm ${i + 1}`}
                        type="number"
                        step="0.1"
                        min="1"
                        value={v.measurements?.[key] ?? ''}
                        onChange={(e) => {
                          const m = { ...v.measurements };
                          if (e.target.value) m[key] = Number(e.target.value);
                          else delete m[key];
                          variant(
                            i,
                            'measurements',
                            Object.keys(m).length ? m : null,
                          );
                        }}
                      />
                    ),
                  )}
                </div>
              </details>
              <label className="catalogue-check">
                <input
                  type="checkbox"
                  checked={v.active}
                  onChange={(e) => variant(i, 'active', e.target.checked)}
                />
                Active variant {i + 1}
              </label>
              <Button
                variant="quiet"
                onClick={() => {
                  if (
                    window.confirm(
                      'Archive this variant on the next save? Its stock and historical references will be retained.',
                    )
                  ) {
                    setVariants((rows) => rows.filter((_, n) => n !== i));
                    setDirty(true);
                  }
                }}
              >
                Remove variant {i + 1}
              </Button>
            </details>
          ))}
          {!variants.length && (
            <p>No variants yet. Add variants before publishing.</p>
          )}
          <datalist id="catalogue-size-options">
            {sizeOptions
              .flatMap((group) => group.sizes)
              .map((size) => (
                <option key={size} value={size} />
              ))}
          </datalist>
        </section>
        <section className="panel">
          <div className="card-heading">
            <h2>Garment measurements (optional)</h2>
            <Button
              variant="secondary"
              onClick={() =>
                field('measurements', [
                  ...product.measurements,
                  { size: '', chest_cm: 0, length_cm: 0, shoulder_cm: 0 },
                ])
              }
            >
              Add measurement row
            </Button>
          </div>
          <p className="muted">
            Optional: enter supplier-verified measurements in centimetres, one
            row per size. Leave this section empty if unknown.
          </p>
          {product.measurements.map((row, i) => (
            <div className="measurement-row" key={i}>
              {(['size', 'chest_cm', 'length_cm', 'shoulder_cm'] as const).map(
                (key) => (
                  <Field
                    key={key}
                    label={`${key.replaceAll('_', ' ')} row ${i + 1}`}
                    value={row[key] || ''}
                    type={key === 'size' ? 'text' : 'number'}
                    min={key === 'size' ? undefined : 1}
                    step="0.1"
                    onChange={(e) =>
                      field(
                        'measurements',
                        product.measurements.map((m, n) =>
                          n === i
                            ? {
                                ...m,
                                [key]:
                                  key === 'size'
                                    ? e.target.value
                                    : Number(e.target.value),
                              }
                            : m,
                        ),
                      )
                    }
                  />
                ),
              )}
              <Button
                variant="quiet"
                onClick={() =>
                  field(
                    'measurements',
                    product.measurements.filter((_, n) => n !== i),
                  )
                }
              >
                Remove row {i + 1}
              </Button>
            </div>
          ))}
        </section>
        <section className="panel">
          <h2>Search engine listing</h2>
          <p className="section-description">
            Choose how this product appears in search results.
          </p>
          <div className="catalogue-grid">
            <Field
              label="SEO title"
              maxLength={70}
              value={product.seo_title || ''}
              onChange={(e) => field('seo_title', e.target.value)}
            />
            <Field
              label="Meta description"
              maxLength={160}
              value={product.seo_description || ''}
              onChange={(e) => field('seo_description', e.target.value)}
            />
          </div>
          <p className="search-preview">
            <strong>{product.seo_title || 'Product search title'}</strong>
            <br />
            elvarqo.uk / products / {product.slug || 'your-product'}
            <br />
            {product.seo_description ||
              'Your accurate product summary appears here.'}
          </p>
        </section>
      </fieldset>
      {saved && (
        <section className="panel">
          <h2>Saved images</h2>
          <p className="muted">
            JPEG, PNG or WebP. Still images only; minimum 200px per side,
            maximum 8MB / 24MP. Originals remain private. First image is
            primary.
          </p>
          <Feedback action={mediaAction} />
          <fieldset
            disabled={
              !editable ||
              mediaAction.busy ||
              action.busy ||
              dirty ||
              pendingImages.length > 0
            }
            className="catalogue-fieldset"
          >
            <div className="media-grid">
              {mediaEdits.map((m, i) => (
                <article className="media-card" key={m.id}>
                  <img src={m.url} alt={m.alt} loading="lazy" />
                  <Field
                    label={`Alt text image ${i + 1}`}
                    value={m.alt}
                    onChange={(e) =>
                      setMediaEdits((items) =>
                        items.map((x) =>
                          x.id === m.id ? { ...x, alt: e.target.value } : x,
                        ),
                      )
                    }
                  />
                  <Select
                    label={`Linked variant image ${i + 1}`}
                    value={m.variant_id ?? ''}
                    onChange={(e) =>
                      setMediaEdits((items) =>
                        items.map((x) =>
                          x.id === m.id
                            ? {
                                ...x,
                                variant_id: e.target.value
                                  ? Number(e.target.value)
                                  : null,
                              }
                            : x,
                        ),
                      )
                    }
                  >
                    <option value="">Shared by all variants</option>
                    {saved.variants.map((v) => (
                      <option key={v.id} value={v.id}>
                        {v.sku}
                      </option>
                    ))}
                  </Select>
                  <div className="catalogue-actions">
                    <Button
                      variant="secondary"
                      disabled={i === 0}
                      onClick={() => moveMedia(i, -1)}
                      aria-label={`Move image ${i + 1} earlier`}
                    >
                      Earlier
                    </Button>
                    <Button
                      variant="secondary"
                      disabled={i === mediaEdits.length - 1}
                      onClick={() => moveMedia(i, 1)}
                      aria-label={`Move image ${i + 1} later`}
                    >
                      Later
                    </Button>
                    <Button
                      variant="quiet"
                      onClick={() => {
                        if (
                          window.confirm(
                            'Archive this image? The private original and historical references will be retained.',
                          )
                        )
                          void mediaAction.run(async () => {
                            accept(
                              (
                                await request<{ product: Product }>(
                                  `products/${product.id}/media/${m.id}/archive`,
                                  {
                                    version: product.version,
                                    confirmation: 'ARCHIVE',
                                  },
                                )
                              ).product,
                            );
                            mediaAction.setNotice('Image archived.');
                          });
                      }}
                    >
                      Archive image {i + 1}
                    </Button>
                  </div>
                </article>
              ))}
            </div>
            {mediaEdits.length > 0 && (
              <Button
                variant="secondary"
                onClick={() =>
                  void mediaAction.run(async () => {
                    accept(
                      (
                        await request<{ product: Product }>(
                          `products/${product.id}/media-order`,
                          {
                            version: product.version,
                            items: mediaEdits.map((m) => ({
                              id: m.id,
                              alt: m.alt,
                              variant_id: m.variant_id,
                            })),
                          },
                        )
                      ).product,
                    );
                    mediaAction.setNotice(
                      'Image order, links and alt text saved.',
                    );
                  })
                }
              >
                Save image order & alt text
              </Button>
            )}
          </fieldset>
          {dirty && (
            <p className="muted">
              Save product changes and selected images to enable saved image
              editing.
            </p>
          )}
        </section>
      )}
      <Modal
        open={!!confirm}
        title={
          confirm === 'archive'
            ? 'Archive product?'
            : confirm === 'publish'
              ? 'Publish this saved product?'
              : 'Unpublish this product?'
        }
        onClose={() => setConfirm('')}
      >
        <p>
          {confirm === 'archive'
            ? 'This removes the product from the catalogue while retaining variants, stock, images and historical references.'
            : confirm === 'publish'
              ? 'The server will validate the saved content, images, active variants, prices and SEO before making the product public.'
              : 'The product and its image URLs will become private immediately.'}
        </p>
        <Button onClick={transition}>Confirm {confirm}</Button>
        <Button variant="secondary" onClick={() => setConfirm('')}>
          Cancel
        </Button>
      </Modal>
      <Modal
        open={preview}
        title="Saved product preview"
        onClose={() => setPreview(false)}
      >
        {saved && (
          <div className="saved-product-preview">
            <Badge>Saved {saved.status} / private staff preview</Badge>
            <h2>{saved.title}</h2>
            {saved.media[0] && (
              <img
                src={saved.media[0].url}
                alt={saved.media[0].alt}
                srcSet={saved.media[0].sources
                  .map((s) => `${s.url} ${s.width}w`)
                  .join(',')}
                sizes="(max-width:600px) 85vw, 560px"
              />
            )}
            <p className="preserve-lines">{saved.description}</p>
            <DataTable
              caption="Saved variants"
              columns={['Colour', 'Size', 'Price', 'Available', 'Status']}
              rows={saved.variants.map((v) => [
                v.colour,
                v.size,
                money(v.price_minor),
                v.stock_tracked === false
                  ? 'In stock (not counted)'
                  : (v.available ?? 0),
                v.active ? 'Active' : 'Inactive',
              ])}
            />
            <h3>Material & construction</h3>
            <p>{saved.material}</p>
            <h3>Fit</h3>
            <p>{saved.fit}</p>
            <h3>Care</h3>
            <p>{saved.care}</p>
            <DataTable
              caption="Garment measurements (cm)"
              columns={['Size', 'Chest', 'Length', 'Shoulder']}
              rows={saved.measurements.map((m) => [
                m.size,
                m.chest_cm,
                m.length_cm,
                m.shoulder_cm,
              ])}
            />
            <p className="muted">
              Shopping controls will connect to this catalogue in Phase 5.
            </p>
          </div>
        )}
      </Modal>
    </div>
  );
}
