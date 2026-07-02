import { $, component$ } from "@builder.io/qwik";
import { Link, useLocation, useNavigate } from "@builder.io/qwik-city";
import {
  hasActiveProductFilters,
  PRODUCT_SORT_OPTIONS,
  productListUrl,
  type ProductListFilters,
} from "~/lib/catalog-filters";

interface ProductListToolbarProps {
  basePath: string;
  filters: ProductListFilters;
}

export const ProductListToolbar = component$<ProductListToolbarProps>(({ basePath, filters }) => {
  const loc = useLocation();
  const nav = useNavigate();

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
    const href = productListUrl(basePath, loc.url.searchParams, { sort: value });
    await nav(href);
  });

  return (
    <div class="product-list-toolbar">
      <div class="product-list-toolbar__filters">
        <label class="product-list-toolbar__field">
          <span class="footer-muted">Sort by</span>
          <select
            class="product-list-toolbar__select"
            aria-label="Sort products"
            value={filters.sort}
            onChange$={onSortChange$}
          >
            {PRODUCT_SORT_OPTIONS.map((option) => (
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
          {filters.inStockOnly ? "In stock only ✓" : "In stock only"}
        </Link>

        {hasActiveProductFilters(filters) ? (
          <Link href={clearHref} class="link-accent product-list-toolbar__clear">
            Clear filters
          </Link>
        ) : null}
      </div>

      {filters.q ? (
        <p class="footer-muted product-list-toolbar__search">
          Showing results for <strong>{filters.q}</strong>
        </p>
      ) : null}
    </div>
  );
});
