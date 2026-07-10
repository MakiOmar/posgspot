import { component$ } from "@builder.io/qwik";
import { Link, routeLoader$, useLocation, type DocumentHead } from "@builder.io/qwik-city";
import { ProductCard } from "~/components/catalog/product-card";
import { RecentlyViewed } from "~/components/catalog/recently-viewed";
import { JsonLd } from "~/components/seo/json-ld";
import { fetchProductsPage } from "~/lib/api";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { useNavCategories, useSiteSettings } from "~/routes/[lang]/layout";

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
  const categoriesLoad = useNavCategories();
  const loc = useLocation();
  const { locale } = useI18n();
  const origin = loc.url.origin;

  const featuredCategories = categoriesLoad.value.items
    .filter((category) => Boolean(category.slug))
    .slice(0, 8);

  return (
    <>
      <JsonLd
        data={{
          "@context": "https://schema.org",
          "@type": "WebSite",
          name: settings.value.business_name,
          url: origin,
          potentialAction: {
            "@type": "SearchAction",
            target: `${origin}${localePath(locale, "/products")}?q={search_term_string}`,
            "query-input": "required name=search_term_string",
          },
        }}
      />

      <section class="home-hero">
        <div class="home-hero__inner">
          <p class="home-hero__kicker">{tStatic(locale, "home.kicker")}</p>
          <h1 class="home-hero__title">
            {tStatic(locale, "home.welcome", { businessName: settings.value.business_name })}
          </h1>
          <p class="home-hero__tagline">{tStatic(locale, "home.tagline")}</p>
          <div class="home-hero__actions">
            <Link href={localePath(locale, "/products")} class="btn btn-primary">
              {tStatic(locale, "home.shopNow")}
            </Link>
            <Link href={localePath(locale, "/contact")} class="btn btn-secondary">
              {tStatic(locale, "nav.contact")}
            </Link>
          </div>
        </div>
      </section>

      {featuredCategories.length > 0 ? (
        <section class="home-section">
          <div class="home-section__head">
            <h2 class="home-section__title">{tStatic(locale, "home.featuredCategories")}</h2>
            <Link href={localePath(locale, "/products")} class="home-all-products-link">
              {tStatic(locale, "footer.allProducts")}
            </Link>
          </div>
          <div class="home-category-grid">
            {featuredCategories.map((category) => (
              <Link
                key={category.id}
                href={localePath(locale, `/category/${encodeURIComponent(category.slug!)}`)}
                class="home-category-card"
              >
                <span class="home-category-card__name">{category.name}</span>
                <span class="home-category-card__cta" aria-hidden="true">
                  {tStatic(locale, "home.shopCategory")}
                </span>
              </Link>
            ))}
          </div>
        </section>
      ) : null}

      <section class="home-section">
        <div class="home-section__head">
          <h2 class="home-section__title">{tStatic(locale, "home.featured")}</h2>
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

      <RecentlyViewed
        settings={settings.value}
        headingId="home-recently-viewed-heading"
      />
    </>
  );
});

export const head: DocumentHead = ({ resolveValue, url, params }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = isSupportedLocale(params.lang) ? params.lang : "en";
  const title = tStatic(lang, "seo.homeTitle", { businessName: settings.business_name });
  const description = tStatic(lang, "seo.homeDescription", {
    businessName: settings.business_name,
  });
  const canonical = publicSeoLinks(url.origin, "/", lang)[0]?.href;

  return withStorefrontThemeHead(
    {
      title,
      meta: [
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:type", content: "website" },
        ...(canonical ? [{ property: "og:url", content: canonical }] : []),
        ...(settings.logo_url ? [{ property: "og:image", content: settings.logo_url }] : []),
        { name: "twitter:card", content: "summary_large_image" },
      ],
      links: publicSeoLinks(url.origin, "/", lang),
    },
    settings,
  );
};
