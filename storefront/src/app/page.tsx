import Link from 'next/link';
import { Icon } from '../ui/components';
import { collections, listing, indexEnabled } from '../lib/catalogue-server';
import type { Collection, Product } from '../lib/catalogue-types';
import ProductGrid from '../components/shop/product-grid';
import Recovery from '../components/shop/recovery';
import Newsletter from '../components/newsletter';
export const dynamic = 'force-dynamic';
export const metadata = {
  alternates: { canonical: '/' },
  robots: { index: indexEnabled, follow: true },
};
export default async function Home() {
  let all: Collection[] = [];
  let products: Product[] = [];
  let failed = false;
  try {
    [all, products] = await Promise.all([
      collections(),
      listing(new URLSearchParams({ per_page: '6', sort: 'featured' })).then(
        (r) => r.items,
      ),
    ]);
  } catch {
    failed = true;
  }
  return (
    <>
      <section className="campaign" aria-labelledby="campaign-title">
        <img
          className="campaign-image"
          src="/concept/campaign.jpg"
          alt="Concept campaign showing neutral everyday layers beside warm stone architecture"
          width="1672"
          height="941"
          fetchPriority="high"
        />
        <div className="campaign-content">
          <p className="eyebrow">ELVARQO / THE COLLECTION</p>
          <h1 id="campaign-title">
            Less effort.
            <br />
            More presence.
          </h1>
          <p>Considered essentials for wherever the day takes you.</p>
          <Link href="/shop" className="button button-primary">
            Explore the collection <Icon name="arrow" size={18} />
          </Link>
        </div>
        <span className="campaign-credit">
          Design concept / campaign imagery
        </span>
      </section>
      <section className="store-section">
        <div className="section-heading">
          <h2>Built around your day.</h2>
          <Link href="/collections" className="text-link">
            The collections →
          </Link>
        </div>
        {all.length > 0 && (
          <div className="live-collection-grid">
            {all.slice(0, 6).map((c) => (
              <Link
                key={c.id}
                href={'/collections/' + c.slug}
                className="live-collection-tile"
                prefetch={false}
              >
                <p className="eyebrow">{c.kind}</p>
                <h3>{c.title}</h3>
                <span>Explore collection →</span>
              </Link>
            ))}
          </div>
        )}
      </section>
      <section className="store-section essentials">
        <div className="section-heading">
          <div>
            <p className="eyebrow">THE EVERYDAY EDIT</p>
            <h2>The everyday essentials</h2>
            <p>A focused edit. Easy to wear, easy to combine.</p>
          </div>
          <Link href="/shop" className="text-link">
            Explore all pieces <Icon name="arrow" size={18} />
          </Link>
        </div>
        {failed ? (
          <Recovery />
        ) : products.length ? (
          <ProductGrid products={products} />
        ) : (
          <section className="shop-empty">
            <h3>New pieces are on their way.</h3>
            <p>There are no published products to explore right now.</p>
            <Link href="/collections" className="text-link">
              View the collections
            </Link>
          </section>
        )}
      </section>
      <section className="brand-note">
        <p className="eyebrow">A CONSIDERED POINT OF VIEW</p>
        <h2>
          Wear it often.
          <br />
          Make it yours.
        </h2>
        <p>
          Simple silhouettes. Considered colours. Pieces that work together.
        </p>
        <Link className="text-link" href="/our-story">
          Discover our direction <Icon name="arrow" size={18} />
        </Link>
      </section>
      <Newsletter />
    </>
  );
}
