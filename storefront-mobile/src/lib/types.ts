/** Shared Storefront API types (subset used by the mobile app). */

export type ContentLocale = "en" | "ar";

export interface ApiEnvelope<T> {
  success: true;
  data: T;
  meta?: Record<string, unknown>;
}

export interface ApiErrorBody {
  success: false;
  message: string;
  errors?: Record<string, string[]>;
}

export interface AuthContact {
  id: number;
  first_name?: string;
  last_name?: string;
  name?: string;
  email?: string;
  mobile?: string;
}

export interface AuthSession {
  token: string;
  contact: AuthContact;
}

export interface StoreSettings {
  business_name?: string;
  maintenance_mode?: boolean;
  cod_enabled?: boolean;
  theme?: { accent_color?: string };
  online_payments?: {
    enabled?: boolean;
    provider?: string;
    label?: string;
  };
  digital?: { enabled?: boolean };
  promo_codes?: {
    enabled_at_checkout?: boolean;
    allow_stacking?: boolean;
  };
  turnstile?: { enabled?: boolean; site_key?: string };
  sale_badge?: { text?: string };
  catalog?: { show_availability_on_cards?: boolean };
  repair?: { lookup_enabled?: boolean; lookup_by_mobile?: boolean };
  [key: string]: unknown;
}

export interface ProductSummary {
  id: number;
  name: string;
  slug: string;
  image_url?: string | null;
  price_inc_tax?: number;
  storefront_sale_price_inc_tax?: number | null;
  in_stock?: boolean;
  brand?: { id?: number; name?: string; slug?: string };
  rating?: { average?: number; count?: number };
}

export interface ProductDetail extends ProductSummary {
  description?: string;
  images?: string[];
  variations?: Array<{
    id: number;
    name?: string;
    price_inc_tax?: number;
    storefront_sale_price_inc_tax?: number | null;
    in_stock?: boolean;
    qty_available?: number;
  }>;
  related_products?: ProductSummary[];
}

export interface Category {
  id: number;
  name: string;
  slug: string;
  image_url?: string | null;
}

export interface Brand {
  id: number;
  name: string;
  slug: string;
  image_url?: string | null;
}

export interface HomepageSection {
  id: string | number;
  type: string;
  layout_width?: string;
  settings: Record<string, unknown>;
}

export interface CartItemDigital {
  kind: "game" | "card";
  line_key: string;
  title: string;
  price: number;
  game_id?: number;
  type?: "primary" | "secondary";
  platform?: "4" | "5";
  card_category_id?: number;
}

export interface CartItem {
  variationId: number;
  productId: number;
  name: string;
  slug?: string;
  imageUrl?: string | null;
  unitPrice: number;
  quantity: number;
  digital?: CartItemDigital;
}

export interface CartApiItem {
  variation_id: number;
  quantity: number;
  unit_price?: number;
  digital?: CartItemDigital;
}

export interface CheckoutOrder {
  id: number;
  storefront_order_id: string;
  payment_status?: string;
  payment?: FawryPaymentSession;
  [key: string]: unknown;
}

export interface FawryPaymentSession {
  provider: string;
  sdk_url?: string;
  return_url?: string;
  locale?: string;
  charge?: Record<string, unknown>;
  merchant_code?: string;
  merchant_ref_num?: string;
  signature?: string;
  base_url?: string;
}

export interface AccountOrder {
  id: number;
  invoice_no?: string;
  storefront_order_id?: string;
  final_total?: number;
  payment_status?: string;
  status?: string;
  created_at?: string;
}

export interface AccountOrderDetail extends AccountOrder {
  lines?: Array<{
    product_id?: number;
    variation_id?: number;
    name?: string;
    quantity?: number;
    slug?: string;
    image_url?: string | null;
  }>;
  shipping_tracking_number?: string | null;
  shipping_tracking_url?: string | null;
  digital_deliveries?: Array<{
    kind?: string;
    title?: string;
    account_email?: string;
    account_password?: string;
    code?: string;
  }>;
  invoice_print_url?: string | null;
}

export interface StoreLocation {
  id: number;
  name: string;
  address?: string;
  mobile?: string;
  maps_url?: string;
  latitude?: number;
  longitude?: number;
  enable_pickup?: boolean;
  is_selling_location?: boolean;
}

export interface WishlistPayload {
  items: ProductSummary[];
  count: number;
}
