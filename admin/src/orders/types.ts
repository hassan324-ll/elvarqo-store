export type Row = {
  id: string;
  reference: string;
  order_state: string;
  payment_state: string;
  fulfilment_state: string;
  total_minor: number;
  currency: string;
  created_at: string;
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
export type Line = {
  variant_id: number;
  title: string;
  sku: string;
  colour: string;
  size: string;
  quantity: number;
  unit_minor: number;
  line_minor: number;
};
export type Snapshot = {
  items: Line[];
  contact: { email: string };
  shipping_address: Address;
  billing_address: Address;
  subtotal_minor: number;
  discount_minor: number;
  shipping_minor: number;
  tax_minor: number;
  total_minor: number;
  delivery: { name: string };
  tax_note: string;
};
export type Shipment = {
  id: string;
  order_id: string;
  state: string;
  service: string;
  cost_minor: number;
  location_id: number;
  provider_reference: string | null;
  tracking_number: string | null;
  last_error: string | null;
  dispatched_at: string | null;
  label_available: boolean;
  sandbox: boolean;
  items: { order_item_id: number; quantity: number }[];
  parcel: {
    weight_grams: number;
    length_cm: number;
    width_cm: number;
    height_cm: number;
    instructions: string;
  };
  attempts: {
    id: string;
    action: string;
    outcome: string;
    provider_reference: string | null;
    created_at: string;
  }[];
  events: {
    id: string;
    state: string;
    applied: boolean;
    occurred_at: string;
  }[];
};
export type Detail = Row & {
  payment_method: string;
  wise_confirmation: {
    confirmed_at: string;
    confirmed_by: string;
    receipt_reference: string | null;
  } | null;
  snapshot: Snapshot;
  remaining: {
    order_item_id: number;
    variant_id: number;
    title: string;
    sku: string;
    colour: string;
    size: string;
    ordered: number;
    allocated: number;
    remaining: number;
  }[];
  funded_exchange: boolean;
  shipments: Shipment[];
  notes: { id: string; body: string; staff_name: string; created_at: string }[];
  support_requests: {
    id: string;
    subject: string;
    message: string;
    state: string;
    created_at: string;
  }[];
  notifications: {
    id: string;
    kind: string;
    delivered_at: string | null;
    created_at: string;
  }[];
  manual: boolean;
  locations: { id: number; name: string; code: string }[];
  courier: {
    provider: string;
    origin_note: string;
    services: Record<string, { name: string; cost_minor: number }>;
  };
  reservations: {
    id: number;
    variant_id: number;
    balance_id: number;
    quantity: number;
    state: string;
  }[];
  stock_events: {
    id: number;
    balance_id: number;
    kind: string;
    on_hand_delta: number;
    reserved_delta: number;
  }[];
  mail_status: string;
};
export type Quote = {
  id: string;
  cost_minor: number;
  currency: string;
  expires_at: string;
  warning: string;
  payload: {
    items: { order_item_id: number; quantity: number }[];
    location_name: string;
    service_name: string;
    destination: Address;
    parcel: {
      weight_grams: number;
      length_cm: number;
      width_cm: number;
      height_cm: number;
    };
    instructions: string;
  };
};
export const money = (n: number) =>
  new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(
    n / 100,
  );
