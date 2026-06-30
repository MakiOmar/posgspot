/**
 * Storefront API client for the Laravel POS backend.
 */
import type {
  ApiEnvelope,
  ApiErrorBody,
  AvailabilityLocation,
  CartValidation,
  Category,
  CheckoutOrder,
  ProductAvailability,
  ProductDetail,
  ProductSummary,
  ProductsMeta,
  StoreLocation,
  StoreSettings,
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

/** Perform a JSON request against the Storefront API envelope. */
export async function storefrontFetch<T>(
  path: string,
  options: RequestInit = {},
): Promise<FetchResult<T>> {
  const url = `${API_BASE}${PREFIX}${path}`;
  const headers: Record<string, string> = {
    Accept: "application/json",
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

export function fetchSettings() {
  return storefrontFetch<StoreSettings>("/settings");
}

export function fetchLocations() {
  return storefrontFetch<StoreLocation[]>("/locations");
}

export function fetchCategories() {
  return storefrontFetch<Category[]>("/categories");
}

export function fetchProducts(params: Record<string, string | number | boolean> = {}) {
  const qs = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== "" && value !== undefined) {
      qs.set(key, String(value));
    }
  }
  const query = qs.toString();
  return storefrontFetch<ProductSummary[]>(`/products${query ? `?${query}` : ""}`);
}

export async function fetchProductsPage(
  params: Record<string, string | number | boolean> = {},
) {
  const qs = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== "" && value !== undefined) {
      qs.set(key, String(value));
    }
  }
  const url = `${API_BASE}${PREFIX}/products?${qs.toString()}`;
  const response = await fetch(url, {
    credentials: "include",
    headers: { Accept: "application/json" },
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

export function fetchProduct(idOrSlug: string) {
  return storefrontFetch<ProductDetail>(`/products/${encodeURIComponent(idOrSlug)}`);
}

export function fetchAvailability(productId: number, variationId?: number) {
  const qs = variationId ? `?variation_id=${variationId}` : "";
  return storefrontFetch<ProductAvailability>(`/products/${productId}/availability${qs}`);
}

export function searchProducts(q: string, limit = 8) {
  return storefrontFetch<ProductSummary[]>(
    `/search?q=${encodeURIComponent(q)}&limit=${limit}`,
  );
}

export function validateCart(payload: {
  location_id: number;
  items: { variation_id: number; quantity: number }[];
}) {
  return storefrontFetch<CartValidation>("/cart/validate", {
    method: "POST",
    body: JSON.stringify(payload),
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

/** Health-check (connectivity / CORS). */
export function pingApi() {
  return storefrontFetch<{
    status: string;
    service: string;
    version: string;
    time: string;
  }>("/ping");
}

export type { AvailabilityLocation, ProductAvailability, ProductDetail, ProductSummary, StoreSettings };
