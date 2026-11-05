import type { ProductDetail, ProductSummary } from "./types";

/** Max products kept in recently-viewed history. */
export const RECENTLY_VIEWED_MAX = 12;

/** Max products shown in a recently-viewed rail. */
export const RECENTLY_VIEWED_DISPLAY = 8;

/** Stored summaries carry price/stock snapshots, so drop entries after a week. */
export const RECENTLY_VIEWED_TTL_MS = 7 * 24 * 60 * 60 * 1000;

/** Per-locale key (same as the website) so AR/EN names stay correct after a language switch. */
export const recentlyViewedStorageKey = (locale: string) => `gs-recently-viewed-${locale}-v2`;

export type StoredRecentlyViewed = ProductSummary & { viewed_at: number };

const isStoredEntry = (value: unknown): value is StoredRecentlyViewed =>
  typeof value === "object" &&
  value !== null &&
  typeof (value as ProductSummary).id === "number" &&
  typeof (value as ProductSummary).name === "string" &&
  typeof (value as StoredRecentlyViewed).viewed_at === "number";

/** Parse stored JSON, dropping expired entries; returns [] on corrupt data. */
export function parseStoredRecentlyViewed(
  raw: string | null,
  now: number = Date.now(),
): StoredRecentlyViewed[] {
  if (!raw) {
    return [];
  }
  try {
    const parsed = JSON.parse(raw) as unknown;
    if (!Array.isArray(parsed)) {
      return [];
    }
    return parsed
      .filter(isStoredEntry)
      .filter((item) => now - item.viewed_at < RECENTLY_VIEWED_TTL_MS)
      .map((item) => ({ ...item }));
  } catch {
    return [];
  }
}

/** Prepend a product (most recent first), dedupe by id, cap length. */
export function prependRecentlyViewed(
  existing: StoredRecentlyViewed[],
  product: ProductSummary,
  now: number = Date.now(),
): StoredRecentlyViewed[] {
  const rest = existing.filter((item) => item.id !== product.id);
  return [{ ...product, viewed_at: now }, ...rest].slice(0, RECENTLY_VIEWED_MAX);
}

/** Summaries for display, optionally excluding the product on screen. */
export function recentlyViewedForDisplay(
  stored: StoredRecentlyViewed[],
  options: { excludeProductId?: number; limit?: number } = {},
): ProductSummary[] {
  const limit = options.limit ?? RECENTLY_VIEWED_DISPLAY;
  return stored
    .filter((item) => item.id !== options.excludeProductId)
    .slice(0, limit)
    .map(({ viewed_at: _viewedAt, ...item }) => item);
}

/** Card snapshot of a product page for the selected variation (same fields as the website). */
export function productSummaryFromDetail(
  product: ProductDetail,
  variationId: number | null,
  imageUrl: string | null,
): ProductSummary {
  const variation =
    product.variations?.find((v) => v.id === variationId) ?? product.variations?.[0];
  return {
    id: product.id,
    slug: product.slug,
    name: product.name,
    image_url: imageUrl ?? product.image_url ?? null,
    variation_id: variation?.id ?? product.variation_id ?? null,
    variation_name: variation?.name ?? null,
    has_options: (product.variations?.length ?? 0) > 1,
    price: Number(variation?.price ?? product.price ?? 0),
    compare_at_price: product.compare_at_price ?? null,
    on_sale: product.on_sale ?? false,
    sale_percent: product.sale_percent ?? 0,
    in_stock: variation?.in_stock ?? product.in_stock,
    rating_average: product.rating?.average ?? product.rating_average ?? 0,
    rating_count: product.rating?.count ?? product.rating_count ?? 0,
  };
}
