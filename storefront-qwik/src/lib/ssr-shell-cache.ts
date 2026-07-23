/**
 * Short-lived in-process cache for layout shell API responses (settings, categories).
 *
 * Qwik City re-runs layout routeLoaders on every SSR / q-data navigation. Without
 * caching, the Node preview/SSR process hammers Laravel from a single IP and trips
 * throttle:storefront (429 Too Many Attempts).
 */

type CacheEntry<T> = {
  value: T;
  expiresAt: number;
};

const DEFAULT_TTL_MS = 30_000;

const settingsCache = new Map<string, CacheEntry<unknown>>();
const categoriesCache = new Map<string, CacheEntry<unknown>>();
const locationsCache = new Map<string, CacheEntry<unknown>>();

function readCache<T>(map: Map<string, CacheEntry<unknown>>, key: string): T | undefined {
  const entry = map.get(key);
  if (!entry) {
    return undefined;
  }
  if (entry.expiresAt <= Date.now()) {
    map.delete(key);
    return undefined;
  }
  return entry.value as T;
}

function writeCache<T>(
  map: Map<string, CacheEntry<unknown>>,
  key: string,
  value: T,
  ttlMs: number,
): void {
  map.set(key, { value, expiresAt: Date.now() + ttlMs });
}

export async function cachedSettings<T>(
  locale: string,
  load: () => Promise<T>,
  ttlMs = DEFAULT_TTL_MS,
): Promise<T> {
  const hit = readCache<T>(settingsCache, locale);
  if (hit !== undefined) {
    return hit;
  }
  const value = await load();
  writeCache(settingsCache, locale, value, ttlMs);
  return value;
}

export async function cachedCategories<T>(
  locale: string,
  load: () => Promise<T>,
  ttlMs = DEFAULT_TTL_MS,
): Promise<T> {
  const hit = readCache<T>(categoriesCache, locale);
  if (hit !== undefined) {
    return hit;
  }
  const value = await load();
  writeCache(categoriesCache, locale, value, ttlMs);
  return value;
}

export async function cachedLocations<T>(
  locale: string,
  load: () => Promise<T>,
  ttlMs = DEFAULT_TTL_MS,
): Promise<T> {
  const hit = readCache<T>(locationsCache, locale);
  if (hit !== undefined) {
    return hit;
  }
  const value = await load();
  writeCache(locationsCache, locale, value, ttlMs);
  return value;
}
