/**
 * Build a stable PDP path — catalog often has null slugs, so fall back to id.
 */
export function productPath(product: {
  id: number | string;
  slug?: string | null;
}): string {
  const slug = typeof product.slug === "string" ? product.slug.trim() : "";
  if (slug) {
    return `/products/${encodeURIComponent(slug)}`;
  }
  return `/products/${encodeURIComponent(String(product.id))}`;
}

/**
 * Normalize expo-router dynamic params (string | string[]).
 */
export function paramString(
  value: string | string[] | undefined,
): string | null {
  if (Array.isArray(value)) {
    const first = value[0];
    return first && String(first).trim() ? String(first).trim() : null;
  }
  if (value == null) {
    return null;
  }
  const trimmed = String(value).trim();
  if (!trimmed || trimmed === "undefined" || trimmed === "null") {
    return null;
  }
  return trimmed;
}
