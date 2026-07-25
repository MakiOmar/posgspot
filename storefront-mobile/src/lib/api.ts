import { API_BASE, CLIENT_HEADER } from "./config";
import type {
  AccountOrder,
  AccountOrderDetail,
  ApiEnvelope,
  ApiErrorBody,
  AuthContact,
  AuthSession,
  Brand,
  CartApiItem,
  Category,
  CheckoutOrder,
  ContentLocale,
  FawryPaymentSession,
  HomepageSection,
  ProductAvailability,
  ProductDetail,
  ProductReviewItem,
  ProductSummary,
  ReviewEligibility,
  StoreLocation,
  StoreSettings,
  WishlistPayload,
} from "./types";

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

let activeContentLocale: ContentLocale = "en";
let onUnauthorized: (() => void) | null = null;

export function setActiveContentLocale(locale: ContentLocale): void {
  activeContentLocale = locale;
}

export function getActiveContentLocale(): ContentLocale {
  return activeContentLocale;
}

export function setUnauthorizedHandler(handler: (() => void) | null): void {
  onUnauthorized = handler;
}

export function authHeaders(token: string | null | undefined): Record<string, string> {
  return token ? { Authorization: `Bearer ${token}` } : {};
}

export async function storefrontFetch<T>(
  path: string,
  options: RequestInit = {},
  locale?: ContentLocale,
): Promise<FetchResult<T>> {
  const url = `${API_BASE}${PREFIX}${path}`;
  const contentLocale = locale ?? activeContentLocale;
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Content-Locale": contentLocale,
    "X-Storefront-Client": CLIENT_HEADER,
    ...(options.headers as Record<string, string> | undefined),
  };

  if (options.body && !headers["Content-Type"]) {
    headers["Content-Type"] = "application/json";
  }

  const response = await fetch(url, {
    ...options,
    headers,
  });

  const json = (await response.json()) as ApiEnvelope<T> | ApiErrorBody;

  if (!response.ok || !json.success) {
    const err = json as ApiErrorBody;
    const authHeader = headers.Authorization || headers.authorization;
    const isPublicAuthAttempt =
      path === "/auth/login" ||
      path === "/auth/register" ||
      path.startsWith("/auth/forgot-password") ||
      path.startsWith("/auth/reset-password");
    if (response.status === 401 && authHeader && !isPublicAuthAttempt) {
      onUnauthorized?.();
    }
    throw new ApiError(
      response.status,
      err.message || `API ${response.status}`,
      err.errors || {},
    );
  }

  return { data: json.data, meta: (json.meta as Record<string, unknown>) || {} };
}

export function fetchSettings(locale?: ContentLocale) {
  return storefrontFetch<StoreSettings>("/settings", {}, locale);
}

export function fetchHomepage(locale?: ContentLocale) {
  return storefrontFetch<{ sections: HomepageSection[] }>("/homepage", {}, locale);
}

export function fetchHomepageShelves(locale?: ContentLocale) {
  return storefrontFetch<import("./types").HomepageCategoryShelf[]>(
    "/categories/homepage-shelves",
    {},
    locale,
  );
}

export function fetchProducts(
  query: Record<string, string | number | boolean | undefined> = {},
  locale?: ContentLocale,
) {
  const params = new URLSearchParams();
  Object.entries(query).forEach(([key, value]) => {
    if (value !== undefined && value !== "") {
      params.set(key, String(value));
    }
  });
  const qs = params.toString();
  return storefrontFetch<ProductSummary[]>(
    `/products${qs ? `?${qs}` : ""}`,
    {},
    locale,
  );
}

export function fetchProduct(idOrSlug: string, locale?: ContentLocale) {
  return storefrontFetch<ProductDetail>(
    `/products/${encodeURIComponent(idOrSlug)}`,
    {},
    locale,
  );
}

export function fetchCategories(locale?: ContentLocale) {
  return storefrontFetch<Category[]>("/categories", {}, locale);
}

export function fetchCategory(slug: string, locale?: ContentLocale) {
  return storefrontFetch<Category>(
    `/categories/${encodeURIComponent(slug)}`,
    {},
    locale,
  );
}

export function fetchBrands(locale?: ContentLocale) {
  return storefrontFetch<Brand[]>("/brands", {}, locale);
}

export function fetchBrand(slug: string, locale?: ContentLocale) {
  return storefrontFetch<Brand>(`/brands/${encodeURIComponent(slug)}`, {}, locale);
}

export function searchProducts(q: string, locale?: ContentLocale) {
  return storefrontFetch<ProductSummary[]>(
    `/search?q=${encodeURIComponent(q)}`,
    {},
    locale,
  );
}

export function fetchLocations(sellingOnly = false, locale?: ContentLocale) {
  const qs = sellingOnly ? "?selling_only=1" : "";
  return storefrontFetch<StoreLocation[]>(`/locations${qs}`, {}, locale);
}

export function login(loginId: string, password: string) {
  return storefrontFetch<AuthSession>("/auth/login", {
    method: "POST",
    body: JSON.stringify({ login: loginId, password }),
  });
}

export function register(body: Record<string, unknown>) {
  return storefrontFetch<AuthSession>("/auth/register", {
    method: "POST",
    body: JSON.stringify(body),
  });
}

export function logout(token: string) {
  return storefrontFetch("/auth/logout", {
    method: "POST",
    headers: authHeaders(token),
  });
}

export function fetchProfile(token: string) {
  return storefrontFetch<AuthContact>("/account/profile", {
    headers: authHeaders(token),
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
  return storefrontFetch<{ invoice_print_url: string }>(
    `/account/orders/${orderId}/invoice`,
    { headers: authHeaders(token) },
  );
}

export function validateCart(
  items: CartApiItem[],
  extras: Record<string, unknown> = {},
  token?: string | null,
) {
  return storefrontFetch<import("./types").CartValidationResult>("/cart/validate", {
    method: "POST",
    headers: authHeaders(token),
    body: JSON.stringify({ items, resolve: true, ...extras }),
  });
}

export function checkout(
  body: Record<string, unknown>,
  token?: string | null,
) {
  return storefrontFetch<CheckoutOrder>("/checkout", {
    method: "POST",
    headers: authHeaders(token),
    body: JSON.stringify(body),
  });
}

export function fetchPaymentSession(
  provider: string,
  storefrontOrderId: string,
  locale?: ContentLocale,
) {
  return storefrontFetch<FawryPaymentSession>(`/payments/${provider}/session`, {
    method: "POST",
    body: JSON.stringify({
      storefront_order_id: storefrontOrderId,
      locale: locale ?? activeContentLocale,
    }),
  });
}

export function confirmPaymentReturn(
  provider: string,
  body: Record<string, unknown>,
) {
  return storefrontFetch(`/payments/${provider}/return`, {
    method: "POST",
    body: JSON.stringify(body),
  });
}

export function fetchWishlist(token: string) {
  return storefrontFetch<WishlistPayload>("/wishlist", {
    headers: authHeaders(token),
  });
}

export function addWishlist(token: string, productId: number) {
  return storefrontFetch<WishlistPayload>("/wishlist", {
    method: "POST",
    headers: authHeaders(token),
    body: JSON.stringify({ product_id: productId }),
  });
}

export function removeWishlist(token: string, productId: number) {
  return storefrontFetch<WishlistPayload>(`/wishlist/${productId}`, {
    method: "DELETE",
    headers: authHeaders(token),
  });
}

export function mergeWishlist(token: string, productIds: number[]) {
  return storefrontFetch<WishlistPayload>("/wishlist/merge", {
    method: "POST",
    headers: authHeaders(token),
    body: JSON.stringify({ product_ids: productIds }),
  });
}

export function fetchRewardPoints(token: string) {
  return storefrontFetch<import("./types").RewardPointsBalance>("/account/reward-points", {
    headers: authHeaders(token),
  });
}

export function fetchGeoCountries() {
  return storefrontFetch<import("./types").GeoCountry[]>("/geo/countries");
}

export function fetchGeoStates(countryCode: string) {
  return storefrontFetch<import("./types").GeoState[]>(
    `/geo/states/${encodeURIComponent(countryCode)}`,
  );
}

export function fetchBostaDistricts(stateCode: string, locale?: ContentLocale) {
  const qs = new URLSearchParams({ state: stateCode });
  return storefrontFetch<import("./types").BostaDistrictsResponse>(
    `/geo/bosta-districts?${qs.toString()}`,
    {},
    locale,
  );
}

export function validateCoupons(
  body: Record<string, unknown>,
  token: string,
) {
  return storefrontFetch("/coupons/validate", {
    method: "POST",
    headers: authHeaders(token),
    body: JSON.stringify(body),
  });
}

export function availableCoupons(
  body: Record<string, unknown>,
  token: string,
) {
  return storefrontFetch("/coupons/available", {
    method: "POST",
    headers: authHeaders(token),
    body: JSON.stringify(body),
  });
}

export function fetchDigitalGames(
  platform: "4" | "5" = "4",
  page = 1,
  locale?: ContentLocale,
) {
  return storefrontFetch<{
    platform: string;
    skus: import("./types").DigitalSkus;
    games: Array<{ id: number; title?: string; name?: string; [key: string]: unknown }>;
    meta: { current_page: number; last_page: number; per_page: number; total: number | null };
  }>(`/digital/games?platform=${platform}&page=${page}`, {}, locale);
}

export function fetchDigitalGame(id: number, locale?: ContentLocale) {
  return storefrontFetch<{
    game: Record<string, unknown>;
    skus: import("./types").DigitalSkus;
  }>(`/digital/games/${id}`, {}, locale);
}

export function fetchCardCategories(locale?: ContentLocale) {
  return storefrontFetch<{
    categories: Array<{
      id: number;
      name?: string;
      title?: string;
      price?: number | string;
      poster_image?: string | null;
    }>;
    skus: import("./types").DigitalSkus;
  }>("/digital/card-categories", {}, locale);
}

export function submitContact(body: Record<string, unknown>) {
  return storefrontFetch("/contact", {
    method: "POST",
    body: JSON.stringify(body),
  });
}

export function repairStatus(body: Record<string, unknown>) {
  return storefrontFetch("/repair/status", {
    method: "POST",
    body: JSON.stringify(body),
  });
}

export function registerDevice(
  token: string,
  payload: { platform: "ios" | "android"; token: string; locale?: string },
) {
  return storefrontFetch("/account/devices", {
    method: "POST",
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });
}

export function unregisterDevice(authToken: string, pushToken: string) {
  return storefrontFetch(
    `/account/devices/${encodeURIComponent(pushToken)}`,
    {
      method: "DELETE",
      headers: authHeaders(authToken),
    },
  );
}

export function updateProfile(
  token: string,
  payload: Record<string, unknown>,
) {
  return storefrontFetch<AuthContact>("/account/profile", {
    method: "PUT",
    headers: authHeaders(token),
    body: JSON.stringify(payload),
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

export function fetchProductReviews(
  idOrSlug: string,
  page = 1,
  perPage = 10,
  locale?: ContentLocale,
) {
  const qs = `?page=${page}&per_page=${perPage}`;
  return storefrontFetch<ProductReviewItem[]>(
    `/products/${encodeURIComponent(idOrSlug)}/reviews${qs}`,
    {},
    locale,
  );
}

export function fetchReviewEligibility(
  idOrSlug: string,
  token: string,
  locale?: ContentLocale,
) {
  return storefrontFetch<ReviewEligibility>(
    `/products/${encodeURIComponent(idOrSlug)}/reviews/eligibility`,
    { headers: authHeaders(token) },
    locale,
  );
}

export function submitProductReview(
  idOrSlug: string,
  token: string,
  payload: { rating: number; title?: string; body: string },
  locale?: ContentLocale,
) {
  return storefrontFetch<{ id: number; status: string; message: string }>(
    `/products/${encodeURIComponent(idOrSlug)}/reviews`,
    {
      method: "POST",
      headers: authHeaders(token),
      body: JSON.stringify(payload),
    },
    locale,
  );
}

export function fetchAvailability(
  productId: number,
  variationId?: number,
  locale?: ContentLocale,
) {
  const qs = variationId != null ? `?variation_id=${variationId}` : "";
  return storefrontFetch<ProductAvailability>(
    `/products/${productId}/availability${qs}`,
    {},
    locale,
  );
}

export function checkDigitalGameStock(
  payload: {
    game_id: number;
    type: "primary" | "secondary";
    platform: "4" | "5";
  },
  locale?: ContentLocale,
) {
  return storefrontFetch<Record<string, unknown>>(
    "/digital/check-stock",
    { method: "POST", body: JSON.stringify(payload) },
    locale,
  );
}

export function checkDigitalCardStock(
  cardCategoryId: number,
  locale?: ContentLocale,
) {
  return storefrontFetch<Record<string, unknown>>(
    "/digital/check-card-stock",
    {
      method: "POST",
      body: JSON.stringify({ card_category_id: cardCategoryId }),
    },
    locale,
  );
}
