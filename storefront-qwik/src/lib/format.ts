import type { StoreSettings } from "./types";

/** Format a price using storefront currency settings. */
export function formatPrice(
  amount: number,
  currency: StoreSettings["currency"],
): string {
  const fixed = amount.toFixed(currency.precision);
  return currency.symbol_placement === "before"
    ? `${currency.symbol}${fixed}`
    : `${fixed}${currency.symbol}`;
}

/** Product URL slug segment (prefers slug, falls back to id). */
export function productPath(product: { id: number; slug: string | null }): string {
  return `/products/${product.slug || product.id}`;
}
