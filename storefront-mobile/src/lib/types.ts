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
  email_verified?: boolean;
  delete_requested?: boolean;
  address_line_1?: string;
  address_line_2?: string;
  country?: string;
  state?: string;
  city?: string;
  zip_code?: string;
}

export interface AuthSession {
  token: string;
  token_type?: string;
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
  couriers?: { bosta?: { enabled?: boolean } };
  turnstile?: { enabled?: boolean; site_key?: string };
  sale_badge?: { text?: string };
  catalog?: { show_availability_on_cards?: boolean };
  repair?: { lookup_enabled?: boolean; lookup_by_mobile?: boolean };
  banners?: Array<{
    id?: string | number;
    placement?: string;
    title?: string;
    link?: string;
    image_url?: string;
    category_slug?: string;
  }>;
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
  banner_kicker?: string | null;
  banner_text?: string | null;
  button_text?: string | null;
  banner_link?: string;
  view_more_path?: string;
  view_more_label?: string | null;
}

export interface HomepageTrustBadge {
  id?: string;
  icon_kind?: "image" | "svg";
  icon_url?: string | null;
  icon_color?: string;
  title?: string;
  description?: string;
}

export interface HomepagePromoBanner {
  logo_url?: string | null;
  top_title?: string;
  main_title?: string;
  top_title_color?: string;
  main_title_color?: string;
  background_color?: string;
  border_radius?: number;
  border_color?: string;
  border_thickness?: number;
  min_height?: number;
  image_url?: string | null;
  button?: {
    label?: string;
    link?: string;
    background_color?: string;
    text_color?: string;
    border_radius?: number;
    show_arrow?: boolean;
    arrow_color?: string;
  };
}

export interface SiteBanner {
  id?: string | number;
  placement?: string;
  title?: string;
  link?: string;
  image_url?: string;
  category_slug?: string;
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

export interface PhoneCountry {
  name_en: string;
  name_ar?: string;
  dial_code: string;
  flag?: string;
  country_code?: string;
  validation_pattern?: string;
}

export interface AccountOrder {
  id: number;
  invoice_no?: string;
  storefront_order_id?: string;
  final_total?: number;
  payment_status?: string;
  status?: string;
  created_at?: string;
  transaction_date?: string;
  invoice_print_url?: string | null;
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
  is_verified_purchase?: boolean;
}

export interface ReviewEligibility {
  can_review: boolean;
  reason?: string | null;
  message?: string | null;
  already_reviewed?: boolean;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface AppliedCouponInfo {
  id?: number;
  code: string;
  label?: string;
  type?: string;
  stack_with_reward_points?: boolean;
}

export interface AvailableCouponInfo {
  id: number;
  code: string;
  name: string;
  label: string;
  type: string;
  description: string | null;
  discount_amount: number;
  free_shipping: boolean;
  shipping_savings: number;
  total_savings: number;
}

export interface AvailableCouponsResult {
  coupons: AvailableCouponInfo[];
}

export interface ShippingRate {
  id: string;
  name?: string;
  label?: string;
  title?: string;
  method_type?: string;
  amount?: number;
  price?: number;
  eta_label?: string | null;
  meta?: Record<string, unknown>;
}

export interface GeoCountry {
  code: string;
  name: string;
}

export interface GeoState {
  code: string;
  name: string;
}

export interface BostaDistrict {
  id: string;
  label: string;
  zone: string | null;
}

export interface BostaDistrictsResponse {
  city_code: string | null;
  city_name: string | null;
  districts: BostaDistrict[];
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
  enabled?: boolean;
  name?: string;
  balance?: number;
  points?: number;
  available?: number;
  used?: number;
  expired?: number;
  value?: number;
  max_redeem_points?: number;
  amount_per_point?: number;
  min_redeem_points?: number;
  max_redeem_points_limit?: number;
  min_order_total_for_redeem?: number;
}

export interface RewardPointsValidation {
  is_valid: boolean;
  message: string | null;
  requested_points: number;
  redeem_amount: number;
  available_points: number;
  max_points: number;
  amount_per_point: number;
  min_redeem_points: number;
  max_redeem_points_limit: number;
  min_order_total_for_redeem: number;
  order_total: number;
}
