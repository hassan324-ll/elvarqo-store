'use client';
import { useEffect, useState } from 'react';
import Link from 'next/link';
import { type Product, publicApi } from '../../lib/catalogue-types';
export default function SearchSuggestions({
  query,
  onSelect,
}: {
  query: string;
  onSelect: () => void;
}) {
  const [result, setResult] = useState<{
    query: string;
    items: Product[];
    total: number;
    failed: boolean;
  } | null>(null);
  useEffect(() => {
    if (query.trim().length < 2) return;
    const controller = new AbortController();
    const timer = setTimeout(() => {
      void fetch(
        publicApi +
          '/catalogue/products?per_page=4&q=' +
          encodeURIComponent(query),
        {
          signal: AbortSignal.any([
            controller.signal,
            AbortSignal.timeout(7000),
          ]),
          cache: 'no-store',
          credentials: 'omit',
        },
      )
        .then(async (r) => {
          if (!r.ok) throw Error();
          return r.json();
        })
        .then((r) =>
          setResult({
            query,
            items: r.data.items,
            total: r.data.total,
            failed: false,
          }),
        )
        .catch(() => {
          if (!controller.signal.aborted)
            setResult({ query, items: [], total: 0, failed: true });
        });
    }, 300);
    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [query]);
  if (query.trim().length < 2)
    return (
      <p className="muted">
        Type at least two characters for suggestions, or submit your search.
      </p>
    );
  if (result?.query !== query)
    return <p role="status">Looking for matching pieces…</p>;
  return (
    <div className="search-suggestions">
      <p role="status">
        {result.failed
          ? 'Suggestions are temporarily unavailable. You can still submit your search.'
          : `${result.total} matching pieces`}
      </p>
      {result.items.map((p) => (
        <Link
          key={p.id}
          href={'/products/' + p.slug}
          prefetch={false}
          onClick={onSelect}
        >
          {p.title}
        </Link>
      ))}
    </div>
  );
}
