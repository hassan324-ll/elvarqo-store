export type Variant = {
  id?: number;
  sku: string;
  barcode: string | null;
  colour: string;
  size: string;
  price_minor: number;
  compare_at_minor: number | null;
  currency: string;
  weight_grams: number | null;
  low_stock_threshold: number;
  active: boolean;
  measurements: Record<string, number> | null;
  fit: string | null;
  care: string | null;
  available?: number;
  stock_tracked?: boolean;
  is_in_stock?: boolean;
};
export type Measurements = {
  size: string;
  chest_cm: number;
  length_cm: number;
  shoulder_cm: number;
};
export type ProductMedia = {
  id: string;
  variant_id: number | null;
  alt: string;
  position: number;
  url: string;
  width: number;
  height: number;
  sources: { url: string; width: number; height: number }[];
};
export type Collection = {
  image_url?: string | null;
  image_alt?: string | null;
  parent_id?: number | null;
  categories?: Collection[];
  product_count?: number;
  id: number;
  title: string;
  slug: string;
  description: string | null;
  kind: string;
  active: boolean;
  position: number;
  version: number;
};
export type Product = {
  id: number;
  title: string;
  slug: string;
  product_type: string | null;
  description: string | null;
  material: string | null;
  fit: string | null;
  care: string | null;
  seo_title: string | null;
  seo_description: string | null;
  measurements: Measurements[];
  version: number;
  status: string;
  collections: Collection[];
  variants: Variant[];
  media: ProductMedia[];
};
export type Page<T> = {
  items: T[];
  page: number;
  last_page: number;
  total: number;
};
export type Location = {
  id: number;
  name: string;
  code: string;
  active: boolean;
};
export type InventoryRow = {
  stock_tracked?: boolean;
  is_in_stock?: boolean;
  id: number;
  product_id: number;
  title: string;
  sku: string;
  low_stock_threshold: number;
  balances: {
    location_id: number;
    location: string;
    on_hand: number;
    reserved: number;
    available: number;
  }[];
};
export type Movement = {
  id: number;
  operation_id: string;
  variant_id: number;
  location_id: number;
  delta: number;
  on_hand_after: number;
  reserved_after: number;
  staff_user_id: number;
  reason: string;
  reference: string | null;
  note: string | null;
  created_at: string;
};
export const money = (minor: number) =>
  new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(
    minor / 100,
  );
