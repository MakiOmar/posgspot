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
 * - Expired entries still inside `staleMs` are served immediately while one
 *   background load refreshes them (stale-while-revalidate).
 * - When a load fails (or returns a degraded value), the last good value keeps
 *   being served and the next retry is pushed back by `backoffMs`, so a POS
 *   outage costs one slow upstream call per key per backoff window.
 */

type CacheEntry = {
  value: unknown;
  expiresAt: number;
  staleUntil: number;
};

export type SsrCacheOptions<T> = {
  ttlMs?: number;
  staleMs?: number;
  backoffMs?: number;
  /** Return true when the loaded value is a partial/fallback result that must not replace a good one. */
  isDegraded?: (value: T) => boolean;
};

const DEFAULT_TTL_MS = 30_000;
const DEFAULT_STALE_MS = 10 * 60_000;
const DEFAULT_BACKOFF_MS = 15_000;

/** Keys that embed config signatures can be orphaned; sweep dead entries past this size. */
const SWEEP_THRESHOLD = 200;

const entries = new Map<string, CacheEntry>();
const inFlight = new Map<string, Promise<unknown>>();

function sweepExpired(now: number): void {
  if (entries.size < SWEEP_THRESHOLD) {
    return;
  }
  for (const [key, entry] of entries) {
    if (entry.staleUntil <= now) {
      entries.delete(key);
    }
  }
}

function readEntry(key: string, now: number): CacheEntry | undefined {
  const entry = entries.get(key);
  if (entry && entry.staleUntil <= now) {
    entries.delete(key);
    return undefined;
  }
  return entry;
}

/** Keep serving the last good value and delay the next refresh attempt. */
function serveStaleWithBackoff<T>(key: string, options: SsrCacheOptions<T>): CacheEntry | undefined {
  const now = Date.now();
  const stale = readEntry(key, now);
  if (stale) {
    stale.expiresAt = Math.min(now + (options.backoffMs ?? DEFAULT_BACKOFF_MS), stale.staleUntil);
  }
  return stale;
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
    const stale = serveStaleWithBackoff(key, options);
    if (stale) {
      return stale.value as T;
    }
    throw err;
  }

  if (options.isDegraded?.(value)) {
    const stale = serveStaleWithBackoff(key, options);
    return stale ? (stale.value as T) : value;
  }

  const now = Date.now();
  sweepExpired(now);
  entries.set(key, { value, expiresAt: now + ttlMs, staleUntil: now + ttlMs + staleMs });
  return value;
}

function startLoad<T>(key: string, load: () => Promise<T>, options: SsrCacheOptions<T>): Promise<T> {
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

/** Cache an SSR loader result under `key` with single-flight loading, stale-while-revalidate and stale-on-error fallback. */
export async function cachedSsr<T>(
  key: string,
  load: () => Promise<T>,
  options: SsrCacheOptions<T> = {},
): Promise<T> {
  const now = Date.now();
  const entry = readEntry(key, now);
  if (entry && entry.expiresAt > now) {
    return entry.value as T;
  }

  if (entry) {
    // Background refresh failures are already absorbed by the stale fallback.
    startLoad(key, load, options).catch(() => undefined);
    return entry.value as T;
  }

  return startLoad(key, load, options);
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
  cacheKey: string,
  load: () => Promise<T>,
  isDegraded: (value: T) => boolean,
): Promise<T> {
  return cachedSsr(`homepage-catalog:${cacheKey}`, load, { isDegraded });
}
