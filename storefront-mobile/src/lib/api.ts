import { API_BASE, CLIENT_HEADER } from "./config";
import type {
  AccountOrder,
  AccountOrderDetail,
  ApiEnvelope,
  ApiErrorBody,
  AuthSession,
  Brand,
  CartApiItem,
  Category,
  CheckoutOrder,
  ContentLocale,
  FawryPaymentSession,
  HomepageSection,
  ProductDetail,
  ProductSummary,
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
  return storefrontFetch("/account/profile", {
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

export function validateCart(
  items: CartApiItem[],
  extras: Record<string, unknown> = {},
  token?: string | null,
) {
  return storefrontFetch("/cart/validate", {
    method: "POST",
    headers: authHeaders(token),
    body: JSON.stringify({ items, ...extras }),
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
  return storefrontFetch("/account/reward-points", {
    headers: authHeaders(token),
  });
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

export function fetchDigitalGames(locale?: ContentLocale) {
  return storefrontFetch("/digital/games", {}, locale);
}

export function fetchDigitalGame(id: number, locale?: ContentLocale) {
  return storefrontFetch(`/digital/games/${id}`, {}, locale);
}

export function fetchCardCategories(locale?: ContentLocale) {
  return storefrontFetch("/digital/card-categories", {}, locale);
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

export function fetchProductReviews(idOrSlug: string, locale?: ContentLocale) {
  return storefrontFetch(
    `/products/${encodeURIComponent(idOrSlug)}/reviews`,
    {},
    locale,
  );
}

export function fetchAvailability(productId: number, locale?: ContentLocale) {
  return storefrontFetch(`/products/${productId}/availability`, {}, locale);
}
