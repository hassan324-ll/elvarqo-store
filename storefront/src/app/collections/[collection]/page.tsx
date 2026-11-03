import Link from 'next/link';
import { notFound } from 'next/navigation';
import {
  collections,
  navigation,
  indexEnabled,
  type Query,
} from '../../../lib/catalogue-server';
import ListingPage from '../../../components/shop/listing-page';
import Recovery from '../../../components/shop/recovery';
import WomenLanding from '../../../components/shop/women-landing';
import MenLanding from '../../../components/shop/men-landing';
import CollectionLanding from '../../../components/shop/collection-landing';
import { collectionCampaigns } from '../../../lib/collection-campaigns';
type Props = {
  params: Promise<{ collection: string }>;
  searchParams: Promise<Query>;
};
export const dynamic = 'force-dynamic';
export async function generateMetadata({ params, searchParams }: Props) {
  const { collection } = await params;
  const query = await searchParams;
  try {
    const [menu, visible] = await Promise.all([navigation(), collections()]);
    const c = menu
      .flatMap((c) => [c, ...(c.categories || [])])
      .find((c) => c.slug === collection);
    return {
      title: c?.title || 'Collection unavailable',
      alternates: { canonical: '/collections/' + collection },
      robots: {
        index:
          !!c &&
          visible.some((item) => item.slug === collection) &&
          indexEnabled &&
          !Object.values(query).some(Boolean),
        follow: true,
      },
    };
  } catch {
    return {
      title: 'Collection temporarily unavailable',
      robots: { index: false, follow: true },
    };
  }
}
export default async function Collection({ params, searchParams }: Props) {
  const { collection } = await params;
  let all;
  try {
    all = await navigation();
  } catch {
    return (
      <div className="store-section">
        <h1>Collection temporarily unavailable</h1>
        <Recovery />
      </div>
    );
  }
  const c = all
    .flatMap((c) => [c, ...(c.categories || [])])
    .find((c) => c.slug === collection);
  if (!c) notFound();
  const campaign = collectionCampaigns[c.slug];
  if (c.kind === 'collection' && campaign)
    return (
      <CollectionLanding
        collection={c}
        query={await searchParams}
        campaign={campaign}
      />
    );
  if (c.kind === 'collection' && c.slug === 'men')
    return <MenLanding collection={c} query={await searchParams} />;
  if (c.kind === 'collection' && c.slug === 'women')
    return <WomenLanding collection={c} query={await searchParams} />;
  return (
    <>
      {c.kind === 'collection' && !!c.categories?.length && (
        <section
          className="store-category-section"
          aria-label={`Shop ${c.title} by category`}
        >
          <h2>Shop by category</h2>
          <div className="store-category-grid">
            {c.categories.map((category) => (
              <Link key={category.id} href={'/collections/' + category.slug}>
                <span className="store-category-photo">
                  {category.image_url ? (
                    <img
                      src={category.image_url}
                      alt={category.image_alt || category.title}
                    />
                  ) : (
                    <span aria-hidden="true">{category.title.slice(0, 1)}</span>
                  )}
                </span>
                <strong>{category.title}</strong>
              </Link>
            ))}
          </div>
        </section>
      )}
      <ListingPage
        path={'/collections/' + c.slug}
        title={c.title}
        description={c.description || undefined}
        collection={c.slug}
        query={await searchParams}
      />
    </>
  );
}
