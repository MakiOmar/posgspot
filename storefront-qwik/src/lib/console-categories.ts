/**
 * POS product categories that belong in the Consoles menu (not digital catalog).
 */
export function isDigitalCatalogCategory(category: {
  name?: string | null;
  slug?: string | null;
}): boolean {
  const hay = `${category.slug || ""} ${category.name || ""}`.toLowerCase();
  if (/(digital[\s_-]*games?|gift[\s_-]*cards?|giftcards?)/i.test(hay)) {
    return true;
  }
  const name = (category.name || "").trim();
  return name.includes("ألعاب رقمية") || name.includes("بطاقات الهدايا");
}

export function consoleNavCategories<T extends { name: string; slug?: string | null }>(
  categories: T[],
): T[] {
  return categories.filter((category) => !isDigitalCatalogCategory(category));
}
