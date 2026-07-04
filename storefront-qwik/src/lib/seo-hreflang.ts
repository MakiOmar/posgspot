import { STORE_LOCALES } from "~/lib/i18n/config";
import { localePath, swapLocalePath } from "~/lib/i18n/paths";

export interface HreflangLink {
  rel: "alternate";
  hreflang: string;
  href: string;
}

export interface CanonicalLink {
  rel: "canonical";
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

/** Canonical + hreflang link tags for public indexable pages. */
export function publicSeoLinks(
  origin: string,
  pathWithoutLocale: string,
  locale: string,
): Array<CanonicalLink | HreflangLink> {
  return [
    { rel: "canonical", href: canonicalUrl(origin, pathWithoutLocale, locale) },
    ...hreflangLinks(origin, pathWithoutLocale, locale),
  ];
}

export interface BreadcrumbJsonLdItem {
  name: string;
  /** Absolute URL when the crumb is a link (omit for the current page). */
  item?: string;
}

/** BreadcrumbList JSON-LD for public pages. */
export function breadcrumbListJsonLd(items: BreadcrumbJsonLdItem[]): Record<string, unknown> {
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: items.map((crumb, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: crumb.name,
      ...(crumb.item ? { item: crumb.item } : {}),
    })),
  };
}

/** Swap locale on a full URL path (e.g. /en/products → /ar/products). */
export { swapLocalePath };
