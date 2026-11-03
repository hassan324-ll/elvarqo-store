export type BagLine = {
  variant_id: number;
  quantity: number;
  product_id?: number;
  title?: string;
  slug?: string;
  colour?: string;
  size?: string;
  unit_minor?: number;
  line_minor?: number;
  available?: number;
  unavailable: boolean;
};
export type Rate = { name: string; minor: number; estimate: string };
export type Bag = {
  id: string;
  revision: number;
  items: BagLine[];
  active_order_id: string | null;
  rates: Record<string, Rate>;
  test_rates: boolean;
  sandbox_enabled: boolean;
  checkout_enabled: boolean;
  payment_provider: string;
};
export type Address = {
  first_name: string;
  last_name: string;
  line1: string;
  line2: string;
  city: string;
  postcode: string;
  country: 'GB';
};
export type Details = {
  revision: number;
  email: string;
  address: Address;
  billing_address?: Address;
  delivery_method: string;
  discount_code: string;
};
export type Snapshot = {
  items: BagLine[];
  contact: { email: string };
  shipping_address: Address;
  billing_address: Address;
  delivery: Rate & { code: string };
  subtotal_minor: number;
  discount_minor: number;
  shipping_minor: number;
  tax_minor: number;
  total_minor: number;
  currency: string;
  tax_note: string;
  test_rates: boolean;
  discount_code: string;
};
export type Quote = { snapshot: Snapshot; quote_hash: string };
export type BankInstructions = {
  provider: string;
  account_holder: string;
  sort_code: string;
  account_number: string;
  currency: string;
  payment_reference: string;
  amount_minor: number;
  pay_before: string;
};
export type Order = {
  payment_method: string;
  payment_instructions: BankInstructions | null;
  id: string;
  reference: string;
  order_state: string;
  payment_state: string;
  fulfilment_state: string;
  total_minor: number;
  currency: string;
  paid_at: string | null;
  reservation_expires_at: string;
  snapshot: Snapshot;
  checkout_url: string | null;
  recoverable: boolean;
  provider_issue: boolean;
  refresh_unavailable?: boolean;
  mail_status: string;
};
export async function commerce<T>(path: string, body?: unknown): Promise<T> {
  const response = await fetch('/api/commerce/' + path, {
    method: body === undefined ? 'GET' : 'POST',
    cache: 'no-store',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      ...(body === undefined ? {} : { 'Content-Type': 'application/json' }),
    },
    ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    signal: AbortSignal.timeout(35000),
  });
  const result = await response.json();
  if (!response.ok) {
    const detail = Object.values(result.error?.details || {})
      .flat()
      .join(' ');
    throw new Error(
      detail || result.error?.message || 'Checkout could not be completed.',
    );
  }
  return result.data as T;
}
