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
  digital?: { enabled?: boolean; primary_product_id?: number };
  promo_codes?: {
    enabled_at_checkout?: boolean;
    allow_stacking?: boolean;
  };
  reward_points?: { enabled?: boolean; name?: string };
  turnstile?: { enabled?: boolean; site_key?: string };
  sale_badge?: { text?: string };
  catalog?: { show_availability_on_cards?: boolean };
  repair?: { lookup_enabled?: boolean; lookup_by_mobile?: boolean };
  [key: string]: unknown;
}

export interface ProductSummary {
  id: number;
  name: string;
  slug: string | null;
  image_url?: string | null;
  /** Catalog list price (Storefront API). */
  price?: number;
  compare_at_price?: number | null;
  on_sale?: boolean;
  sale_percent?: number;
  /** Legacy / detail aliases kept for safety. */
  price_inc_tax?: number;
  storefront_sale_price_inc_tax?: number | null;
  in_stock?: boolean;
  variation_id?: number | null;
  variation_name?: string | null;
  has_options?: boolean;
  brand?: { id?: number; name?: string; slug?: string };
  rating_average?: number;
  rating_count?: number;
  rating?: { average?: number; count?: number };
}

export interface HomepageHeroSlide {
  id: string;
  image_url: string;
  href: string;
  kicker?: string;
  title?: string;
}

export interface HomepagePromoTile {
  id: string;
  image_url: string;
  href: string;
  label?: string;
}

export interface HomepageCategoryShelf {
  id?: number | string;
  name?: string;
  slug?: string;
  heading?: string;
  banner_image_url?: string | null;
  banner_fg_image_url?: string | null;
  banner_kicker?: string;
  banner_text?: string;
  button_text?: string;
  banner_link?: string;
  view_more_path?: string;
  view_more_label?: string;
}

export interface ProductDetail extends ProductSummary {
  description?: string | null;
  images?: string[];
  variations?: Array<{
    id: number;
    name?: string;
    price?: number;
    price_inc_tax?: number;
    storefront_sale_price_inc_tax?: number | null;
    in_stock?: boolean;
    qty_available?: number;
    images?: string[];
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

export interface AccountOrderLine {
  product_id: number;
  variation_id: number;
  product_name?: string | null;
  variation_name?: string | null;
  name?: string;
  slug?: string | null;
  image_url?: string | null;
  quantity: number;
  unit_price_inc_tax?: number;
  line_total?: number;
}

export interface AccountOrderDetail extends AccountOrder {
  lines?: AccountOrderLine[];
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
  subtotal?: number;
  shipping_charges?: number;
  discount_amount?: number;
}

export interface DigitalPosSku {
  product_id: number;
  variation_id: number;
  image_url?: string | null;
}

export interface DigitalSkus {
  primary?: DigitalPosSku | null;
  secondary?: DigitalPosSku | null;
  gift_card?: DigitalPosSku | null;
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

export interface ProductAvailabilityLocation {
  id?: number;
  name: string;
  address?: string;
  in_stock?: boolean;
  qty_available?: number;
}

export interface ProductAvailability {
  product_id?: number;
  variation_id?: number;
  locations: ProductAvailabilityLocation[];
  in_stock_count?: number;
  cod_available?: boolean;
}

export interface ProductReviewItem {
  id: number;
  rating: number;
  title?: string | null;
  body?: string | null;
  author_name?: string | null;
  created_at?: string | null;
  status?: string;
}

export interface ReviewEligibility {
  can_review: boolean;
  reason?: string | null;
  message?: string | null;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface ShippingRate {
  id: string;
  name?: string;
  label?: string;
  method_type?: string;
  amount?: number;
  price?: number;
}

export interface CartValidationResult {
  shipping_rate_id?: string;
  available_rates?: ShippingRate[];
  digital_only?: boolean;
  location_id?: number;
  subtotal?: number;
  shipping?: number;
  discount?: number;
  coupon_discount?: number;
  total?: number;
  items?: Array<{
    variation_id: number;
    unit_price?: number;
    quantity?: number;
    in_stock?: boolean;
    name?: string;
    error?: string;
  }>;
  errors?: string[];
  message?: string;
}

export interface RewardPointsBalance {
  balance?: number;
  points?: number;
  available?: number;
  [key: string]: unknown;
}
