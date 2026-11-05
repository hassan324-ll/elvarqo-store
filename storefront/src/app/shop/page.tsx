import ListingPage from '../../components/shop/listing-page';
import { indexEnabled, type Query } from '../../lib/catalogue-server';
export const dynamic = 'force-dynamic';
type Props = { searchParams: Promise<Query> };
export async function generateMetadata({ searchParams }: Props) {
  const query = await searchParams;
  return {
    title: 'Shop the collection',
    alternates: { canonical: '/shop' },
    robots: {
      index: indexEnabled && !Object.values(query).some(Boolean),
      follow: true,
    },
  };
}
export default async function Shop({ searchParams }: Props) {
  return (
    <ListingPage
      query={await searchParams}
      path="/shop"
      title="Everyday essentials"
    />
  );
}
