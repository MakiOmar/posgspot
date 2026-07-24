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
      const url = new URL(path);
      // External domains stay as absolute URLs (open in browser).
      const apiHost = (() => {
        try {
          return new URL(API_BASE).host;
        } catch {
          return "";
        }
      })();
      const knownHosts = new Set(
        [apiHost, "shop.gamesspoteg.com", "new.gamesspoteg.com", "posstaging.gamesspoteg.com"].filter(
          Boolean,
        ),
      );
      if (url.host && !knownHosts.has(url.host) && !url.host.endsWith("gamesspoteg.com")) {
        return path;
      }
      path = url.pathname + url.search;
    }
  } catch {
    return null;
  }

  // Strip locale prefix used by the Qwik site.
  path = path.replace(/^\/(en|ar)(?=\/|$)/i, "") || "/";
  if (!path.startsWith("/")) {
    path = `/${path}`;
  }

  // Normalize web aliases onto app routes.
  path = path.replace(/^\/product\//i, "/products/");
  path = path.replace(/^\/categories\//i, "/category/");
  path = path.replace(/^\/brand\//i, "/brands/");

  if (path === "/" || path === "") {
    return "/";
  }
  if (path === "/shop" || path.startsWith("/shop?")) {
    return "/(tabs)/shop";
  }
  if (path === "/cart" || path.startsWith("/cart?")) {
    return "/(tabs)/cart";
  }
  if (path === "/account" || path === "/account/") {
    return "/(tabs)/account";
  }
  // `/products` (no slug) → catalog index; `/products/:slug` stays as PDP.
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
