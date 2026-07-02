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

export function localePath(lang: StoreLocaleCode | string, path = "/"): string {
  const normalized = path.startsWith("/") ? path : `/${path}`;
  const bare = stripLocalePrefix(normalized);
  if (bare === "/") {
    return `/${lang}/`;
  }
  return `/${lang}${bare}`;
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
