import { useState } from 'react';
import { Button, Field, Icon, Modal, Select } from '../ui/components';
import { Feedback, request, TextArea, useAction } from './common';
import CategoryImageInput, { groupSaveBody } from './CategoryImageInput';
import type { Collection } from './types';

export default function GroupActions({
  group,
  groups,
  onChanged,
}: {
  group: Collection;
  groups: Collection[];
  onChanged: () => Promise<void>;
}) {
  const [mode, setMode] = useState<'edit' | 'delete' | null>(null);
  const [form, setForm] = useState({
    title: '',
    slug: '',
    description: '',
    parent_id: '',
    position: 0,
    active: false,
  });
  const [image, setImage] = useState<File | null>(null);
  const [removeImage, setRemoveImage] = useState(false);
  const [imageAlt, setImageAlt] = useState('');
  const action = useAction();
  const noun = group.kind === 'category' ? 'category' : 'collection';
  function open(next: 'edit' | 'delete') {
    action.setError('');
    setImage(null);
    setRemoveImage(false);
    setImageAlt(group.image_alt || group.title);
    setForm({
      title: group.title,
      slug: group.slug,
      description: group.description || '',
      parent_id: String(group.parent_id || ''),
      position: group.position,
      active: group.active,
    });
    setMode(next);
  }
  async function save() {
    await action.run(async () => {
      if (mode === 'delete')
        await request(`collections/${group.id}/archive`, {
          version: group.version,
          confirmation: 'ARCHIVE',
        });
      else
        await request(
          `collections/${group.id}`,
          groupSaveBody(
            {
              ...form,
              kind: group.kind,
              parent_id:
                group.kind === 'category'
                  ? Number(form.parent_id) || null
                  : null,
              version: group.version,
              image_alt: imageAlt,
            },
            image,
            removeImage,
          ),
        );
      setMode(null);
      await onChanged();
    });
  }
  return (
    <>
      <div className="product-row-actions">
        <button
          type="button"
          className="row-action-icon"
          aria-label={`Edit ${noun} ${group.title}`}
          title={`Edit ${noun}`}
          onClick={() => open('edit')}
        >
          <Icon name="edit" size={18} />
        </button>
        <button
          type="button"
          className="row-action-icon row-action-delete"
          aria-label={`Delete ${noun} ${group.title}`}
          title={`Delete ${noun}`}
          onClick={() => open('delete')}
        >
          <Icon name="trash" size={18} />
        </button>
      </div>
      <Modal
        open={!!mode}
        title={`${mode === 'delete' ? 'Delete' : 'Edit'} ${noun}`}
        onClose={() => {
          if (!action.busy) setMode(null);
        }}
      >
        <Feedback action={action} />
        {mode === 'delete' ? (
          <>
            <p>
              Delete <strong>{group.title}</strong>? It will be archived and
              removed from browsing and public navigation. Products, images and
              historical assignments will be preserved.
            </p>
            {groups.some((c) => c.parent_id === group.id) && (
              <p className="catalogue-notice">
                Move or delete its categories first. Products remain available
                in All products.
              </p>
            )}
          </>
        ) : (
          <fieldset className="catalogue-fieldset" disabled={action.busy}>
            <Field
              label={`${noun === 'category' ? 'Category' : 'Collection'} title`}
              value={form.title}
              maxLength={160}
              onChange={(e) =>
                setForm((f) => ({ ...f, title: e.target.value }))
              }
            />
            <Field
              label={`${noun === 'category' ? 'Category' : 'Collection'} slug`}
              value={form.slug}
              maxLength={180}
              onChange={(e) => setForm((f) => ({ ...f, slug: e.target.value }))}
            />
            {group.kind === 'category' && (
              <Select
                label="Parent collection"
                value={form.parent_id}
                onChange={(e) =>
                  setForm((f) => ({ ...f, parent_id: e.target.value }))
                }
              >
                <option value="">Choose collection</option>
                {groups
                  .filter((c) => c.kind === 'collection')
                  .map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.title}
                    </option>
                  ))}
              </Select>
            )}
            <TextArea
              label="Description"
              value={form.description}
              maxLength={4000}
              onChange={(e) =>
                setForm((f) => ({ ...f, description: e.target.value }))
              }
            />
            {group.kind === 'category' && (
              <CategoryImageInput
                current={group.image_url}
                alt={imageAlt}
                file={image}
                remove={removeImage}
                onFile={setImage}
                onRemove={setRemoveImage}
                onAlt={setImageAlt}
              />
            )}
            <Field
              label="Navigation position"
              type="number"
              min={0}
              max={100000}
              value={form.position}
              onChange={(e) =>
                setForm((f) => ({ ...f, position: Number(e.target.value) }))
              }
            />
            <label className="catalogue-check">
              <input
                type="checkbox"
                checked={form.active}
                onChange={(e) =>
                  setForm((f) => ({ ...f, active: e.target.checked }))
                }
              />
              Active in public catalogue
            </label>
          </fieldset>
        )}
        <div className="catalogue-actions">
          <Button
            variant="secondary"
            disabled={action.busy}
            onClick={() => setMode(null)}
          >
            Cancel
          </Button>
          <Button
            busy={action.busy}
            disabled={
              mode === 'delete' && groups.some((c) => c.parent_id === group.id)
            }
            onClick={() => void save()}
          >
            {mode === 'delete' ? `Delete ${noun}` : 'Save changes'}
          </Button>
        </div>
      </Modal>
    </>
  );
}
