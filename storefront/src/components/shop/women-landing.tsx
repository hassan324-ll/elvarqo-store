import Link from 'next/link';
import { listing, type Query } from '../../lib/catalogue-server';
import type { Collection, Product } from '../../lib/catalogue-types';
import { money } from '../../lib/catalogue-types';
import { Icon } from '../../ui/components';
import ListingPage from './listing-page';
import ProductGrid from './product-grid';
import Recovery from './recovery';
import Newsletter from '../newsletter';
import CollectionRefresh from './collection-refresh';

function CategoryPhoto({ category }: { category: Collection }) {
  return category.image_url ? (
    <img
      src={category.image_url}
      alt={category.image_alt || category.title}
      loading="lazy"
    />
  ) : (
    <span className="women-photo-fallback" aria-hidden="true">
      {category.title.charAt(0)}
    </span>
  );
}
function photo(p: Product) {
  return p.media.find((m) => !m.variant_id) || p.media[0];
}
export default async function WomenLanding({
  collection,
  query,
}: {
  collection: Collection;
  query: Query;
}) {
  const categories = collection.categories || [];
  const results = await Promise.allSettled([
    listing(
      new URLSearchParams({
        collection: collection.slug,
        sort: 'trending',
        per_page: '3',
      }),
    ),
    listing(
      new URLSearchParams({
        collection: collection.slug,
        sort: 'newest',
        per_page: '4',
      }),
    ),
  ]);
  const trending =
    results[0].status === 'fulfilled' ? results[0].value.items : [];
  const newest =
    results[1].status === 'fulfilled' ? results[1].value.items : [];
  const finishing = ['boots', 'lingerie', 'pyjamas']
    .map((s) => categories.find((c) => c.slug.endsWith('-' + s)))
    .filter((c): c is Collection => !!c);
  const root = '/collections/' + collection.slug;
  return (
    <div className="women-landing">
      <CollectionRefresh />
      <section className="women-hero" aria-labelledby="women-title">
        <img
          className="women-hero-image"
          src="/campaigns/women/autumn-hero.png"
          width="2022"
          height="778"
          alt="Two women in textured knitwear and tailored coats beside warm stone architecture"
          fetchPriority="high"
        />
        <div className="women-hero-copy">
          <p className="eyebrow">Women / The new collection</p>
          <h1 id="women-title">
            A season
            <br />
            of possibility.
          </h1>
          <Link href="#women-products" className="women-light-button">
            Shop the collection <Icon name="arrow" size={18} />
          </Link>
        </div>
      </section>
      {!!categories.length && (
        <section
          className="women-section women-categories"
          aria-label="Shop Women by category"
        >
          <h2>Shop by category</h2>
          <div className="women-category-row">
            {categories.map((c) => (
              <Link href={'/collections/' + c.slug} key={c.id}>
                <span className="women-category-circle">
                  <CategoryPhoto category={c} />
                </span>
                <span>{c.title}</span>
              </Link>
            ))}
          </div>
        </section>
      )}
      <section className="women-ribbon">
        <p>Everyday pieces. Endless possibilities.</p>
        <Link href="#women-trending">
          Explore the edit <Icon name="arrow" size={18} />
        </Link>
      </section>
      <section
        className="women-section"
        id="women-trending"
        aria-labelledby="women-trending-title"
      >
        <div className="women-section-heading">
          <h2 id="women-trending-title">Trending now</h2>
          <Link href={root + '?sort=trending#women-products'}>
            Explore all women’s <Icon name="arrow" size={16} />
          </Link>
        </div>
        {results[0].status === 'rejected' ? (
          <Recovery />
        ) : trending.length ? (
          <div className="women-editorial-grid">
            {trending.map((p) => {
              const image = photo(p);
              return (
                <Link
                  href={'/products/' + p.slug}
                  key={p.id}
                  className="women-editorial-card"
                  prefetch={false}
                >
                  <div className="women-editorial-photo">
                    {image ? (
                      <img
                        src={image.url}
                        alt={image.alt || p.title}
                        loading="lazy"
                      />
                    ) : (
                      <span className="women-photo-fallback">{p.title}</span>
                    )}
                  </div>
                  <div className="women-editorial-caption">
                    <span>{p.title}</span>
                    <span>
                      From{' '}
                      {money(
                        p.listing_price_minor ??
                          Math.min(...p.variants.map((v) => v.price_minor)),
                      )}{' '}
                      <Icon name="arrow" size={16} />
                    </span>
                  </div>
                </Link>
              );
            })}
          </div>
        ) : (
          <div className="women-empty">
            <h3>Your next favourites are on their way.</h3>
            <p>Explore our categories while we prepare the collection.</p>
            <Link href="#women-products">Browse the collection →</Link>
          </div>
        )}
      </section>
      {!!categories.length && (
        <section
          className="women-section"
          aria-labelledby="women-refreshed-title"
        >
          <div className="women-section-heading">
            <h2 id="women-refreshed-title">Your wardrobe, refreshed</h2>
            <Link href="#women-products">
              Explore all <Icon name="arrow" size={16} />
            </Link>
          </div>
          <div className="women-mini-grid">
            {categories.slice(0, 6).map((c) => (
              <Link href={'/collections/' + c.slug} key={c.id}>
                <div>
                  <CategoryPhoto category={c} />
                </div>
                <span>{c.title}</span>
              </Link>
            ))}
          </div>
        </section>
      )}
      {!!finishing.length && (
        <section
          className="women-section"
          aria-labelledby="women-finishing-title"
        >
          <div className="women-section-heading">
            <h2 id="women-finishing-title">The finishing touches</h2>
          </div>
          <div className="women-finishing-grid">
            {finishing.map((c) => (
              <article key={c.id}>
                <Link href={'/collections/' + c.slug}>
                  <div className="women-finishing-image">
                    <CategoryPhoto category={c} />
                  </div>
                  <h3>{c.title}</h3>
                </Link>
                <p>
                  {c.description ||
                    'Discover the details that bring your wardrobe together.'}
                </p>
                <Link
                  href={'/collections/' + c.slug}
                  className="women-underlined"
                >
                  Shop {c.title.toLowerCase()} →
                </Link>
              </article>
            ))}
          </div>
        </section>
      )}
      <section className="women-story">
        <div className="women-story-photo">
          <img
            src="/campaigns/women/autumn-hero.png"
            alt="Considered autumn layers in natural daylight"
            loading="lazy"
          />
        </div>
        <div className="women-story-copy">
          <p className="eyebrow">The new collection</p>
          <h2>
            Considered style,
            <br />
            every day.
          </h2>
          <Link className="button button-primary" href="/our-story">
            Discover ELVARQO <Icon name="arrow" size={18} />
          </Link>
        </div>
      </section>
      <section
        className="women-section women-newest"
        aria-labelledby="women-newest-title"
      >
        <div className="women-section-heading">
          <h2 id="women-newest-title">New arrivals</h2>
          <Link href={root + '?sort=newest#women-products'}>
            View all women’s <Icon name="arrow" size={16} />
          </Link>
        </div>
        {results[1].status === 'rejected' ? (
          <Recovery />
        ) : newest.length ? (
          <ProductGrid products={newest} />
        ) : (
          <p className="women-empty">
            New pieces will appear here as the collection arrives.
          </p>
        )}
      </section>
      <section
        id="women-products"
        className="women-all-products"
        aria-label="Shop the Women collection"
      >
        <ListingPage
          path={root}
          title="Shop the collection"
          description={collection.description || undefined}
          collection={collection.slug}
          query={query}
          headingLevel="h2"
        />
      </section>
      <Newsletter />
    </div>
  );
}
