import type { StoreSettings } from "./types";
import { localeDefinition, type StoreLocaleCode } from "./i18n/config";
import { localePath } from "./i18n/paths";

/** Format a price using storefront currency settings and locale. */
export function formatPrice(
  amount: number,
  currency: StoreSettings["currency"] | null | undefined,
  locale: StoreLocaleCode | string = "en",
): string {
  const safe = currency ?? {
    code: "EGP",
    symbol: "L.E.",
    precision: 2,
    symbol_placement: "before" as const,
  };
  const intl = localeDefinition(locale).intl;
  const formatted = new Intl.NumberFormat(intl, {
    minimumFractionDigits: safe.precision,
    maximumFractionDigits: safe.precision,
  }).format(amount);

  return safe.symbol_placement === "before"
    ? `${safe.symbol}${formatted}`
    : `${formatted} ${safe.symbol}`;
}

/** Format integers with locale grouping. */
export function formatNumber(value: number, locale: StoreLocaleCode | string = "en"): string {
  return new Intl.NumberFormat(localeDefinition(locale).intl).format(value);
}

/** Product URL with locale prefix. */
export function productPath(
  product: { id: number; slug: string | null },
  locale: StoreLocaleCode | string = "en",
): string {
  return localePath(locale, `/products/${product.slug || product.id}`);
}
