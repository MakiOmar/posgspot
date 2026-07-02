import { component$ } from "@builder.io/qwik";
import { Link, routeLoader$, useLocation, type DocumentHead } from "@builder.io/qwik-city";
import { ProductCard } from "~/components/catalog/product-card";
import { ProductListToolbar } from "~/components/catalog/product-list-toolbar";
import { ChevronLeftIcon, ChevronRightIcon } from "~/components/icons";
import { fetchCategory, fetchProductsPage } from "~/lib/api";
import { parseProductListFilters } from "~/lib/catalog-filters";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { Category } from "~/lib/types";
import { useSiteSettings } from "~/routes/layout";

interface CategoryPageData {
  category: Category | null;
  data: Awaited<ReturnType<typeof fetchProductsPage>>["data"];
  meta: Awaited<ReturnType<typeof fetchProductsPage>>["meta"];
}

export const useCategoryPage = routeLoader$(
  async ({ params, query, status }): Promise<CategoryPageData> => {
    const slug = params.slug;
    const locale = isSupportedLocale(params.lang) ? params.lang : "en";
    const filters = parseProductListFilters(query);

    try {
      const [categoryRes, products] = await Promise.all([
        fetchCategory(slug, locale),
        fetchProductsPage({
          page: filters.page,
          per_page: 20,
          category_slug: slug,
          in_stock_only: filters.inStockOnly,
          sort: filters.sort,
        }, locale),
      ]);

      return { category: categoryRes.data, data: products.data, meta: products.meta };
    } catch {
      // Unknown slug or upstream failure: render a 404-friendly empty state.
      status(404);
      return {
        category: null,
        data: [],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
      };
    }
  },
);

export default component$(() => {
  const settings = useSiteSettings();
  const pageData = useCategoryPage();
  const loc = useLocation();
  const filters = parseProductListFilters(loc.url.searchParams);
  const { category, data, meta } = pageData.value;

  const buildPageUrl = (page: number) => {
    const params = new URLSearchParams(loc.url.searchParams);
    params.set("page", String(page));
    return `${loc.url.pathname}?${params.toString()}`;
  };

  if (!category) {
    return (
      <section>
        <h1 class="page-title">Category not found</h1>
        <div class="empty-state">
          This category doesn’t exist or is no longer available.{" "}
          <Link href={localePath(loc.params.lang || "en", "/products")}>
            {tStatic((loc.params.lang || "en") as "en" | "ar", "footer.allProducts")}
          </Link>
        </div>
      </section>
    );
  }

  return (
    <section>
      {/* Category heading driven by the resolved POS category name. */}
      <h1 class="page-title">{category.name}</h1>

      <ProductListToolbar basePath={loc.url.pathname} filters={filters} />

      {data.length === 0 ? (
        <div class="empty-state">No products in this category yet.</div>
      ) : (
        <>
          <p class="footer-muted" style={{ marginBottom: "1rem" }}>
            {meta.total} product{meta.total === 1 ? "" : "s"}
          </p>
          <div class="product-grid">
            {data.map((product) => (
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

export const head: DocumentHead = ({ resolveValue, params }) => {
  const settings = resolveValue(useSiteSettings);
  const pageData = resolveValue(useCategoryPage);
  const name = pageData.category?.name || params.slug;
  const title = `${name} — ${settings.business_name}`;
  const description = `Shop ${name} at ${settings.business_name}.`;

  return {
    title,
    meta: [
      { name: "description", content: description },
      { property: "og:title", content: title },
      { property: "og:description", content: description },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary" },
      ...(pageData.category ? [] : [{ name: "robots", content: "noindex, follow" }]),
    ],
  };
};
