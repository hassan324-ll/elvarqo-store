import Link from 'next/link';
import { collections, indexEnabled } from '../../lib/catalogue-server';
import Recovery from '../../components/shop/recovery';
export const dynamic = 'force-dynamic';
export const metadata = {
  title: 'The collections',
  alternates: { canonical: '/collections' },
  robots: { index: indexEnabled, follow: true },
};
export default async function Collections() {
  let data;
  try {
    data = await collections();
  } catch {
    return (
      <div className="store-section">
        <h1>The collections</h1>
        <Recovery />
      </div>
    );
  }
  return (
    <div className="store-section">
      <header className="page-intro">
        <p className="eyebrow">ELVARQO / COLLECTIONS</p>
        <h1>The collections</h1>
        <p>A wardrobe made to work together.</p>
      </header>
      {data.length ? (
        <div className="live-collection-grid">
          {data.map((c, i) => (
            <Link
              href={'/collections/' + c.slug}
              key={c.id}
              className="live-collection-tile"
              prefetch={false}
            >
              <span className="eyebrow">
                {String(i + 1).padStart(2, '0')} / {c.kind}
              </span>
              <h2>{c.title}</h2>
              <p>{c.description}</p>
              <span className="text-link">Discover collection →</span>
            </Link>
          ))}
        </div>
      ) : (
        <section className="shop-empty">
          <h2>The next collection is taking shape.</h2>
          <p>There are no published collections to explore right now.</p>
          <Link href="/shop" className="text-link">
            Browse all pieces
          </Link>
        </section>
      )}
    </div>
  );
}
