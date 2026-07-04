import { $, component$ } from "@builder.io/qwik";
import { Link, useLocation, useNavigate } from "@builder.io/qwik-city";
import {
  getProductSortOptions,
  hasActiveProductFilters,
  productListUrl,
  type ProductListFilters,
} from "~/lib/catalog-filters";
import { tStatic, useI18n } from "~/lib/i18n/context";

interface ProductListToolbarProps {
  basePath: string;
  filters: ProductListFilters;
}

export const ProductListToolbar = component$<ProductListToolbarProps>(({ basePath, filters }) => {
  const loc = useLocation();
  const nav = useNavigate();
  const { locale } = useI18n();
  const sortOptions = getProductSortOptions(locale);

  const inStockHref = productListUrl(basePath, loc.url.searchParams, {
    in_stock_only: filters.inStockOnly ? null : "1",
  });

  const clearHref = productListUrl(basePath, loc.url.searchParams, {
    q: null,
    in_stock_only: null,
    category_id: null,
    sort: null,
    page: null,
  });

  const onSortChange$ = $(async (event: Event) => {
    const value = (event.target as HTMLSelectElement).value;
    const href = productListUrl(basePath, loc.url.searchParams, {
      sort: value === "default" ? null : value,
    });
    await nav(href);
  });

  return (
    <div class="product-list-toolbar">
      <div class="product-list-toolbar__filters">
        <label class="product-list-toolbar__field">
          <span class="footer-muted">{tStatic(locale, "catalog.sortBy")}</span>
          <select
            class="product-list-toolbar__select"
            aria-label={tStatic(locale, "catalog.sortBy")}
            value={filters.sort}
            onChange$={onSortChange$}
          >
            {sortOptions.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>

        <Link
          href={inStockHref}
          class={`btn btn-secondary product-list-toolbar__stock${filters.inStockOnly ? " is-active" : ""}`}
          aria-pressed={filters.inStockOnly}
        >
          {filters.inStockOnly ? tStatic(locale, "catalog.inStockActive") : tStatic(locale, "catalog.inStockOnly")}
        </Link>

        {hasActiveProductFilters(filters) ? (
          <Link href={clearHref} class="link-accent product-list-toolbar__clear">
            {tStatic(locale, "catalog.clearFilters")}
          </Link>
        ) : null}
      </div>

      {filters.q ? (
        <p class="footer-muted product-list-toolbar__search">
          {tStatic(locale, "catalog.showingResults")} <strong>{filters.q}</strong>
        </p>
      ) : null}
    </div>
  );
});
