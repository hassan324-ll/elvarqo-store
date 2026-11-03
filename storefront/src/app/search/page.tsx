import ListingPage from '../../components/shop/listing-page';
import type { Query } from '../../lib/catalogue-server';
export const dynamic = 'force-dynamic';
export const metadata = {
  title: 'Search the collection',
  alternates: { canonical: '/shop' },
  robots: { index: false, follow: true },
};
export default async function Search({
  searchParams,
}: {
  searchParams: Promise<Query>;
}) {
  return (
    <ListingPage
      query={await searchParams}
      path="/search"
      title="Search the collection"
      search
    />
  );
}
