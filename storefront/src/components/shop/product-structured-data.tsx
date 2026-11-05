import type { Product } from '../../lib/catalogue-types';
import { variantInStock } from '../../lib/catalogue-types';
import {
  productStructuredData,
  safeJsonLd,
} from '../../lib/product-structured-data';

export default function ProductStructuredData({
  product,
  origin,
}: {
  product: Product;
  origin: string;
}) {
  return (
    <script
      id="product-structured-data"
      type="application/ld+json"
      dangerouslySetInnerHTML={{
        __html: safeJsonLd(
          productStructuredData(product, origin, variantInStock),
        ),
      }}
    />
  );
}
