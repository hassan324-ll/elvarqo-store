import './forms.css';
import { useEffect, useState, useCallback, useRef } from 'react';
import {
  Badge,
  Button,
  DataTable,
  EmptyState,
  Field,
  Icon,
  Modal,
  Pagination,
  Select,
} from '../ui/components';
import type { StaffIdentity } from '../lib/staff-api';
import { Feedback, request, TextArea, useAction } from './common';
import { type Collection, type Page, type Product, money } from './types';
import ProductEditor from './ProductEditor';
import Collections from './Collections';
import CategoryImageInput, { groupSaveBody } from './CategoryImageInput';
import GroupActions from './GroupActions';
export default function Catalogue({ staff }: { staff: StaffIdentity }) {
  const canManage = staff.permissions.includes('catalogue.manage');
  const params = new URLSearchParams(location.search);
  const [tab, setTab] = useState('products');
  const [initialCollectionId, setInitialCollectionId] = useState<number | null>(
    null,
  );
  const [editing, setEditing] = useState<number | 'new' | null>(() =>
    params.get('product') === 'new'
      ? 'new'
      : params.get('product')
        ? Number(params.get('product'))
        : null,
  );
  const [deleteTarget, setDeleteTarget] = useState<Product | null>(null);
  const deletion = useAction();
  const categoryAction = useAction();
  const [categoryImage, setCategoryImage] = useState<File | null>(null);
  const [categoryImageAlt, setCategoryImageAlt] = useState('');
  const [removeCategoryImage, setRemoveCategoryImage] = useState(false);
  const [categoryParent, setCategoryParent] = useState<Collection | null>(null);
  const [categoryForm, setCategoryForm] = useState({
    title: '',
    slug: '',
    description: '',
    position: 0,
    active: false,
  });
  const [categorySlugEdited, setCategorySlugEdited] = useState(false);

  const [q, setQ] = useState('');
  const [status, setStatus] = useState('all');
  const [collectionId, setCollectionId] = useState('');
  const [collections, setCollections] = useState<Collection[]>([]);
  const [catalogueTotal, setCatalogueTotal] = useState(0);
  const [filtersOpen, setFiltersOpen] = useState(false);
  const generation = useRef(0);
  const invalidate = useCallback(() => {
    generation.current++;
  }, []);
  const [page, setPage] = useState(1);
  const [data, setData] = useState<Page<Product> | null>(null);
  const action = useAction();
  const { run } = action;
  const queryString = `products?page=${page}&q=${encodeURIComponent(q)}&status=${status}${collectionId ? '&collection_id=' + collectionId : ''}`;
  const load = useCallback(async () => {
    const current = ++generation.current;
    await run(async () => {
      const [products, groups] = await Promise.all([
        request<Page<Product>>(queryString),
        request<{ items: Collection[]; catalogue_total: number }>(
          'collections',
        ),
      ]);
      if (current !== generation.current) return;
      setData(products);
      setCollections(groups.items);
      setCatalogueTotal(groups.catalogue_total);
    });
  }, [run, queryString]);
  useEffect(() => {
    if (editing === null && tab === 'products') void load();
    return invalidate;
  }, [editing, tab, load, invalidate]);
  const selectedCollection = collections.find(
    (c) => String(c.id) === collectionId,
  );
  const rootCollections = collections.filter((c) => c.kind === 'collection');
  const activeRoot =
    selectedCollection?.kind === 'category'
      ? rootCollections.find((c) => c.id === selectedCollection.parent_id)
      : selectedCollection;
  const childCategories = activeRoot
    ? collections.filter(
        (c) => c.kind === 'category' && c.parent_id === activeRoot.id,
      )
    : [];
  function browseGroup(id: string) {
    setTab('products');
    setCollectionId(id);
    setPage(1);
    setQ('');
    setStatus('all');
  }
  function openCategory(parent: Collection) {
    categoryAction.setError('');
    setCategoryImage(null);
    setCategoryImageAlt('');
    setRemoveCategoryImage(false);
    setCategoryForm({
      title: '',
      slug: '',
      description: '',
      active: false,
      position:
        Math.max(
          -1,
          ...collections
            .filter((c) => c.parent_id === parent.id)
            .map((c) => c.position),
        ) + 1,
    });
    setCategorySlugEdited(false);
    setCategoryParent(parent);
  }
  async function saveCategory() {
    await categoryAction.run(async () => {
      if (!categoryParent || !canManage) return;
      await request(
        'collections',
        groupSaveBody(
          {
            ...categoryForm,
            kind: 'category',
            parent_id: categoryParent.id,
            image_alt: categoryImageAlt,
          },
          categoryImage,
          removeCategoryImage,
        ),
      );
      setCategoryParent(null);
      await load();
      action.setNotice(`Category added to ${categoryParent.title}.`);
    });
  }
  function edit(id: number | 'new' | null) {
    setEditing(id);
    const url = new URL(location.href);
    if (id === null) url.searchParams.delete('product');
    else url.searchParams.set('product', String(id));
    history.replaceState(null, '', url);
  }
  if (editing !== null)
    return (
      <ProductEditor
        id={editing}
        initialCollectionId={initialCollectionId}
        canManage={canManage}
        onBack={() => edit(null)}
      />
    );
  return (
    <div className="catalogue-workspace">
      <header className="admin-page-heading">
        <div>
          <p className="eyebrow">ELVARQO / CATALOGUE</p>
          <h1>Product catalogue</h1>
          <p className="muted">
            Create thoughtfully. Preview before publication.
          </p>
        </div>
        <div className="catalogue-heading-actions">
          <Button
            variant="secondary"
            aria-pressed={tab === 'collections'}
            onClick={() => setTab('collections')}
          >
            <Icon name="settings" size={16} />
            {canManage ? 'Manage collections' : 'View collections'}
          </Button>
          {canManage && (
            <Button
              key="cell-1"
              onClick={() => {
                setInitialCollectionId(
                  selectedCollection?.kind === 'category'
                    ? selectedCollection.id
                    : null,
                );
                edit('new');
              }}
            >
              Add product
            </Button>
          )}
        </div>
      </header>
      <div className="catalogue-hierarchy-navigation">
        <div
          className="collection-filter-chips hierarchy-collection-tabs"
          role="group"
          aria-label="Browse collections"
        >
          <button
            type="button"
            className="collection-filter-chip"
            aria-pressed={tab === 'products' && !collectionId}
            onClick={() => browseGroup('')}
          >
            <Icon name="grid" size={16} />
            All products<span>{catalogueTotal}</span>
          </button>
          {rootCollections.map((c) => (
            <button
              type="button"
              key={c.id}
              className="collection-filter-chip"
              aria-pressed={tab === 'products' && activeRoot?.id === c.id}
              onClick={() => browseGroup(String(c.id))}
            >
              <Icon name="box" size={16} />
              {c.title}
              <span>{c.product_count ?? 0}</span>
            </button>
          ))}
        </div>
        {tab === 'products' && activeRoot && (
          <div className="hierarchy-category-panel">
            <div className="hierarchy-category-heading">
              <p className="hierarchy-category-caption">
                Categories in <strong>{activeRoot.title}</strong>
              </p>
              {canManage && (
                <GroupActions
                  group={activeRoot}
                  groups={collections}
                  onChanged={async () => {
                    setCollectionId('');
                    setPage(1);
                    await load();
                  }}
                />
              )}
              {canManage && (
                <Button
                  variant="secondary"
                  onClick={() => openCategory(activeRoot)}
                >
                  <Icon name="plus" size={16} />
                  Add category
                </Button>
              )}
            </div>
            <div
              className="collection-filter-chips hierarchy-category-tabs"
              role="group"
              aria-label={`Categories in ${activeRoot.title}`}
            >
              {childCategories.map((c) => (
                <button
                  type="button"
                  key={c.id}
                  className="collection-filter-chip"
                  aria-pressed={collectionId === String(c.id)}
                  onClick={() => browseGroup(String(c.id))}
                >
                  {c.title}
                  <span>{c.product_count ?? 0}</span>
                </button>
              ))}
            </div>
            {!childCategories.length && (
              <p className="muted">
                No categories in this collection yet.
                {canManage
                  ? ' Click Add category to create the first one.'
                  : ''}
              </p>
            )}
          </div>
        )}
      </div>
      {tab === 'collections' ? (
        <Collections
          canManage={canManage}
          onGroupsChanged={load}
          onAddProduct={(id) => {
            setInitialCollectionId(id);
            setCollectionId(String(id));
            setPage(1);
            edit('new');
          }}
        />
      ) : selectedCollection?.kind === 'collection' ? (
        <section className="panel collection-category-browser">
          <div className="card-heading">
            <div>
              <h2>{selectedCollection.title} categories</h2>
              <p className="section-description">
                Choose a category to view its products.
              </p>
            </div>
            <Badge>{childCategories.length} categories</Badge>
          </div>
          <Feedback action={action} />
          {childCategories.length ? (
            <div className="category-browse-grid">
              {childCategories.map((category) => (
                <div className="category-management-card" key={category.id}>
                  <button
                    type="button"
                    className="category-browse-card"
                    onClick={() => browseGroup(String(category.id))}
                    aria-label={`View products in ${category.title}`}
                  >
                    <span className="category-browse-icon">
                      {category.image_url ? (
                        <img
                          src={category.image_url}
                          alt={category.image_alt || category.title}
                        />
                      ) : (
                        <Icon name="box" size={22} />
                      )}
                    </span>
                    <span>
                      <strong>{category.title}</strong>
                      <small>
                        {category.product_count ?? 0} products ·{' '}
                        {category.active ? 'Active' : 'Hidden'}
                      </small>
                    </span>
                    <Icon name="arrow" size={18} />
                  </button>
                  {canManage && (
                    <GroupActions
                      group={category}
                      groups={collections}
                      onChanged={load}
                    />
                  )}
                </div>
              ))}
            </div>
          ) : (
            <EmptyState title="No categories yet">
              {canManage
                ? 'Use Add category above to create the first category in this collection.'
                : 'This collection does not have any categories yet.'}
            </EmptyState>
          )}
        </section>
      ) : (
        <section className="panel">
          <div className="collection-browser-heading">
            <div>
              <h2>Browse your products</h2>
              <p className="section-description">
                Use the collection and category filters above, or search your
                products.
              </p>
            </div>
            <span className="catalogue-result-count">
              {catalogueTotal} total products
            </span>
          </div>
          <form
            className="catalogue-search-toolbar"
            onSubmit={(e) => {
              e.preventDefault();
              setPage(1);
              if (page === 1) void load();
            }}
          >
            <Field
              label="Search product catalogue"
              type="search"
              placeholder={
                selectedCollection
                  ? `Search ${selectedCollection.title}…`
                  : 'Search by product name or SKU…'
              }
              value={q}
              onChange={(e) => {
                setQ(e.target.value);
                setPage(1);
              }}
            />
            <Button busy={action.busy} type="submit" variant="secondary">
              <Icon name="search" size={16} />
              Search products
            </Button>
            <Button
              type="button"
              variant="secondary"
              aria-expanded={filtersOpen}
              aria-controls="catalogue-extra-filters"
              onClick={() => setFiltersOpen((open) => !open)}
            >
              <Icon name="settings" size={16} />
              Filters
              {status !== 'all' && (
                <span
                  className="filter-active-dot"
                  aria-label="Status filter active"
                />
              )}
            </Button>
          </form>
          {filtersOpen && (
            <div
              id="catalogue-extra-filters"
              className="catalogue-extra-filters"
            >
              <Select
                label="Product status"
                value={status}
                onChange={(e) => {
                  setStatus(e.target.value);
                  setPage(1);
                }}
              >
                <option value="all">All statuses</option>
                <option value="draft">Draft</option>
                <option value="published">Published</option>
                <option value="archived">Archived</option>
              </Select>
              <p className="muted">
                All statuses includes draft, published and archived products.
                Public storefront visibility is unchanged.
              </p>
              <Button
                variant="quiet"
                onClick={() => {
                  setStatus('all');
                  setCollectionId('');
                  setQ('');
                  setPage(1);
                }}
              >
                Clear filters
              </Button>
            </div>
          )}
          <div className="catalogue-list-heading">
            <strong>{selectedCollection?.title || 'All products'}</strong>
            <span>
              {data?.total ?? 0} results · 10 per page
              {status !== 'all' ? ` · ${status}` : ''}
            </span>
          </div>
          <Feedback action={action} />
          {data?.items.length ? (
            <>
              <DataTable
                caption="Saved products · 10 per page"
                columns={[
                  'ID',
                  'Product',
                  'Variants / SKU',
                  'Price',
                  'Available',
                  'Status',
                  'Action',
                ]}
                rows={data.items.map((p) => [
                  p.id,
                  <strong key="cell-5">{p.title}</strong>,
                  <span key="cell-6">
                    {p.variants.length} variants
                    <br />
                    {p.variants[0]?.sku || 'No SKU'}
                  </span>,
                  p.variants.length
                    ? `From ${money(Math.min(...p.variants.map((v) => v.price_minor)))}`
                    : '—',
                  p.variants.reduce((n, v) => n + (v.available || 0), 0),
                  <Badge
                    key="cell-7"
                    tone={p.status === 'published' ? 'success' : 'neutral'}
                  >
                    {p.status}
                  </Badge>,
                  <div className="product-row-actions" key="cell-9">
                    {p.status !== 'archived' && (
                      <button
                        type="button"
                        className="row-action-icon"
                        aria-label={`${canManage ? 'Edit' : 'View'} ${p.title}`}
                        title={canManage ? 'Edit product' : 'View product'}
                        onClick={() => edit(p.id)}
                      >
                        <Icon name={canManage ? 'edit' : 'eye'} size={18} />
                      </button>
                    )}
                    {canManage && (
                      <button
                        type="button"
                        className="row-action-icon row-action-delete"
                        aria-label={`Delete ${p.title}`}
                        title="Delete product (archive)"
                        onClick={() => {
                          deletion.setError('');
                          setDeleteTarget(p);
                        }}
                      >
                        <Icon name="trash" size={18} />
                      </button>
                    )}
                  </div>,
                ])}
              />
              <Pagination
                page={data.page}
                total={data.last_page}
                onChange={setPage}
              />
              <p className="muted">{data.total} saved products</p>
            </>
          ) : (
            !action.busy && (
              <EmptyState title="No matching products">
                {data?.total === 0
                  ? 'No products match this collection and filter. Add a product or choose All products.'
                  : 'Search again or retry the connection.'}
              </EmptyState>
            )
          )}
        </section>
      )}
      <Modal
        open={!!categoryParent}
        title="Add category"
        onClose={() => {
          if (!categoryAction.busy) setCategoryParent(null);
        }}
      >
        <p className="muted">
          Create a category inside <strong>{categoryParent?.title}</strong>.
        </p>
        <Feedback action={categoryAction} />
        <form
          onSubmit={(e) => {
            e.preventDefault();
            void saveCategory();
          }}
        >
          <fieldset
            className="catalogue-fieldset"
            disabled={!canManage || categoryAction.busy}
          >
            <Field
              label="Parent collection"
              value={categoryParent?.title || ''}
              readOnly
            />
            <div className="catalogue-grid">
              <Field
                label="Category title"
                placeholder="e.g. T-Shirts"
                required
                maxLength={160}
                value={categoryForm.title}
                onChange={(e) => {
                  const title = e.target.value;
                  setCategoryForm((form) => ({
                    ...form,
                    title,
                    ...(!categorySlugEdited
                      ? {
                          slug: title
                            .toLowerCase()
                            .normalize('NFKD')
                            .replace(/[\u0300-\u036f]/g, '')
                            .replace(/[^a-z0-9]+/g, '-')
                            .replace(/^-|-$/g, '')
                            .slice(0, 180),
                        }
                      : {}),
                  }));
                }}
              />
              <Field
                label="Category slug"
                required
                maxLength={180}
                hint="Unique across all collections."
                value={categoryForm.slug}
                onChange={(e) => {
                  setCategorySlugEdited(true);
                  setCategoryForm((form) => ({
                    ...form,
                    slug: e.target.value,
                  }));
                }}
              />
            </div>
            <TextArea
              label="Category description"
              maxLength={4000}
              value={categoryForm.description}
              onChange={(e) =>
                setCategoryForm((form) => ({
                  ...form,
                  description: e.target.value,
                }))
              }
            />
            <Field
              label="Navigation position"
              type="number"
              min={0}
              max={100000}
              required
              value={categoryForm.position}
              onChange={(e) =>
                setCategoryForm((form) => ({
                  ...form,
                  position: Number(e.target.value),
                }))
              }
            />
            <CategoryImageInput
              alt={categoryImageAlt}
              file={categoryImage}
              remove={removeCategoryImage}
              onFile={setCategoryImage}
              onRemove={setRemoveCategoryImage}
              onAlt={setCategoryImageAlt}
            />
            <label className="catalogue-check collection-visibility">
              <input
                type="checkbox"
                checked={categoryForm.active}
                onChange={(e) =>
                  setCategoryForm((form) => ({
                    ...form,
                    active: e.target.checked,
                  }))
                }
              />
              <span>
                <strong>Active in public catalogue</strong>
                <small>
                  Show under this collection when the parent collection is
                  active.
                </small>
              </span>
            </label>
            <div className="catalogue-actions">
              <Button type="submit" busy={categoryAction.busy}>
                Save category
              </Button>
              <Button
                type="button"
                variant="secondary"
                onClick={() => setCategoryParent(null)}
              >
                Cancel
              </Button>
            </div>
          </fieldset>
        </form>
      </Modal>
      <Modal
        open={!!deleteTarget}
        title="Delete product?"
        onClose={() => {
          if (!deletion.busy) setDeleteTarget(null);
        }}
      >
        <p>
          Remove <strong>{deleteTarget?.title}</strong> from your current
          catalogue permanently? Its details, variants, assignments and image
          files will be removed. This cannot be undone. Products with orders or
          stock history cannot be permanently deleted.
        </p>
        <Feedback action={deletion} />
        <div className="catalogue-actions">
          <Button
            variant="secondary"
            disabled={deletion.busy}
            onClick={() => setDeleteTarget(null)}
          >
            Cancel
          </Button>
          <Button
            busy={deletion.busy}
            onClick={() =>
              void deletion.run(async () => {
                if (!deleteTarget) return;
                const removed = await request<{
                  image_cleanup_pending: boolean;
                }>(`products/${deleteTarget.id}/delete`, {
                  version: deleteTarget.version,
                  confirmation: 'DELETE',
                });
                setDeleteTarget(null);
                const next = await request<Page<Product>>(queryString);
                setData(next);
                if (page > next.last_page) setPage(next.last_page);
                await load();
                action.setNotice(
                  removed.image_cleanup_pending
                    ? 'Product deleted; some image files could not be removed. Contact the store operator for storage cleanup.'
                    : 'Product permanently deleted.',
                );
              })
            }
          >
            Delete product
          </Button>
        </div>
      </Modal>
    </div>
  );
}
