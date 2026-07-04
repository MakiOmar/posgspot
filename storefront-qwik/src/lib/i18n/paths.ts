import { DEFAULT_CONTENT_LOCALE, isSupportedLocale, type StoreLocaleCode } from "./config";

export function stripLocalePrefix(pathname: string): string {
  const parts = pathname.split("/").filter(Boolean);
  if (parts.length > 0 && isSupportedLocale(parts[0])) {
    parts.shift();
  }
  return "/" + parts.join("/");
}

/** First path segment when it is a supported storefront locale, else default. */
export function localeFromPathname(pathname: string): StoreLocaleCode {
  const first = pathname.split("/").filter(Boolean)[0];
  return isSupportedLocale(first) ? first : DEFAULT_CONTENT_LOCALE;
}

/**
 * Locale-prefixed path with a trailing slash (Qwik City default).
 * Query strings in `path` are preserved after the slash, e.g. `/products?q=x` → `/en/products/?q=x`.
 * Omitting the slash causes a redirect on SPA navigations and can leave route loaders stale
 * (pagination/filters update the URL but not the product list until a full reload).
 */
export function localePath(lang: StoreLocaleCode | string, path = "/"): string {
  const normalized = path.startsWith("/") ? path : `/${path}`;
  const qIndex = normalized.indexOf("?");
  const pathname = qIndex >= 0 ? normalized.slice(0, qIndex) : normalized;
  const search = qIndex >= 0 ? normalized.slice(qIndex) : "";
  const bare = stripLocalePrefix(pathname);
  const withSlash = bare.endsWith("/") ? bare : `${bare}/`;
  if (withSlash === "/") {
    return `/${lang}/${search}`;
  }
  return `/${lang}${withSlash}${search}`;
}

export function swapLocalePath(pathname: string, search: string, newLang: StoreLocaleCode): string {
  const bare = stripLocalePrefix(pathname);
  const href = localePath(newLang, bare === "/" ? "/" : bare);
  return search ? `${href}?${search.replace(/^\?/, "")}` : href;
}

export function detectPreferredLocale(acceptLanguage: string | null): StoreLocaleCode {
  if (!acceptLanguage) {
    return DEFAULT_CONTENT_LOCALE;
  }
  const first = acceptLanguage.split(",")[0]?.trim().toLowerCase() ?? "";
  if (first.startsWith("ar")) {
    return "ar";
  }
  return DEFAULT_CONTENT_LOCALE;
}
