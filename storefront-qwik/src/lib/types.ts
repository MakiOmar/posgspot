/** Storefront API response envelope. */
export interface ApiEnvelope<T> {
  success: boolean;
  data: T;
  meta: Record<string, unknown>;
}

export interface ApiErrorBody {
  success: false;
  message: string;
  errors: Record<string, string[]>;
}

export interface StoreSettings {
  business_name: string;
  logo_url: string | null;
  currency: {
    code: string;
    symbol: string;
    precision: number;
    symbol_placement: "before" | "after";
  };
  contact: {
    phone: string | null;
    /** Base64-encoded business email — decode client-side only (not in SSR mailto/text). */
    email_encoded: string | null;
    whatsapp: string | null;
  };
  social: Record<string, string | null>;
  announcement: {
    message: string;
    link: string;
    enabled: boolean;
  };
  theme: {
    accent_color: string;
  };
  cod_enabled: boolean;
  maintenance_mode: boolean;
  locales: string[];
}

export interface StoreLocation {
  id: number;
  name: string;
  address: string;
  phone: string | null;
  /** Base64-encoded location email — decode client-side only (not in SSR mailto/text). */
  email_encoded: string | null;
  enable_pickup: boolean;
  latitude: number | null;
  longitude: number | null;
  maps_url: string | null;
}

export interface Category {
  id: number;
  name: string;
  slug: string | null;
  sub_categories?: Category[];
}

export interface ProductSummary {
  id: number;
  slug: string | null;
  name: string;
  sku: string;
  type: string;
  image_url: string | null;
  price: number;
  in_stock: boolean;
}

export interface ProductVariation {
  id: number;
  name: string;
  sub_sku: string;
  price: number;
  in_stock: boolean;
  qty_available: number;
  images: string[];
}

export interface ProductDetail {
  id: number;
  slug: string | null;
  name: string;
  sku: string;
  type: string;
  description: string | null;
  brand: { id: number; name: string } | null;
  category: { id: number; name: string; slug: string | null } | null;
  images: string[];
  enable_stock: boolean;
  variations: ProductVariation[];
}

export interface ProductsMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface AvailabilityLocation {
  location_id: number;
  name: string;
  address: string;
  phone: string | null;
  in_stock: boolean;
  qty_available: number;
  latitude: number | null;
  longitude: number | null;
  maps_url: string | null;
}

export interface ProductAvailability {
  product_id: number;
  product_name: string;
  variation_id: number;
  variation_name: string;
  in_stock_count: number;
  cod_available: boolean;
  locations: AvailabilityLocation[];
}

export interface CartLine {
  variation_id: number;
  product_id: number;
  name: string;
  variation_name: string;
  quantity: number;
  unit_price: number;
  line_total: number;
  in_stock: boolean;
}

export interface CartValidation {
  lines: CartLine[];
  subtotal: number;
  shipping: number;
  total: number;
}

export interface CheckoutOrder {
  id: number;
  storefront_order_id: string;
  invoice_no: string;
  status: string;
  payment_status: string;
  final_total: number;
  transaction_date: string;
  shipping_status: string;
}

export interface CartItem {
  productId: number;
  variationId: number;
  slug: string | null;
  name: string;
  variationName: string;
  price: number;
  quantity: number;
  imageUrl: string | null;
}

export interface AuthContact {
  id: number;
  name: string;
  first_name: string | null;
  last_name: string | null;
  email: string | null;
  mobile: string | null;
  address_line_1: string | null;
  address_line_2: string | null;
  city: string | null;
  state: string | null;
  country: string | null;
  zip_code: string | null;
}

export interface AuthSession {
  contact: AuthContact;
  token: string;
  token_type: string;
}

export interface AccountOrder {
  id: number;
  storefront_order_id: string;
  invoice_no: string;
  status: string;
  payment_status: string;
  final_total: number;
  transaction_date: string;
  shipping_status: string;
}

export interface AccountOrderLine {
  product_id: number;
  variation_id: number;
  product_name: string | null;
  variation_name: string | null;
  quantity: number;
  unit_price_inc_tax: number;
  line_total: number;
}

export interface AccountOrderDetail extends AccountOrder {
  lines: AccountOrderLine[];
}
