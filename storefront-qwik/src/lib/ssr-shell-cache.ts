/**
 * Short-lived in-process cache for SSR API responses (layout shell + homepage).
 *
 * Qwik City re-runs routeLoaders on every SSR / q-data navigation. Without
 * caching, a traffic burst on the shop fans out into several POS API calls per
 * render, saturating PHP-FPM until the POS healthcheck fails and Traefik drops
 * the route.
 *
 * - Fresh hits are served for `ttlMs`.
 * - Concurrent misses for the same key share one in-flight load.
 * - When a load fails (or returns a degraded value), the last good value is
 *   served for up to `staleMs` so the shop keeps rendering during POS outages.
 */

type CacheEntry = {
  value: unknown;
  expiresAt: number;
  staleUntil: number;
};

export type SsrCacheOptions<T> = {
  ttlMs?: number;
  staleMs?: number;
  /** Return true when the loaded value is a partial/fallback result that must not replace a good one. */
  isDegraded?: (value: T) => boolean;
};

const DEFAULT_TTL_MS = 30_000;
const DEFAULT_STALE_MS = 10 * 60_000;

const entries = new Map<string, CacheEntry>();
const inFlight = new Map<string, Promise<unknown>>();

function readEntry(key: string, now: number): CacheEntry | undefined {
  const entry = entries.get(key);
  if (entry && entry.staleUntil <= now) {
    entries.delete(key);
    return undefined;
  }
  return entry;
}

async function loadAndStore<T>(
  key: string,
  load: () => Promise<T>,
  options: SsrCacheOptions<T>,
): Promise<T> {
  const ttlMs = options.ttlMs ?? DEFAULT_TTL_MS;
  const staleMs = options.staleMs ?? DEFAULT_STALE_MS;

  let value: T;
  try {
    value = await load();
  } catch (err) {
    const stale = readEntry(key, Date.now());
    if (stale) {
      return stale.value as T;
    }
    throw err;
  }

  const now = Date.now();
  if (options.isDegraded?.(value)) {
    const stale = readEntry(key, now);
    return stale ? (stale.value as T) : value;
  }

  entries.set(key, { value, expiresAt: now + ttlMs, staleUntil: now + ttlMs + staleMs });
  return value;
}

/** Cache an SSR loader result under `key` with single-flight loading and stale-on-error fallback. */
export async function cachedSsr<T>(
  key: string,
  load: () => Promise<T>,
  options: SsrCacheOptions<T> = {},
): Promise<T> {
  const entry = readEntry(key, Date.now());
  if (entry && entry.expiresAt > Date.now()) {
    return entry.value as T;
  }

  const pending = inFlight.get(key);
  if (pending) {
    return pending as Promise<T>;
  }

  const promise = loadAndStore(key, load, options).finally(() => {
    inFlight.delete(key);
  });
  inFlight.set(key, promise);
  return promise;
}

/** Test helper: drop all cached and in-flight entries. */
export function clearSsrCache(): void {
  entries.clear();
  inFlight.clear();
}

export function cachedSettings<T>(locale: string, load: () => Promise<T>): Promise<T> {
  return cachedSsr(`settings:${locale}`, load);
}

export function cachedCategories<T>(locale: string, load: () => Promise<T>): Promise<T> {
  return cachedSsr(`categories:${locale}`, load);
}

export function cachedLocations<T>(locale: string, load: () => Promise<T>): Promise<T> {
  return cachedSsr(`locations:${locale}`, load);
}

export function cachedHomepageSections<T>(locale: string, load: () => Promise<T>): Promise<T> {
  return cachedSsr(`homepage-sections:${locale}`, load);
}

export function cachedHomepageCatalog<T>(
  locale: string,
  load: () => Promise<T>,
  isDegraded: (value: T) => boolean,
): Promise<T> {
  return cachedSsr(`homepage-catalog:${locale}`, load, { isDegraded });
}
