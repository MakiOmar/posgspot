import { API_BASE } from "./config";

/**
 * Turn Storefront web paths / absolute URLs into Expo Router hrefs.
 */
export function hrefToAppPath(href: string | null | undefined): string | null {
  if (!href || typeof href !== "string") {
    return null;
  }
  let path = href.trim();
  if (!path) {
    return null;
  }
  try {
    if (/^https?:\/\//i.test(path)) {
      path = new URL(path).pathname + new URL(path).search;
    }
  } catch {
    return null;
  }
  // Strip locale prefix used by the Qwik site.
  path = path.replace(/^\/(en|ar)(?=\/|$)/i, "") || "/";
  if (!path.startsWith("/")) {
    path = `/${path}`;
  }
  // Map common web aliases onto app routes.
  if (path === "/shop" || path.startsWith("/shop?")) {
    return "/(tabs)/shop";
  }
  return path;
}

/** Resolve relative media URLs against the API origin. */
export function absoluteMediaUrl(url: string | null | undefined): string | null {
  if (!url || typeof url !== "string") {
    return null;
  }
  const trimmed = url.trim();
  if (!trimmed) {
    return null;
  }
  if (/^https?:\/\//i.test(trimmed) || trimmed.startsWith("data:")) {
    return trimmed;
  }
  const base = API_BASE.replace(/\/$/, "");
  return `${base}/${trimmed.replace(/^\//, "")}`;
}
