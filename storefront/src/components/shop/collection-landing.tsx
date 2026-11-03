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
import type { CollectionCampaign } from '../../lib/collection-campaigns';

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
export default async function CollectionLanding({
  collection,
  query,
  campaign,
}: {
  collection: Collection;
  query: Query;
  campaign: CollectionCampaign;
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
  const finishing = campaign.suffixes
    .map((s) => categories.find((c) => c.slug.endsWith('-' + s)))
    .filter((c): c is Collection => !!c);
  const root = '/collections/' + collection.slug;
  return (
    <div
      className={`women-landing collection-landing collection-landing-${collection.slug}`}
    >
      <CollectionRefresh />
      <section
        className="women-hero"
        aria-labelledby={`${collection.slug}-title`}
      >
        <img
          className="women-hero-image"
          src={`/campaigns/${collection.slug}/autumn-hero.png`}
          width={campaign.width}
          height={campaign.height}
          alt={campaign.alt}
          fetchPriority="high"
        />
        <div className="women-hero-copy">
          <p className="eyebrow">{collection.title} / The new collection</p>
          <h1 id={`${collection.slug}-title`}>
            {campaign.headline[0]}
            <br />
            {campaign.headline[1]}
          </h1>
          <Link
            href={`#${collection.slug}-products`}
            className="women-light-button"
          >
            Shop the collection <Icon name="arrow" size={18} />
          </Link>
        </div>
      </section>
      {!!categories.length && (
        <section
          className="women-section women-categories"
          aria-label={`Shop ${collection.title} by category`}
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
        <p>{campaign.ribbon}</p>
        <Link href={`#${collection.slug}-trending`}>
          Explore the edit <Icon name="arrow" size={18} />
        </Link>
      </section>
      <section
        className="women-section"
        id={`${collection.slug}-trending`}
        aria-labelledby={`${collection.slug}-trending-title`}
      >
        <div className="women-section-heading">
          <h2 id={`${collection.slug}-trending-title`}>Trending now</h2>
          <Link href={root + '?sort=trending#' + collection.slug + '-products'}>
            Explore {collection.title.toLowerCase()}{' '}
            <Icon name="arrow" size={16} />
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
            <Link href={`#${collection.slug}-products`}>
              Browse the collection →
            </Link>
          </div>
        )}
      </section>
      {!!categories.length && (
        <section
          className="women-section"
          aria-labelledby={`${collection.slug}-refreshed-title`}
        >
          <div className="women-section-heading">
            <h2 id={`${collection.slug}-refreshed-title`}>
              {campaign.refreshed}
            </h2>
            <Link href={`#${collection.slug}-products`}>
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
          aria-labelledby={`${collection.slug}-finishing-title`}
        >
          <div className="women-section-heading">
            <h2 id={`${collection.slug}-finishing-title`}>
              {campaign.finishing}
            </h2>
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
            src={`/campaigns/${collection.slug}/autumn-hero.png`}
            alt={campaign.alt}
            loading="lazy"
          />
        </div>
        <div className="women-story-copy">
          <p className="eyebrow">The new collection</p>
          <h2>
            {campaign.story[0]}
            <br />
            {campaign.story[1]}
          </h2>
          <Link className="button button-primary" href="/our-story">
            Discover ELVARQO <Icon name="arrow" size={18} />
          </Link>
        </div>
      </section>
      <section
        className="women-section women-newest"
        aria-labelledby={`${collection.slug}-newest-title`}
      >
        <div className="women-section-heading">
          <h2 id={`${collection.slug}-newest-title`}>New arrivals</h2>
          <Link href={root + '?sort=newest#' + collection.slug + '-products'}>
            View all {collection.title.toLowerCase()}{' '}
            <Icon name="arrow" size={16} />
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
        id={`${collection.slug}-products`}
        className="women-all-products"
        aria-label={`Shop the ${collection.title} collection`}
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
