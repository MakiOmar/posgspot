import { component$ } from "@builder.io/qwik";
import { Link, routeLoader$, useLocation, type DocumentHead } from "@builder.io/qwik-city";
import { ProductCard } from "~/components/catalog/product-card";
import { ProductListToolbar } from "~/components/catalog/product-list-toolbar";
import { ChevronLeftIcon, ChevronRightIcon } from "~/components/icons";
import { fetchBrand, fetchProductsPage } from "~/lib/api";
import { parseProductListFilters } from "~/lib/catalog-filters";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import type { Brand } from "~/lib/types";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

interface BrandPageData {
  brand: Brand | null;
  data: Awaited<ReturnType<typeof fetchProductsPage>>["data"];
  meta: Awaited<ReturnType<typeof fetchProductsPage>>["meta"];
}

export const useBrandPage = routeLoader$(
  async ({ params, query, status }): Promise<BrandPageData> => {
    const slug = params.slug;
    const locale = isSupportedLocale(params.lang) ? params.lang : "en";
    const filters = parseProductListFilters(query);

    try {
      const [brandRes, products] = await Promise.all([
        fetchBrand(slug, locale),
        fetchProductsPage(
          {
            page: filters.page,
            per_page: 20,
            brand_slug: slug,
            in_stock_only: filters.inStockOnly,
            sort: filters.sort,
          },
          locale,
        ),
      ]);

      return { brand: brandRes.data, data: products.data, meta: products.meta };
    } catch {
      status(404);
      return {
        brand: null,
        data: [],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
      };
    }
  },
);

export default component$(() => {
  const settings = useSiteSettings();
  const pageData = useBrandPage();
  const loc = useLocation();
  const filters = parseProductListFilters(loc.url.searchParams);
  const { brand, data, meta } = pageData.value;
  const lang = (loc.params.lang || "en") as "en" | "ar";

  const listKey = loc.url.search || "?";

  const buildPageUrl = (page: number) => {
    const params = new URLSearchParams(loc.url.searchParams);
    if (page <= 1) {
      params.delete("page");
    } else {
      params.set("page", String(page));
    }
    const qs = params.toString();
    return qs ? `${loc.url.pathname}?${qs}` : loc.url.pathname;
  };

  if (!brand) {
    return (
      <section>
        <h1 class="page-title">{tStatic(lang, "catalog.brandNotFound")}</h1>
        <div class="empty-state">
          {tStatic(lang, "catalog.brandNotFoundBody")}{" "}
          <Link href={localePath(lang, "/brands")}>{tStatic(lang, "nav.brands")}</Link>
        </div>
      </section>
    );
  }

  return (
    <section>
      <h1 class="page-title">{brand.name}</h1>

      <ProductListToolbar basePath={loc.url.pathname} filters={filters} />

      {data.length === 0 ? (
        <div class="empty-state" key={listKey}>
          {tStatic(lang, "catalog.noProductsInBrand")}
        </div>
      ) : (
        <div key={listKey}>
          <p class="footer-muted" style={{ marginBottom: "1rem" }}>
            {tStatic(lang, "catalog.productCount", { count: meta.total })}
          </p>
          <div class="product-grid">
            {data.map((product) => (
              <ProductCard key={product.id} product={product} settings={settings.value} />
            ))}
          </div>

          {meta.last_page > 1 ? (
            <nav class="pagination" aria-label={tStatic(lang, "a11y.pagination")}>
              {meta.current_page > 1 ? (
                <Link href={buildPageUrl(meta.current_page - 1)} class="footer-contact">
                  <ChevronLeftIcon size={16} />
                  {tStatic(lang, "common.prev")}
                </Link>
              ) : null}
              <span class="active">
                {tStatic(lang, "common.pageOf", {
                  current: meta.current_page,
                  last: meta.last_page,
                })}
              </span>
              {meta.current_page < meta.last_page ? (
                <Link href={buildPageUrl(meta.current_page + 1)} class="footer-contact">
                  {tStatic(lang, "common.next")}
                  <ChevronRightIcon size={16} />
                </Link>
              ) : null}
            </nav>
          ) : null}
        </div>
      )}
    </section>
  );
});

export const head: DocumentHead = ({ resolveValue, params, url }) => {
  const settings = resolveValue(useSiteSettings);
  const pageData = resolveValue(useBrandPage);
  const lang = resolveValue(useLangParam);
  const name = pageData.brand?.name || params.slug;
  const title = `${name} — ${settings.business_name}`;
  const description = tStatic(lang, "seo.brandDescription", {
    name,
    businessName: settings.business_name,
  });
  const path = `/brands/${encodeURIComponent(params.slug)}`;

  return {
    title,
    meta: [
      { name: "description", content: description },
      { property: "og:title", content: title },
      { property: "og:description", content: description },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary" },
      ...(pageData.brand ? [] : [{ name: "robots", content: "noindex, follow" }]),
    ],
    links: pageData.brand ? publicSeoLinks(url.origin, path, lang) : [],
  };
};
