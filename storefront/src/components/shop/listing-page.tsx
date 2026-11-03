import Link from 'next/link';
import { filterParams, listing, type Query } from '../../lib/catalogue-server';
import type { Listing } from '../../lib/catalogue-types';
import ProductGrid from './product-grid';
import ListingControls from './listing-controls';
import Recovery from './recovery';
export default async function ListingPage({
  query,
  path,
  title,
  collection,
  description,
  search = false,
  headingLevel = 'h1',
}: {
  query: Query;
  path: string;
  title: string;
  collection?: string;
  description?: string;
  search?: boolean;
  headingLevel?: 'h1' | 'h2';
}) {
  const Heading = headingLevel;
  let data: Listing | undefined;
  let error = '';
  try {
    data = await listing(filterParams(query, collection));
  } catch (e) {
    error =
      e instanceof Error ? e.message : 'We could not load this selection.';
  }
  const values = Object.fromEntries(
    Object.entries(query).filter(
      (entry): entry is [string, string] => typeof entry[1] === 'string',
    ),
  );
  function pageUrl(page: number) {
    const params = new URLSearchParams(values);
    params.set('page', String(page));
    return path + '?' + params;
  }
  return (
    <div className="store-section live-listing">
      <header className="page-intro">
        <p className="eyebrow">ELVARQO / THE COLLECTION</p>
        <Heading>
          {search && values.q ? `Results for “${values.q}”` : title}
        </Heading>
        <p>{description || 'Find the pieces that work for you.'}</p>
        {data && (
          <p role="status" className="results-count">
            {data.total} matching {data.total === 1 ? 'piece' : 'pieces'}
          </p>
        )}
      </header>
      <ListingControls
        path={path}
        values={values}
        colours={data?.facets.colours || []}
        sizes={data?.facets.sizes || []}
        search={search}
      />
      {error ? (
        <Recovery message={error} />
      ) : data?.items.length ? (
        <>
          <ProductGrid products={data.items} />
          <nav className="shop-pagination" aria-label="Product pages">
            {data.page > 1 && (
              <Link href={pageUrl(data.page - 1)} prefetch={false}>
                Previous page
              </Link>
            )}
            <span>
              Page {data.page} of {data.last_page}
            </span>
            {data.page < data.last_page && (
              <Link href={pageUrl(data.page + 1)} prefetch={false}>
                Next page
              </Link>
            )}
          </nav>
        </>
      ) : (
        <section className="shop-empty">
          <h2>No pieces match this selection.</h2>
          <p>
            Try fewer filters or another search. Your choices are still shown
            above.
          </p>
          <Link href={path} className="button button-primary" prefetch={false}>
            Clear filters and browse again
          </Link>
          {collection && (
            <Link href="/shop" className="text-link" prefetch={false}>
              Browse all pieces
            </Link>
          )}
          {data && data.page > 1 && (
            <Link href={pageUrl(1)} className="text-link" prefetch={false}>
              Return to the first page
            </Link>
          )}
        </section>
      )}
    </div>
  );
}
