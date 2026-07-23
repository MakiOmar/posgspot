import { MAX_SVG_BYTES, prepareInlineSvg } from "~/lib/sanitize-svg";

const FETCH_TIMEOUT_MS = 8_000;
const cache = new Map<string, string>();
const MAX_CACHE = 64;

function cacheKey(url: string, currentColor: boolean): string {
  return `${currentColor ? "1" : "0"}:${url}`;
}

function remember(key: string, value: string): string {
  if (cache.size >= MAX_CACHE) {
    const first = cache.keys().next().value;
    if (first !== undefined) {
      cache.delete(first);
    }
  }
  cache.set(key, value);
  return value;
}

/**
 * Fetch an SVG file (SSR or browser), sanitize, and optionally force currentColor.
 * Returns empty string on failure — callers should fall back to &lt;img&gt;.
 */
export async function fetchRemoteSvgMarkup(
  url: string,
  options: { currentColor?: boolean } = {},
): Promise<string> {
  const src = (url || "").trim();
  if (!src || !/^https?:\/\//i.test(src)) {
    return "";
  }

  const useCurrentColor = options.currentColor !== false;
  const key = cacheKey(src, useCurrentColor);
  const hit = cache.get(key);
  if (hit !== undefined) {
    return hit;
  }

  try {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), FETCH_TIMEOUT_MS);
    const res = await fetch(src, {
      signal: controller.signal,
      headers: { Accept: "image/svg+xml,text/plain,*/*" },
    });
    clearTimeout(timer);

    if (!res.ok) {
      return remember(key, "");
    }

    const buf = await res.arrayBuffer();
    if (buf.byteLength === 0 || buf.byteLength > MAX_SVG_BYTES) {
      return remember(key, "");
    }

    const text = new TextDecoder("utf-8").decode(buf);
    const prepared = prepareInlineSvg(text, { currentColor: useCurrentColor });
    return remember(key, prepared);
  } catch {
    return remember(key, "");
  }
}

export function isSvgUrl(url: string | null | undefined): boolean {
  return Boolean(url && /\.svg(\?|#|$)/i.test(url));
}
