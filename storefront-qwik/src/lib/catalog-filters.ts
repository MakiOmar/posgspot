/** Product listing sort values supported by GET /products. */
import type { StoreLocaleCode } from "./i18n/config";
import { tStatic } from "./i18n/context";

export type ProductSort = "name" | "price_asc" | "price_desc" | "newest";

export function getProductSortOptions(
  locale: StoreLocaleCode,
): { value: ProductSort; label: string }[] {
  return [
    { value: "name", label: tStatic(locale, "catalog.sortName") },
    { value: "price_asc", label: tStatic(locale, "catalog.sortPriceAsc") },
    { value: "price_desc", label: tStatic(locale, "catalog.sortPriceDesc") },
    { value: "newest", label: tStatic(locale, "catalog.sortNewest") },
  ];
}

export const PRODUCT_SORT_OPTIONS: { value: ProductSort; label: string }[] = [
  { value: "name", label: "Name (A–Z)" },
  { value: "price_asc", label: "Price: low to high" },
  { value: "price_desc", label: "Price: high to low" },
  { value: "newest", label: "Newest" },
];

const SORT_VALUES = new Set<string>(PRODUCT_SORT_OPTIONS.map((o) => o.value));

export function parseProductSort(value: string | null | undefined): ProductSort {
  if (value && SORT_VALUES.has(value)) {
    return value as ProductSort;
  }
  return "name";
}

export interface ProductListFilters {
  sort: ProductSort;
  inStockOnly: boolean;
  q: string;
  categoryId: string;
  page: number;
}

export function parseProductListFilters(
  searchParams: URLSearchParams,
): ProductListFilters {
  return {
    page: Math.max(1, Number(searchParams.get("page") || 1)),
    q: (searchParams.get("q") || "").trim(),
    categoryId: searchParams.get("category_id") || "",
    inStockOnly: searchParams.get("in_stock_only") === "1",
    sort: parseProductSort(searchParams.get("sort")),
  };
}

/** Build a listing URL preserving filters; resets page unless `page` is passed. */
export function productListUrl(
  basePath: string,
  searchParams: URLSearchParams,
  changes: Record<string, string | null | undefined> = {},
): string {
  const params = new URLSearchParams(searchParams);

  for (const [key, value] of Object.entries(changes)) {
    if (value === null || value === undefined || value === "") {
      params.delete(key);
    } else {
      params.set(key, value);
    }
  }

  if (!("page" in changes)) {
    params.delete("page");
  }

  const qs = params.toString();
  // Keep trailing slash so SPA query navigations do not hit a redirect.
  const path = basePath.endsWith("/") || basePath.includes("?") ? basePath : `${basePath}/`;
  return qs ? `${path}?${qs}` : path;
}

export function hasActiveProductFilters(filters: ProductListFilters): boolean {
  return Boolean(filters.q || filters.inStockOnly || filters.categoryId || filters.sort !== "name");
}
