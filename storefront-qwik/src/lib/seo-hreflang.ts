import { STORE_LOCALES } from "~/lib/i18n/config";
import { localePath, swapLocalePath } from "~/lib/i18n/paths";

export interface HreflangLink {
  rel: "alternate";
  hreflang: string;
  href: string;
}

/** Build hreflang alternate link tags for a storefront path (without locale prefix). */
export function hreflangLinks(
  origin: string,
  pathWithoutLocale: string,
  _currentLocale: string,
): HreflangLink[] {
  const base = origin.replace(/\/$/, "");
  const links: HreflangLink[] = STORE_LOCALES.map((loc) => ({
    rel: "alternate",
    hreflang: loc.code,
    href: `${base}${localePath(loc.code, pathWithoutLocale)}`,
  }));

  links.push({
    rel: "alternate",
    hreflang: "x-default",
    href: `${base}${localePath("en", pathWithoutLocale)}`,
  });

  return links;
}

/** Canonical URL for the active locale. */
export function canonicalUrl(
  origin: string,
  pathWithoutLocale: string,
  locale: string,
): string {
  const base = origin.replace(/\/$/, "");
  return `${base}${localePath(locale, pathWithoutLocale)}`;
}

/** Swap locale on a full URL path (e.g. /en/products → /ar/products). */
export { swapLocalePath };
