import Link from 'next/link';
import { notFound, permanentRedirect } from 'next/navigation';
import {
  product as loadProduct,
  indexEnabled,
  type Query,
} from '../../../lib/catalogue-server';
import ProductSelection from '../../../components/shop/product-selection';
import Recovery from '../../../components/shop/recovery';
type Props = {
  params: Promise<{ product: string }>;
  searchParams: Promise<Query>;
};
export const dynamic = 'force-dynamic';
export async function generateMetadata({ params }: Props) {
  const { product: slug } = await params;
  const data = await loadProduct(slug);
  if (data.missing) notFound();
  if (data.canonical) permanentRedirect('/products/' + data.canonical);
  return {
    title: data.product
      ? { absolute: data.product.seo_title }
      : 'Product temporarily unavailable',
    description: data.product?.seo_description,
    alternates: { canonical: '/products/' + (data.product?.slug || slug) },
    robots: { index: !!data.product && indexEnabled, follow: true },
    openGraph: data.product
      ? {
          title: data.product.seo_title,
          description: data.product.seo_description,
          url: '/products/' + data.product.slug,
        }
      : undefined,
  };
}
export default async function ProductPage({ params, searchParams }: Props) {
  const { product: slug } = await params;
  const data = await loadProduct(slug);
  if (data.missing) notFound();
  if (data.canonical) permanentRedirect('/products/' + data.canonical);
  if (!data.product)
    return (
      <div className="store-section">
        <h1>Product temporarily unavailable</h1>
        <Recovery message={data.error} />
      </div>
    );
  const query = await searchParams;
  const selected =
    typeof query.variant === 'string' && /^\d+$/.test(query.variant)
      ? Number(query.variant)
      : undefined;
  return (
    <div className="store-section live-detail">
      <nav className="product-breadcrumbs" aria-label="Breadcrumb">
        <Link href="/shop">Shop</Link>
        <span>/</span>
        {data.product.collections[0] && (
          <>
            <Link href={'/collections/' + data.product.collections[0].slug}>
              {data.product.collections[0].title}
            </Link>
            <span>/</span>
          </>
        )}
        <span>{data.product.title}</span>
      </nav>
      <ProductSelection initial={data.product} initialVariant={selected} />
      <noscript>
        <p>
          Product information and prices are shown above. Enable JavaScript to
          choose a variant, view the interactive size guide or save a piece.
        </p>
        <table className="size-table">
          <caption>Current variants</caption>
          <thead>
            <tr>
              <th>Colour</th>
              <th>Size</th>
              <th>Price GBP</th>
              <th>Available</th>
            </tr>
          </thead>
          <tbody>
            {data.product.variants.map((v) => (
              <tr key={v.id}>
                <td>{v.colour}</td>
                <td>{v.size}</td>
                <td>{(v.price_minor / 100).toFixed(2)}</td>
                <td>{v.available}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </noscript>
    </div>
  );
}
