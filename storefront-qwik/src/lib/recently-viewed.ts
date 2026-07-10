import type { ProductSummary } from "~/lib/types";

/** Max products kept in recently-viewed history. */
export const RECENTLY_VIEWED_MAX = 12;

/** Max products shown in a recently-viewed grid. */
export const RECENTLY_VIEWED_DISPLAY = 8;

/** Per-locale key so AR/EN names stay correct after language switch. */
export const recentlyViewedStorageKey = (locale: string) =>
  `gs-recently-viewed-${locale}-v1`;

const isProductSummary = (value: unknown): value is ProductSummary =>
  typeof value === "object" &&
  value !== null &&
  typeof (value as ProductSummary).id === "number" &&
  typeof (value as ProductSummary).name === "string";

/** Parse recently-viewed JSON; returns [] on corrupt data. */
export const parseStoredRecentlyViewed = (raw: string | null): ProductSummary[] => {
  if (!raw) {
    return [];
  }
  try {
    const parsed = JSON.parse(raw) as unknown;
    if (!Array.isArray(parsed)) {
      return [];
    }
    return parsed.filter(isProductSummary).map((item) => ({ ...item }));
  } catch {
    return [];
  }
};

export const persistRecentlyViewed = (locale: string, items: ProductSummary[]) => {
  if (typeof localStorage === "undefined") {
    return;
  }
  const key = recentlyViewedStorageKey(locale);
  if (items.length === 0) {
    localStorage.removeItem(key);
    return;
  }
  localStorage.setItem(key, JSON.stringify(items.slice(0, RECENTLY_VIEWED_MAX)));
};

export const loadRecentlyViewed = (locale: string): ProductSummary[] => {
  if (typeof localStorage === "undefined") {
    return [];
  }
  return parseStoredRecentlyViewed(localStorage.getItem(recentlyViewedStorageKey(locale)));
};

/**
 * Prepend a product to history (most recent first), dedupe by id, cap length.
 * Returns the updated list.
 */
export const recordRecentlyViewed = (
  locale: string,
  product: ProductSummary,
): ProductSummary[] => {
  const existing = loadRecentlyViewed(locale).filter((item) => item.id !== product.id);
  const next = [product, ...existing].slice(0, RECENTLY_VIEWED_MAX);
  persistRecentlyViewed(locale, next);
  return next;
};

/** History for display, optionally excluding the product currently on screen. */
export const getRecentlyViewedForDisplay = (
  locale: string,
  options: { excludeProductId?: number; limit?: number } = {},
): ProductSummary[] => {
  const limit = options.limit ?? RECENTLY_VIEWED_DISPLAY;
  return loadRecentlyViewed(locale)
    .filter((item) => item.id !== options.excludeProductId)
    .slice(0, limit);
};
