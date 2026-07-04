import { component$ } from "@builder.io/qwik";
import { Link, routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import { ProductCard } from "~/components/catalog/product-card";
import { JsonLd } from "~/components/seo/json-ld";
import { fetchProductsPage } from "~/lib/api";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { canonicalUrl, hreflangLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { useSiteSettings } from "~/routes/[lang]/layout";

export const useHomeProducts = routeLoader$(async ({ params }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  try {
    return await fetchProductsPage({ per_page: 8, in_stock_only: true }, locale);
  } catch {
    return { data: [], meta: { current_page: 1, last_page: 1, per_page: 8, total: 0 } };
  }
});

export default component$(() => {
  const settings = useSiteSettings();
  const products = useHomeProducts();
  const { locale } = useI18n();

  return (
    <>
      <JsonLd
        data={{
          "@context": "https://schema.org",
          "@type": "WebSite",
          name: settings.value.business_name,
          url: typeof window !== "undefined" ? window.location.origin : undefined,
          potentialAction: {
            "@type": "SearchAction",
            target: `${localePath(locale, "/products")}?q={search_term_string}`,
            "query-input": "required name=search_term_string",
          },
        }}
      />

      <section>
        <h1 class="page-title">
          {tStatic(locale, "home.welcome", { businessName: settings.value.business_name })}
        </h1>
        <p style={{ color: "var(--gs-muted)", marginBottom: "2rem" }}>{tStatic(locale, "home.tagline")}</p>

        <div
          style={{
            display: "flex",
            justifyContent: "space-between",
            alignItems: "center",
            marginBottom: "1rem",
          }}
        >
          <h2 style={{ margin: 0, fontSize: "1.25rem" }}>{tStatic(locale, "home.featured")}</h2>
          <Link href={localePath(locale, "/products")} class="home-all-products-link">
            {tStatic(locale, "footer.allProducts")}
          </Link>
        </div>

        {products.value.data.length === 0 ? (
          <div class="empty-state">{tStatic(locale, "catalog.noProducts")}</div>
        ) : (
          <div class="product-grid">
            {products.value.data.map((product) => (
              <ProductCard key={product.id} product={product} settings={settings.value} />
            ))}
          </div>
        )}
      </section>
    </>
  );
});

export const head: DocumentHead = ({ resolveValue, url, params }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = isSupportedLocale(params.lang) ? params.lang : "en";
  const title = `${settings.business_name} — ${tStatic(lang, "nav.shop")}`;
  const description = tStatic(lang, "seo.shopDescription", { businessName: settings.business_name });

  return withStorefrontThemeHead(
    {
      title,
      meta: [
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:type", content: "website" },
        { name: "twitter:card", content: "summary_large_image" },
      ],
      links: [
        { rel: "canonical", href: canonicalUrl(url.origin, "/", lang) },
        ...hreflangLinks(url.origin, "/", lang),
      ],
    },
    settings,
  );
};
