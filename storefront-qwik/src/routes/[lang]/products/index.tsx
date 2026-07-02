import { component$ } from "@builder.io/qwik";
import { Link, routeLoader$, useLocation, type DocumentHead } from "@builder.io/qwik-city";
import { ProductCard } from "~/components/catalog/product-card";
import { ProductListToolbar } from "~/components/catalog/product-list-toolbar";
import { ChevronLeftIcon, ChevronRightIcon } from "~/components/icons";
import { fetchProductsPage } from "~/lib/api";
import { parseProductListFilters } from "~/lib/catalog-filters";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { canonicalUrl, hreflangLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { useSiteSettings } from "~/routes/layout";

export const useProductList = routeLoader$(async ({ query, params }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  const filters = parseProductListFilters(query);

  try {
    return await fetchProductsPage({
      page: filters.page,
      per_page: 20,
      q: filters.q,
      category_id: filters.categoryId,
      in_stock_only: filters.inStockOnly,
      sort: filters.sort,
    }, locale);
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
  const lang = loc.params.lang || "en";
  const productsBase = localePath(lang, "/products");

  const buildPageUrl = (page: number) => {
    const params = new URLSearchParams(loc.url.searchParams);
    params.set("page", String(page));
    return `${productsBase}?${params.toString()}`;
  };

  return (
    <section>
      <h1 class="page-title">{tStatic(lang as "en" | "ar", "nav.shop")}</h1>

      <ProductListToolbar basePath={productsBase} filters={filters} />

      {list.value.data.length === 0 ? (
        <div class="empty-state">{tStatic(lang as "en" | "ar", "catalog.noProducts")}</div>
      ) : (
        <>
          <p class="footer-muted" style={{ marginBottom: "1rem" }}>
            {tStatic(lang as "en" | "ar", "catalog.productCount", { count: meta.total })}
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
                  {tStatic(lang as "en" | "ar", "common.prev")}
                </Link>
              ) : null}
              <span class="active">
                {tStatic(lang as "en" | "ar", "common.pageOf", {
                  current: meta.current_page,
                  last: meta.last_page,
                })}
              </span>
              {meta.current_page < meta.last_page ? (
                <Link href={buildPageUrl(meta.current_page + 1)} class="footer-contact">
                  {tStatic(lang as "en" | "ar", "common.next")}
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

export const head: DocumentHead = ({ resolveValue, url, params }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = isSupportedLocale(params.lang) ? params.lang : "en";
  const q = url.searchParams.get("q");
  const title = q
    ? tStatic(lang, "seo.searchTitle", { query: q, businessName: settings.business_name })
    : tStatic(lang, "seo.shopTitle", { businessName: settings.business_name });
  const description = q
    ? tStatic(lang, "seo.searchDescription", { query: q, businessName: settings.business_name })
    : tStatic(lang, "seo.shopDescription", { businessName: settings.business_name });

  const path = q ? `/products?q=${encodeURIComponent(q)}` : "/products";

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
      links: [
        { rel: "canonical", href: canonicalUrl(url.origin, path, lang) },
        ...hreflangLinks(url.origin, path, lang),
      ],
    },
    settings,
  );
};
