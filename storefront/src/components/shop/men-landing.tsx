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
export default async function MenLanding({
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
  const styled = [
    'coats-jackets',
    'suits',
    't-shirts-polos',
    'knitwear',
    'footwear',
    'jeans',
  ]
    .map((s) => categories.find((c) => c.slug.endsWith('-' + s)))
    .filter((c): c is Collection => !!c);
  const wear = [
    {
      suffix: 'shirts',
      title: 'Modern workwear',
      description:
        'Refined pieces for a more considered approach to the working day.',
    },
    {
      suffix: 'knitwear',
      title: 'Autumn essentials',
      description: 'Layering staples in rich textures and effortless tones.',
    },
    {
      suffix: 'suits',
      title: 'Occasionwear',
      description: 'Modern tailoring for special moments.',
    },
  ]
    .map((edit) => ({
      ...edit,
      category: categories.find((c) => c.slug.endsWith('-' + edit.suffix)),
    }))
    .filter(
      (edit): edit is typeof edit & { category: Collection } => !!edit.category,
    );
  const root = '/collections/' + collection.slug;
  return (
    <div className="women-landing men-landing">
      <CollectionRefresh />
      <section className="women-hero" aria-labelledby="men-title">
        <img
          className="women-hero-image"
          src="/campaigns/men/autumn-hero.png"
          width="2022"
          height="778"
          alt="Man in a charcoal textured coat and blue shirt beside warm stone architecture"
          fetchPriority="high"
        />
        <div className="women-hero-copy">
          <p className="eyebrow">Men / The new collection</p>
          <h1 id="men-title">
            A new season.
            <br />
            Your own style.
          </h1>
          <Link href="#men-products" className="women-light-button">
            Shop the collection <Icon name="arrow" size={18} />
          </Link>
        </div>
      </section>
      {!!categories.length && (
        <section
          className="women-section women-categories"
          aria-label="Shop Men by category"
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
        <p>Considered essentials. Effortless every day.</p>
        <Link href="#men-trending">
          Explore the edit <Icon name="arrow" size={18} />
        </Link>
      </section>
      {!!styled.length && (
        <section
          className="women-section"
          aria-labelledby="men-refreshed-title"
        >
          <div className="women-section-heading">
            <h2 id="men-refreshed-title">Styled for you</h2>
            <Link href="#men-products">
              Explore all <Icon name="arrow" size={16} />
            </Link>
          </div>
          <div className="women-mini-grid">
            {styled.map((c) => (
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
      <section
        className="women-section"
        id="men-trending"
        aria-labelledby="men-trending-title"
      >
        <div className="women-section-heading">
          <h2 id="men-trending-title">Trending now</h2>
          <Link href={root + '?sort=trending#men-products'}>
            Explore all men’s <Icon name="arrow" size={16} />
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
            <Link href="#men-products">Browse the collection →</Link>
          </div>
        )}
      </section>
      <section className="women-story">
        <div className="women-story-photo">
          <img
            src="/campaigns/men/style-triptych.png"
            alt="Three men wearing olive layers, navy knitwear and a textured coat"
            loading="lazy"
          />
        </div>
        <div className="women-story-copy">
          <p className="eyebrow">The new collection</p>
          <h2>
            Style that
            <br />
            goes further.
          </h2>
          <Link className="button button-primary" href="/our-story">
            Discover ELVARQO <Icon name="arrow" size={18} />
          </Link>
        </div>
      </section>
      {!!wear.length && (
        <section
          className="women-section"
          aria-labelledby="men-finishing-title"
        >
          <div className="women-section-heading">
            <h2 id="men-finishing-title">What to wear</h2>
          </div>
          <div className="women-finishing-grid">
            {wear.map((edit) => (
              <article key={edit.category.id}>
                <Link href={'/collections/' + edit.category.slug}>
                  <div className="women-finishing-image">
                    <CategoryPhoto category={edit.category} />
                  </div>
                  <h3>{edit.title}</h3>
                </Link>
                <p>{edit.description}</p>
                <Link
                  href={'/collections/' + edit.category.slug}
                  className="women-underlined"
                >
                  Shop {edit.category.title.toLowerCase()} →
                </Link>
              </article>
            ))}
          </div>
        </section>
      )}
      <section
        className="women-section women-newest"
        aria-labelledby="men-newest-title"
      >
        <div className="women-section-heading">
          <h2 id="men-newest-title">New arrivals</h2>
          <Link href={root + '?sort=newest#men-products'}>
            View all men’s <Icon name="arrow" size={16} />
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
      <section className="women-story men-repeat">
        <div className="women-story-photo">
          <img
            src="/campaigns/men/autumn-hero.png"
            alt="Textured menswear layers in warm autumn daylight"
            loading="lazy"
          />
        </div>
        <div className="women-story-copy">
          <p className="eyebrow">Considered essentials</p>
          <h2>
            Good pieces.
            <br />
            On repeat.
          </h2>
          <Link href="#men-products" className="button button-primary">
            Explore the collection <Icon name="arrow" size={18} />
          </Link>
        </div>
      </section>
      <section
        id="men-products"
        className="women-all-products"
        aria-label="Shop the Men collection"
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
