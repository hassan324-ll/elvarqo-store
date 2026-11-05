'use client';
import { useEffect, useRef, useState } from 'react';
import type { ProductMedia } from '../../lib/catalogue-types';
export default function ProductImage({
  media,
  priority = false,
  sizes = '(max-width: 767px) 90vw, (max-width:1100px) 45vw, 33vw',
}: {
  media: ProductMedia | undefined;
  priority?: boolean;
  sizes?: string;
}) {
  const [failed, setFailed] = useState(false);
  const [attempt, setAttempt] = useState(0);
  const ref = useRef<HTMLImageElement | null>(null);
  useEffect(() => {
    const check = setTimeout(() => {
      const img = ref.current;
      if (img?.complete && img.naturalWidth === 0) setFailed(true);
    }, 0);
    return () => clearTimeout(check);
  }, [media?.id, attempt]);
  if (!media)
    return (
      <div
        className="image-placeholder"
        role="img"
        aria-label="Product image unavailable"
      >
        Image unavailable
      </div>
    );
  if (failed)
    return (
      <div className="image-placeholder" role="status">
        <p>We could not load this image.</p>
        <button
          type="button"
          onClick={(e) => {
            e.preventDefault();
            e.stopPropagation();
            setFailed(false);
            setAttempt((n) => n + 1);
          }}
        >
          Retry image
        </button>
      </div>
    );
  return (
    <img
      ref={ref}
      key={media.id + '-' + attempt}
      className="live-product-image"
      src={media.url + (attempt ? '?retry=' + attempt : '')}
      srcSet={
        attempt
          ? undefined
          : media.sources.map((s) => `${s.url} ${s.width}w`).join(',')
      }
      sizes={sizes}
      decoding="async"
      width={media.width}
      height={media.height}
      alt={media.alt}
      loading={priority ? 'eager' : 'lazy'}
      fetchPriority={priority ? 'high' : 'auto'}
      onError={() => setFailed(true)}
    />
  );
}
