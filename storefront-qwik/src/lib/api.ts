/**
 * Storefront API client for the Laravel POS backend.
 */
import type {
  AccountOrder,
  AccountOrderDetail,
  ApiEnvelope,
  ApiErrorBody,
  AuthContact,
  AuthSession,
  AvailabilityLocation,
  CartInspection,
  CartValidation,
  Category,
  CheckoutOrder,
  FawryPaymentSession,
  PaymentReturnResult,
  ProductAvailability,
  ProductDetail,
  ProductSummary,
  ProductsMeta,
  RewardPointsBalance,
  RewardPointsValidation,
  StoreLocation,
  StoreSettings,
  WishlistPayload,
} from "./types";

export const API_BASE: string =
  (import.meta.env.PUBLIC_API_BASE as string | undefined)?.replace(/\/$/, "") ||
  "http://localhost:8000";

const PREFIX = "/api/storefront/v1";

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errors: Record<string, string[]> = {},
  ) {
    super(message);
    this.name = "ApiError";
  }
}

type FetchResult<T> = { data: T; meta: Record<string, unknown> };

let activeContentLocale = "en";

/** Set the locale sent as X-Content-Locale on storefront API requests. */
export function setActiveContentLocale(locale: string): void {
  if (locale === "en" || locale === "ar") {
    activeContentLocale = locale;
  }
}

export function getActiveContentLocale(): string {
  return activeContentLocale;
}

/** Perform a JSON request against the Storefront API envelope. */
export async function storefrontFetch<T>(
  path: string,
  options: RequestInit = {},
  locale?: string,
): Promise<FetchResult<T>> {
  const url = `${API_BASE}${PREFIX}${path}`;
  const contentLocale = locale ?? activeContentLocale;
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Content-Locale": contentLocale,
    ...(options.headers as Record<string, string> | undefined),
  };

  if (options.body && !headers["Content-Type"]) {
    headers["Content-Type"] = "application/json";
  }

  const response = await fetch(url, {
    credentials: "include",
    ...options,
    headers,
  });

  const json = (await response.json()) as ApiEnvelope<T> | ApiErrorBody;

  if (!response.ok || !json.success) {
    const err = json as ApiErrorBody;
    throw new ApiError(
      response.status,
      err.message || `API ${response.status}`,
      err.errors || {},
    );
  }

  return { data: json.data, meta: json.meta || {} };
}

export function fetchSettings(locale?: string) {
  return storefrontFetch<StoreSettings>("/settings", {}, locale);
}

export function fetchLocations(locale?: string) {
  return storefrontFetch<StoreLocation[]>("/locations", {}, locale);
}

export function fetchCategories(locale?: string) {
  return storefrontFetch<Category[]>("/categories", {}, locale);
}

export function fetchCategory(slug: string, locale?: string) {
  return storefrontFetch<Category>(`/categories/${encodeURIComponent(slug)}`, {}, locale);
}

export function fetchProducts(
  params: Record<string, string | number | boolean> = {},
  locale?: string,
) {
  const qs = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== "" && value !== undefined) {
      qs.set(key, String(value));
    }
  }
  const query = qs.toString();
  return storefrontFetch<ProductSummary[]>(`/products${query ? `?${query}` : ""}`, {}, locale);
}

export async function fetchProductsPage(
  params: Record<string, string | number | boolean> = {},
  locale?: string,
) {
  const qs = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== "" && value !== undefined) {
      qs.set(key, String(value));
    }
  }
  const contentLocale = locale ?? activeContentLocale;
  const url = `${API_BASE}${PREFIX}/products?${qs.toString()}`;
  const response = await fetch(url, {
    credentials: "include",
    headers: {
      Accept: "application/json",
      "X-Content-Locale": contentLocale,
    },
  });
  const json = (await response.json()) as ApiEnvelope<ProductSummary[]>;
  if (!response.ok || !json.success) {
    throw new ApiError(response.status, "Failed to load products");
  }
  return {
    data: json.data,
    meta: json.meta as unknown as ProductsMeta,
  };
}

export function fetchProduct(idOrSlug: string, locale?: string) {
  return storefrontFetch<ProductDetail>(`/products/${encodeURIComponent(idOrSlug)}`, {}, locale);
}

export function fetchAvailability(productId: number, variationId?: number, locale?: string) {
  const qs = variationId ? `?variation_id=${variationId}` : "";
  return storefrontFetch<ProductAvailability>(`/products/${productId}/availability${qs}`, {}, locale);
}

export function searchProducts(q: string, limit = 8, locale?: string) {
  return storefrontFetch<ProductSummary[]>(
    `/search?q=${encodeURIComponent(q)}&limit=${limit}`,
    {},
    locale,
  );
}

export function validateCart(payload: {
  location_id?: number;
  items: { variation_id: number; quantity: number }[];
}) {
  return storefrontFetch<CartValidation>("/cart/validate", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

/** Inspect cart lines — returns per-line max quantity without failing on partial stock. */
export function inspectCart(payload: {
  location_id?: number;
  items: { variation_id: number; quantity: number }[];
}) {
  return storefrontFetch<CartInspection>("/cart/validate", {
    method: "POST",
    body: JSON.stringify({ ...payload, resolve: true }),
  });
}

export function checkout(payload: Record<string, unknown>, token?: string) {
  const headers: Record<string, string> = {};
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }
  return storefrontFetch<CheckoutOrder>("/checkout", {
    method: "POST",
    headers,
    body: JSON.stringify(payload),
  });
}

export function fetchPaymentSession(provider: string, storefrontOrderId: string, locale: string) {
  return storefrontFetch<FawryPaymentSession | { already_paid: boolean; order: CheckoutOrder }>(
    `/payments/${provider}/session`,
    {
      method: "POST",
      body: JSON.stringify({ storefront_order_id: storefrontOrderId, locale }),
    },
  );
}

export function confirmPaymentReturn(provider: string, payload: Record<string, unknown>) {
  return storefrontFetch<PaymentReturnResult>(`/payments/${provider}/return`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

/** Authorization header helper for token-protected endpoints. */
function authHeaders(token: string): Record<string, string> {
  return { Authorization: `Bearer ${token}` };
}

export function registerCustomer(payload: {
  first_name: string;
  last_name?: string;
  email: string;
  mobile: string;
  password: string;
  password_confirmation: string;
  dial_code?: string;
}) {
  return storefrontFetch<AuthSession>("/auth/register", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export function loginCustomer(payload: { login: string; password: string }) {
  return storefrontFetch<AuthSession>("/auth/login", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export function logoutCustomer(token: string) {
  return storefrontFetch<{ message: string }>("/auth/logout", {
    method: "POST",
    headers: authHeaders(token),
  });
}

export function forgotPassword(email: string) {
  return storefrontFetch<{ message: string }>("/auth/forgot-password", {
    method: "POST",
    body: JSON.stringify({ email }),
  });
}

export function resetPassword(payload: {
  email: string;
  token: string;
  password: string;
  password_confirmation: string;
}) {
  return storefrontFetch<{ message: string }>("/auth/reset-password", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export function fetchProfile(token: string) {
  return storefrontFetch<AuthContact>("/account/profile", {
    headers: authHeaders(token),
  });
}

export function updateProfile(
  token: string,
  payload: { first_name?: string; last_name?: string; email?: string; mobile?: string },
) {
  return storefrontFetch<AuthContact>("/account/profile", {
    method: "PUT",
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });
}

export function updateAddress(
  token: string,
  payload: {
    address_line_1?: string;
    address_line_2?: string;
    city?: string;
    state?: string;
    country?: string;
    zip_code?: string;
  },
) {
  return storefrontFetch<AuthContact>("/account/address", {
    method: "PUT",
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });
}

export function fetchOrders(token: string) {
  return storefrontFetch<AccountOrder[]>("/account/orders", {
    headers: authHeaders(token),
  });
}

export function fetchOrder(token: string, orderId: number) {
  return storefrontFetch<AccountOrderDetail>(`/account/orders/${orderId}`, {
    headers: authHeaders(token),
  });
}

export function fetchOrderInvoiceUrl(token: string, orderId: number) {
  return storefrontFetch<{ invoice_print_url: string }>(`/account/orders/${orderId}/invoice`, {
    headers: authHeaders(token),
  });
}

export function fetchRewardPoints(token: string) {
  return storefrontFetch<RewardPointsBalance>("/account/reward-points", {
    headers: authHeaders(token),
  });
}

export function validateRewardPoints(
  token: string,
  payload: { requested_points: number; order_total: number },
) {
  return storefrontFetch<RewardPointsValidation>("/account/reward-points/validate", {
    method: "POST",
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });
}

/** Health-check (connectivity / CORS). */
export function pingApi() {
  return storefrontFetch<{
    status: string;
    service: string;
    version: string;
    time: string;
  }>("/ping");
}

export interface ContactFormPayload {
  name: string;
  email: string;
  phone: string;
  message: string;
}

export function submitContactForm(payload: ContactFormPayload) {
  return storefrontFetch<{ message: string }>("/contact", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export function fetchPhoneCountries() {
  return storefrontFetch<import("./phone-validation").PhoneCountry[]>("/phone-countries");
}

export function fetchGeoCountries() {
  return storefrontFetch<import("./phone-validation").GeoCountry[]>("/geo/countries");
}

export function fetchGeoStates(countryCode: string) {
  return storefrontFetch<import("./phone-validation").GeoState[]>(
    `/geo/states/${encodeURIComponent(countryCode)}`,
  );
}

export type AddCustomerPayload = {
  first_name: string;
  last_name: string;
  email: string;
  birth_date: string;
  country: string;
  state: string;
  mobile: string;
  dial_code?: string;
};

export type AddCustomerResult = {
  contact: {
    id: number;
    name: string;
    first_name: string | null;
    last_name: string | null;
    email: string | null;
    mobile: string;
    country: string | null;
    state: string | null;
    dob: string | null;
  };
  message: string;
  created: boolean;
};

export function addCustomer(payload: AddCustomerPayload) {
  return storefrontFetch<AddCustomerResult>("/customers/add", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export function fetchWishlist(token: string, locale?: string) {
  return storefrontFetch<WishlistPayload>("/wishlist", { headers: authHeaders(token) }, locale);
}

export function addToWishlist(token: string, productId: number, locale?: string) {
  return storefrontFetch<WishlistPayload>(
    "/wishlist",
    {
      method: "POST",
      headers: authHeaders(token),
      body: JSON.stringify({ product_id: productId }),
    },
    locale,
  );
}

export function removeFromWishlist(token: string, productId: number, locale?: string) {
  return storefrontFetch<WishlistPayload>(
    `/wishlist/${productId}`,
    {
      method: "DELETE",
      headers: authHeaders(token),
    },
    locale,
  );
}

export function mergeWishlist(token: string, productIds: number[], locale?: string) {
  return storefrontFetch<WishlistPayload>(
    "/wishlist/merge",
    {
      method: "POST",
      headers: authHeaders(token),
      body: JSON.stringify({ product_ids: productIds }),
    },
    locale,
  );
}

export type { AvailabilityLocation, ProductAvailability, ProductDetail, ProductSummary, StoreSettings };
