/**
 * Minimal Storefront API client.
 *
 * Talks to the Laravel POS Storefront API. The base URL is configured via the
 * PUBLIC_API_BASE env var so we can point local dev at the production backend
 * (https://pos.gamesspoteg.com) and later switch environments without code changes.
 */

// Fallback keeps things working if the env var is missing during early setup.
export const API_BASE: string =
  (import.meta.env.PUBLIC_API_BASE as string | undefined)?.replace(/\/$/, "") ||
  "https://pos.gamesspoteg.com";

const STOREFRONT_PREFIX = "/api/storefront/v1";

/**
 * Perform a JSON request against the Storefront API.
 */
export async function apiFetch<T = unknown>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const url = `${API_BASE}${STOREFRONT_PREFIX}${path}`;

  const response = await fetch(url, {
    // Sanctum cookie auth will need credentials; harmless for public GETs.
    credentials: "include",
    headers: {
      Accept: "application/json",
      ...(options.headers || {}),
    },
    ...options,
  });

  if (!response.ok) {
    throw new Error(`API ${response.status} ${response.statusText} for ${url}`);
  }

  return (await response.json()) as T;
}

/**
 * Health-check the Storefront API (used to verify connectivity and CORS).
 */
export function pingApi() {
  return apiFetch<{
    status: string;
    service: string;
    version: string;
    time: string;
  }>("/ping");
}
