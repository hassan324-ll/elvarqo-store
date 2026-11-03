/* oxlint-disable react/only-export-components */
import { useEffect, useState } from 'react';
import { Button, Field } from '../ui/components';

export function groupSaveBody(
  body: Record<string, unknown>,
  file: File | null,
  remove: boolean,
): Record<string, unknown> | FormData {
  if (!file) return { ...body, remove_image: remove };
  const data = new FormData();
  for (const [key, value] of Object.entries(body))
    if (value !== null && value !== undefined)
      data.set(
        key,
        typeof value === 'boolean' ? (value ? '1' : '0') : String(value),
      );
  data.set('image', file);
  data.set('remove_image', '0');
  return data;
}

export default function CategoryImageInput({
  current,
  alt,
  file,
  remove,
  onFile,
  onRemove,
  onAlt,
}: {
  current?: string | null;
  alt: string;
  file: File | null;
  remove: boolean;
  onFile: (file: File | null) => void;
  onRemove: (remove: boolean) => void;
  onAlt: (alt: string) => void;
}) {
  const [preview, setPreview] = useState('');
  const [error, setError] = useState('');
  useEffect(() => {
    if (!file) return;
    const url = URL.createObjectURL(file);
    let active = true;
    queueMicrotask(() => {
      if (active) setPreview(url);
    });
    return () => {
      active = false;
      URL.revokeObjectURL(url);
    };
  }, [file]);
  const source = file ? preview : remove ? null : current;
  return (
    <section className="category-image-input">
      <h3>Category image</h3>
      <p className="muted">
        Choose a square product photo for the category thumbnail. JPG, PNG or
        WebP · minimum 64px per side (400px recommended) · maximum 8MB / 24MP.
      </p>
      {source && (
        <img
          src={source}
          alt={alt || 'Selected category image'}
          className="category-image-preview"
        />
      )}
      <Field
        key={file ? file.name + file.size : 'empty'}
        label="Category image"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        onChange={(e) => {
          const selected = e.target.files?.[0] || null;
          if (
            selected &&
            (!['image/jpeg', 'image/png', 'image/webp'].includes(
              selected.type,
            ) ||
              selected.size > 8 * 1024 * 1024)
          ) {
            setError('Choose a JPEG, PNG or WebP no larger than 8MB.');
            e.target.value = '';
            return;
          }
          setError('');
          onFile(selected);
          if (selected) onRemove(false);
        }}
      />
      {error && (
        <p role="alert" className="auth-error">
          {error}
        </p>
      )}
      {(source || file) && (
        <Field
          label="Category image alternative text"
          maxLength={240}
          value={alt}
          onChange={(e) => onAlt(e.target.value)}
          hint="Describe the photo; leave blank to use the category name."
        />
      )}
      {(current || file) && (
        <Button
          variant="quiet"
          type="button"
          onClick={() => {
            onFile(null);
            onRemove(!remove);
          }}
        >
          {remove ? 'Keep current image' : 'Remove image'}
        </Button>
      )}
      {remove && <p className="muted">Image will be removed when you save.</p>}
    </section>
  );
}
