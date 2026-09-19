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

/** Categories intentionally omitted from the Shop mega / drawer. */
export function isExcludedShopNavCategory(category: {
  name?: string | null;
  slug?: string | null;
}): boolean {
  const name = (category.name || "").trim().toLowerCase();
  const slug = (category.slug || "").trim().toLowerCase();
  const hay = `${slug} ${name}`;
  if (name === "generic" || slug === "generic" || name === "عام") {
    return true;
  }
  if (
    /maintenance[\s_-]*accessor/i.test(hay) ||
    name.includes("إكسسوارات الصيانة") ||
    name.includes("اكسسوارات الصيانة")
  ) {
    return true;
  }
  return false;
}

export function consoleNavCategories<T extends { name: string; slug?: string | null }>(
  categories: T[],
): T[] {
  return categories.filter(
    (category) => !isDigitalCatalogCategory(category) && !isExcludedShopNavCategory(category),
  );
}
