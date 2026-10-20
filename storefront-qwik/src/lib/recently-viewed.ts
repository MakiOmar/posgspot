import type { ProductSummary } from "~/lib/types";

/** Max products kept in recently-viewed history. */
export const RECENTLY_VIEWED_MAX = 12;

/** Max products shown in a recently-viewed grid. */
export const RECENTLY_VIEWED_DISPLAY = 8;

/** Stored summaries carry price/stock snapshots, so drop entries after a week. */
export const RECENTLY_VIEWED_TTL_MS = 7 * 24 * 60 * 60 * 1000;

/** Per-locale key so AR/EN names stay correct after language switch. */
export const recentlyViewedStorageKey = (locale: string) =>
  `gs-recently-viewed-${locale}-v2`;

type StoredRecentlyViewed = ProductSummary & { viewed_at: number };

const isStoredEntry = (value: unknown): value is StoredRecentlyViewed =>
  typeof value === "object" &&
  value !== null &&
  typeof (value as ProductSummary).id === "number" &&
  typeof (value as ProductSummary).name === "string" &&
  typeof (value as StoredRecentlyViewed).viewed_at === "number";

/** Parse recently-viewed JSON, dropping expired entries; returns [] on corrupt data. */
export const parseStoredRecentlyViewed = (
  raw: string | null,
  now: number = Date.now(),
): StoredRecentlyViewed[] => {
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
};

const persistRecentlyViewed = (locale: string, items: StoredRecentlyViewed[]) => {
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

const loadStoredRecentlyViewed = (locale: string): StoredRecentlyViewed[] => {
  if (typeof localStorage === "undefined") {
    return [];
  }
  return parseStoredRecentlyViewed(localStorage.getItem(recentlyViewedStorageKey(locale)));
};

const toSummary = ({ viewed_at: _viewedAt, ...item }: StoredRecentlyViewed): ProductSummary => item;

export const loadRecentlyViewed = (locale: string): ProductSummary[] =>
  loadStoredRecentlyViewed(locale).map(toSummary);

/**
 * Prepend a product to history (most recent first), dedupe by id, cap length.
 * Returns the updated list.
 */
export const recordRecentlyViewed = (
  locale: string,
  product: ProductSummary,
): ProductSummary[] => {
  const existing = loadStoredRecentlyViewed(locale).filter((item) => item.id !== product.id);
  const next = [{ ...product, viewed_at: Date.now() }, ...existing].slice(0, RECENTLY_VIEWED_MAX);
  persistRecentlyViewed(locale, next);
  return next.map(toSummary);
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
