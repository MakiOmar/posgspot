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
  sale_badge: {
    mode: "percent" | "text";
    text: string;
  };
  catalog: {
    /** Out-of-stock listing cards show "Check store availability" when true */
    show_availability_on_cards: boolean;
  };
  cod_enabled: boolean;
  maintenance_mode: boolean;
  online_payments: {
    enabled: boolean;
    provider: string | null;
    label: string | null;
  };
  reward_points: {
    enabled: boolean;
    name: string;
  };
  promo_codes: {
    enabled_at_checkout: boolean;
    allow_stacking: boolean;
  };
  /** Footer payment method icons from storefront settings. */
  payment_icons: Array<{
    label: string;
    icon_url: string;
  }>;
  /** Homepage / category promotional banners. */
  banners: PromoBanner[];
  newsletter: {
    enabled: boolean;
  };
  turnstile: {
    enabled: boolean;
    site_key: string | null;
  };
  locales: string[];
}

export interface PromoBanner {
  id: string;
  placement: "home" | "category";
  category_slug: string | null;
  title: string;
  link: string;
  image_url: string;
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

export interface Brand {
  id: number;
  name: string;
  slug: string;
}

export interface ProductSummary {
  id: number;
  slug: string | null;
  name: string;
  sku: string;
  type: string;
  image_url: string | null;
  variation_id: number | null;
  variation_name: string | null;
  has_options: boolean;
  price: number;
  compare_at_price: number | null;
  on_sale: boolean;
  sale_percent: number;
  in_stock: boolean;
  rating_average?: number;
  rating_count?: number;
}

export interface ProductVariation {
  id: number;
  name: string;
  sub_sku: string;
  price: number;
  compare_at_price?: number | null;
  on_sale?: boolean;
  sale_percent?: number;
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
  brand: { id: number; name: string; slug?: string | null } | null;
  category: { id: number; name: string; slug: string | null } | null;
  images: string[];
  enable_stock: boolean;
  variations: ProductVariation[];
  rating?: { average: number; count: number };
  /** Same category / brand summaries for PDP upsell (catalog ProductSummary shape). */
  related_products?: ProductSummary[];
}

export interface ProductReviewItem {
  id: number;
  rating: number;
  title: string | null;
  body: string;
  is_verified_purchase: boolean;
  author_name: string;
  created_at: string | null;
  moderated_at: string | null;
}

export interface ReviewEligibility {
  can_review: boolean;
  already_reviewed: boolean;
  reason: string | null;
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

export interface AppliedCouponInfo {
  id: number;
  code: string;
  label: string;
  type: string;
  stack_with_reward_points: boolean;
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

export interface CouponApplyResult {
  coupon: AppliedCouponInfo | null;
  coupons?: AppliedCouponInfo[];
  coupon_id: number | null;
  coupon_discount: number;
  free_shipping: boolean;
  eligible_subtotal: number;
  subtotal: number;
  shipping: number;
  total: number;
  stack_with_reward_points: boolean;
}

export interface CartValidation {
  lines: CartLine[];
  subtotal: number;
  shipping: number;
  total: number;
  coupon?: AppliedCouponInfo | null;
  coupons?: AppliedCouponInfo[];
  coupon_discount?: number;
  eligible_subtotal?: number;
  stack_with_reward_points?: boolean;
}

export interface CartLineStatus {
  variation_id: number;
  requested_quantity: number;
  max_quantity: number | null;
  unit_price: number;
  name: string;
  variation_name: string;
  available: boolean;
  stock_tracked: boolean;
}

export interface CartInspection {
  line_status: CartLineStatus[];
  lines: CartLine[];
  subtotal: number;
  shipping: number;
  total: number;
  coupon?: AppliedCouponInfo | null;
  coupons?: AppliedCouponInfo[];
  coupon_discount?: number;
  eligible_subtotal?: number;
  stack_with_reward_points?: boolean;
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
  payment?: FawryPaymentSession;
}

export interface FawryChargeItem {
  itemId: string | number;
  description: string;
  quantity: number;
  price: string;
}

export interface FawryPaymentSession {
  provider: string;
  sdk_url: string;
  return_url: string;
  locale: string;
  charge: {
    merchantCode: string;
    merchantRefNum: string;
    customerProfileId: string;
    customerMobile: string;
    customerEmail: string;
    customerName: string;
    chargeItems: FawryChargeItem[];
    paymentExpiry: string;
    returnUrl: string;
    signature: string;
  };
  customer: {
    customerName: string;
    customerMobile: string;
    customerEmail: string;
    customerId: string;
  };
}

export interface PaymentReturnResult {
  payment_status: string;
  message: string | null;
  order: CheckoutOrder;
  reference_number: string | null;
  fawry_ref_number: string | null;
  payment_method: string | null;
  expiration_time: string | null;
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
  slug?: string | null;
  image_url?: string | null;
  quantity: number;
  unit_price_inc_tax: number;
  line_total: number;
}

export interface OrderShippingAddress {
  address_line_1: string | null;
  address_line_2: string | null;
  city: string | null;
  state: string | null;
  country: string | null;
  zip_code: string | null;
  formatted: string | null;
}

export interface AccountOrderDetail extends AccountOrder {
  lines: AccountOrderLine[];
  shipping_address: OrderShippingAddress | null;
  fulfillment_location: string | null;
  /** POS invoice page with auto-print; only when payment_status is paid */
  invoice_print_url: string | null;
  subtotal?: number;
  discount_amount?: number;
  discount_type?: string | null;
  shipping_charges?: number;
  coupon_code?: string | null;
}

export interface RewardPointsBalance {
  enabled: boolean;
  name: string;
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

export interface WishlistPayload {
  items: ProductSummary[];
  count: number;
}
