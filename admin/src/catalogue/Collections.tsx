import { useEffect, useState, useCallback } from 'react';
import {
  Badge,
  Button,
  DataTable,
  EmptyState,
  Field,
  Icon,
  Select,
} from '../ui/components';
import { Feedback, request, TextArea, useAction } from './common';
import CategoryImageInput, { groupSaveBody } from './CategoryImageInput';
import GroupActions from './GroupActions';
import type { Collection } from './types';
type Assigned = { id: number; title: string; status: string; position: number };
const empty = () => ({
  title: '',
  slug: '',
  kind: 'collection',
  parent_id: null as number | null,
  description: '',
  active: false,
  position: 0,
});
export default function Collections({
  canManage,
  onAddProduct,
  onGroupsChanged,
}: {
  canManage: boolean;
  onAddProduct: (id: number) => void;
  onGroupsChanged?: () => Promise<void>;
}) {
  const [items, setItems] = useState<Collection[]>([]);
  const [form, setForm] = useState<
    ReturnType<typeof empty> & { id?: number; version?: number }
  >(empty);
  const [image, setImage] = useState<File | null>(null);
  const [removeImage, setRemoveImage] = useState(false);
  const [imageAlt, setImageAlt] = useState('');
  const [savedImage, setSavedImage] = useState<string | null>(null);
  const [ordered, setOrdered] = useState<Assigned[] | null>(null);
  const action = useAction();
  const { run } = action;
  const load = useCallback(() => {
    return request<{ items: Collection[] }>('collections').then((r) =>
      setItems(r.items),
    );
  }, []);
  useEffect(() => {
    void run(load);
  }, [run, load]);
  async function choose(c: Collection) {
    setImage(null);
    setRemoveImage(false);
    setImageAlt(c.image_alt || c.title);
    setSavedImage(c.image_url || null);
    setForm({
      ...c,
      parent_id: c.parent_id ?? null,
      description: c.description || '',
    });
    await action.run(async () =>
      setOrdered(
        (await request<{ items: Assigned[] }>(`collections/${c.id}/products`))
          .items,
      ),
    );
  }
  async function save() {
    await action.run(async () => {
      const { collection } = await request<{ collection: Collection }>(
        form.id ? 'collections/' + form.id : 'collections',
        groupSaveBody({ ...form, image_alt: imageAlt }, image, removeImage),
      );
      setImage(null);
      setRemoveImage(false);
      setSavedImage(collection.image_url || null);
      setForm({
        ...collection,
        parent_id: collection.parent_id ?? null,
        description: collection.description || '',
      });
      await load();
      await onGroupsChanged?.();
      action.setNotice(
        'Collection saved. Position controls its public navigation order.',
      );
    });
  }
  const roots = items.filter((c) => c.kind === 'collection');
  const unassigned = items.filter(
    (c) => c.kind === 'category' && !roots.some((p) => p.id === c.parent_id),
  );
  const tree = [
    ...roots.flatMap((c) => [
      c,
      ...items.filter((child) => child.parent_id === c.id),
    ]),
    ...unassigned,
  ];
  function addCategory(parent: number) {
    setImage(null);
    setRemoveImage(false);
    setImageAlt('');
    setSavedImage(null);
    setForm({ ...empty(), kind: 'category', parent_id: parent });
    setOrdered(null);
    document
      .querySelector('.collection-editor')
      ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  return (
    <div className="collections-workspace">
      <section className="panel collections-list">
        <div className="card-heading">
          <div>
            <h2>Collections & categories</h2>
            <p className="section-description">
              Organise products and shape your store navigation.
            </p>
          </div>
          <Button
            key="cell-1"
            variant="secondary"
            busy={action.busy}
            onClick={() => void action.run(load)}
          >
            Refresh collections
          </Button>
        </div>
        <Feedback action={action} />
        {unassigned.length > 0 && (
          <p className="catalogue-notice">
            {unassigned.length} existing categories need a parent collection.
            Edit each category and choose its collection; existing products stay
            assigned.
          </p>
        )}
        {items.length > 0 && (
          <DataTable
            caption="Actual collections and navigation order"
            columns={[
              'Collection',
              'Kind',
              'Parent collection',
              'Navigation position',
              'Status',
              'Action',
            ]}
            rows={tree.map((c) => [
              <span
                key="title"
                className={c.kind === 'category' ? 'category-child-label' : ''}
              >
                {c.kind === 'category' ? '↳ ' : ''}
                {c.title}
              </span>,
              c.kind,
              c.parent_id
                ? roots.find((p) => p.id === c.parent_id)?.title || 'Unassigned'
                : c.kind === 'category'
                  ? 'Choose collection'
                  : '—',
              c.position,
              <Badge key="cell-2" tone={c.active ? 'success' : 'neutral'}>
                {c.active ? 'Active' : 'Hidden'}
              </Badge>,
              <div className="product-row-actions" key="actions">
                {canManage && (
                  <GroupActions
                    group={c}
                    groups={items}
                    onChanged={async () => {
                      setImage(null);
                      setRemoveImage(false);
                      setImageAlt('');
                      setSavedImage(null);
                      setForm(empty());
                      setOrdered(null);
                      await load();
                      await onGroupsChanged?.();
                    }}
                  />
                )}
                <button
                  type="button"
                  className="row-action-icon"
                  aria-label={`${canManage ? 'Edit' : 'View'} ${c.title}`}
                  title={canManage ? 'Edit' : 'View'}
                  onClick={() => void choose(c)}
                >
                  <Icon name={canManage ? 'edit' : 'eye'} size={18} />
                </button>
                {canManage && c.kind === 'collection' && (
                  <Button
                    variant="secondary"
                    onClick={() => addCategory(c.id)}
                    aria-label={`Add category to ${c.title}`}
                  >
                    <Icon name="plus" size={16} />
                    Add category
                  </Button>
                )}
              </div>,
            ])}
          />
        )}
        {!items.length && (
          <EmptyState title="Build your first collection">
            Group products by style, season or category. Create a collection
            below to get started.
          </EmptyState>
        )}
      </section>
      <div className="collection-editor-layout">
        <section className="panel collection-editor">
          <div className="collection-editor-heading">
            <span className="section-icon" aria-hidden="true">
              <Icon name="box" size={20} />
            </span>
            <div>
              <h2>
                {form.id ? 'Edit ' : 'Add '}
                {form.kind === 'category' ? 'category' : 'collection'}
              </h2>
              <p className="section-description">
                Set the details, then choose when it becomes visible.
              </p>
            </div>
            <Badge tone={form.active ? 'success' : 'neutral'}>
              {form.active ? 'Active' : 'Hidden'}
            </Badge>
          </div>
          <fieldset
            disabled={!canManage || action.busy}
            className="catalogue-fieldset"
          >
            <div className="catalogue-grid">
              <Field
                label={
                  form.kind === 'category'
                    ? 'Category title'
                    : 'Collection title'
                }
                placeholder="e.g. Everyday essentials"
                hint="The name customers see in your store."
                value={form.title}
                onChange={(e) =>
                  setForm((v) => ({ ...v, title: e.target.value }))
                }
              />
              <Field
                label={
                  form.kind === 'category' ? 'Category slug' : 'Collection slug'
                }
                placeholder="everyday-essentials"
                hint="Use lowercase words separated by hyphens."
                value={form.slug}
                onChange={(e) =>
                  setForm((v) => ({ ...v, slug: e.target.value }))
                }
              />
              <Select
                label="Collection kind"
                value={form.kind}
                onChange={(e) =>
                  setForm((v) => ({
                    ...v,
                    kind: e.target.value,
                    parent_id: null,
                  }))
                }
              >
                <option value="collection">Collection</option>
                <option value="category">Category</option>
              </Select>
              {form.kind === 'category' && (
                <Select
                  label="Parent collection"
                  value={form.parent_id ?? ''}
                  onChange={(e) =>
                    setForm((v) => ({
                      ...v,
                      parent_id: e.target.value ? Number(e.target.value) : null,
                    }))
                  }
                >
                  <option value="">Choose a collection</option>
                  {roots
                    .filter((c) => c.id !== form.id)
                    .map((c) => (
                      <option value={c.id} key={c.id}>
                        {c.title}
                      </option>
                    ))}
                </Select>
              )}
              <Field
                label="Navigation position"
                hint="Lower numbers appear first in navigation."
                type="number"
                min={0}
                value={form.position}
                onChange={(e) =>
                  setForm((v) => ({ ...v, position: Number(e.target.value) }))
                }
              />
            </div>
            <TextArea
              label="Collection description"
              placeholder="Describe what brings these products together…"
              value={form.description}
              onChange={(e) =>
                setForm((v) => ({ ...v, description: e.target.value }))
              }
            />
            {form.kind === 'category' && (
              <CategoryImageInput
                current={savedImage}
                alt={imageAlt}
                file={image}
                remove={removeImage}
                onFile={setImage}
                onRemove={setRemoveImage}
                onAlt={setImageAlt}
              />
            )}
            <label className="catalogue-check collection-visibility">
              <input
                type="checkbox"
                checked={form.active}
                onChange={(e) =>
                  setForm((v) => ({ ...v, active: e.target.checked }))
                }
              />
              <span>
                <strong>Active in public catalogue</strong>
                <small>
                  Allow this collection to appear in the public catalogue and
                  navigation.
                </small>
              </span>
            </label>
            <div className="catalogue-actions collection-save-actions">
              <Button key="cell-4" busy={action.busy} onClick={save}>
                Save {form.kind === 'category' ? 'category' : 'collection'}
              </Button>
              {form.id && form.kind === 'collection' && (
                <Button
                  variant="secondary"
                  onClick={() => addCategory(form.id!)}
                >
                  <Icon name="plus" size={16} />
                  Add category to this collection
                </Button>
              )}
              {form.id && form.kind === 'category' && form.parent_id && (
                <Button
                  variant="secondary"
                  onClick={() => onAddProduct(form.id!)}
                >
                  Add products to this category <Icon name="arrow" size={16} />
                </Button>
              )}
              <Button
                key="cell-5"
                variant="secondary"
                onClick={() => {
                  setImage(null);
                  setRemoveImage(false);
                  setImageAlt('');
                  setSavedImage(null);
                  setForm(empty());
                  setOrdered(null);
                }}
              >
                New collection
              </Button>
            </div>
          </fieldset>
          {ordered && (
            <>
              <h3>Products in this collection</h3>
              <p className="muted">
                Assign products from the product editor, then order them here.
                Archived references remain retained.
              </p>
              <ol className="collection-order">
                {ordered.map((p, i) => (
                  <li key={p.id}>
                    <span key="cell-6">
                      {p.title} / {p.status}
                    </span>
                    {canManage && (
                      <>
                        <Button
                          key="cell-7"
                          variant="quiet"
                          disabled={i === 0}
                          aria-label={`Move ${p.title} earlier`}
                          onClick={() =>
                            setOrdered((rows) => {
                              const next = [...rows!];
                              [next[i - 1], next[i]] = [next[i], next[i - 1]];
                              return next;
                            })
                          }
                        >
                          Earlier
                        </Button>
                        <Button
                          key="cell-8"
                          variant="quiet"
                          disabled={i === ordered.length - 1}
                          aria-label={`Move ${p.title} later`}
                          onClick={() =>
                            setOrdered((rows) => {
                              const next = [...rows!];
                              [next[i + 1], next[i]] = [next[i], next[i + 1]];
                              return next;
                            })
                          }
                        >
                          Later
                        </Button>
                      </>
                    )}
                  </li>
                ))}
              </ol>
              {canManage && (
                <Button
                  key="cell-9"
                  busy={action.busy}
                  onClick={() =>
                    void action.run(async () => {
                      const { collection } = await request<{
                        collection: Collection;
                      }>(`collections/${form.id}/order`, {
                        version: form.version,
                        product_ids: ordered.map((p) => p.id),
                      });
                      setForm({
                        ...collection,
                        parent_id: collection.parent_id ?? null,
                        description: collection.description || '',
                      });
                      await load();
                      await onGroupsChanged?.();
                      action.setNotice('Product order saved.');
                    })
                  }
                >
                  Save collection product order
                </Button>
              )}
            </>
          )}
        </section>
        <aside className="collection-guide" aria-label="Collection guidance">
          <p className="eyebrow">A considered catalogue</p>
          <h3>Make products easier to discover.</h3>
          <p>
            Collections are your main menu groups. Create categories inside each
            collection, then assign products to those categories.
          </p>
          <ol>
            <li>
              <strong>Start with the details</strong>
              <span>Give the group a clear name and a unique URL slug.</span>
            </li>
            <li>
              <strong>Add your products</strong>
              <span>
                Create a category in the collection, then select that category
                in the product editor.
              </span>
            </li>
            <li>
              <strong>Choose its visibility</strong>
              <span>
                Mark it active when ready. Individual products also need to be
                published.
              </span>
            </li>
          </ol>
          <p className="collection-guide-note">
            You can update these details and reorder products later.
          </p>
        </aside>
      </div>
    </div>
  );
}
