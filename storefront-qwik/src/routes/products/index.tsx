import { component$ } from "@builder.io/qwik";
import { Link, routeLoader$, useLocation, type DocumentHead } from "@builder.io/qwik-city";
import { ProductCard } from "~/components/catalog/product-card";
import { ProductListToolbar } from "~/components/catalog/product-list-toolbar";
import { ChevronLeftIcon, ChevronRightIcon } from "~/components/icons";
import { fetchProductsPage } from "~/lib/api";
import { parseProductListFilters } from "~/lib/catalog-filters";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { useSiteSettings } from "~/routes/layout";

export const useProductList = routeLoader$(async ({ query }) => {
  const filters = parseProductListFilters(query);

  try {
    return await fetchProductsPage({
      page: filters.page,
      per_page: 20,
      q: filters.q,
      category_id: filters.categoryId,
      in_stock_only: filters.inStockOnly,
      sort: filters.sort,
    });
  } catch {
    return {
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
    };
  }
});

export default component$(() => {
  const settings = useSiteSettings();
  const list = useProductList();
  const loc = useLocation();
  const filters = parseProductListFilters(loc.url.searchParams);
  const { meta } = list.value;

  const buildPageUrl = (page: number) => {
    const params = new URLSearchParams(loc.url.searchParams);
    params.set("page", String(page));
    return `/products?${params.toString()}`;
  };

  return (
    <section>
      <h1 class="page-title">Shop</h1>

      <ProductListToolbar basePath="/products" filters={filters} />

      {list.value.data.length === 0 ? (
        <div class="empty-state">No products match your filters.</div>
      ) : (
        <>
          <p class="footer-muted" style={{ marginBottom: "1rem" }}>
            {meta.total} product{meta.total === 1 ? "" : "s"}
          </p>
          <div class="product-grid">
            {list.value.data.map((product) => (
              <ProductCard key={product.id} product={product} settings={settings.value} />
            ))}
          </div>

          {meta.last_page > 1 ? (
            <nav class="pagination" aria-label="Pagination">
              {meta.current_page > 1 ? (
                <Link href={buildPageUrl(meta.current_page - 1)} class="footer-contact">
                  <ChevronLeftIcon size={16} />
                  Prev
                </Link>
              ) : null}
              <span class="active">
                Page {meta.current_page} of {meta.last_page}
              </span>
              {meta.current_page < meta.last_page ? (
                <Link href={buildPageUrl(meta.current_page + 1)} class="footer-contact">
                  Next
                  <ChevronRightIcon size={16} />
                </Link>
              ) : null}
            </nav>
          ) : null}
        </>
      )}
    </section>
  );
});

export const head: DocumentHead = ({ resolveValue, url }) => {
  const settings = resolveValue(useSiteSettings);
  const q = url.searchParams.get("q");
  const title = q
    ? `Search: ${q} — ${settings.business_name}`
    : `Shop — ${settings.business_name}`;
  const description = q
    ? `Search results for "${q}" at ${settings.business_name}.`
    : `Browse gaming products at ${settings.business_name}.`;

  return withStorefrontThemeHead(
    {
      title,
      meta: [
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:type", content: "website" },
        { name: "twitter:card", content: "summary" },
        ...(q ? [{ name: "robots", content: "noindex, follow" }] : []),
      ],
    },
    settings,
  );
};
