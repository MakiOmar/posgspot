import { component$ } from "@builder.io/qwik";
import { routeLoader$, useLocation, type DocumentHead } from "@builder.io/qwik-city";
import { PromoBanners } from "~/components/catalog/promo-banners";
import { RecentlyViewed } from "~/components/catalog/recently-viewed";
import { BestSelling } from "~/components/home/best-selling";
import { BrandSlider } from "~/components/home/brand-slider";
import { CategoryShelf } from "~/components/home/category-shelf";
import { FeaturedSlider } from "~/components/home/featured-slider";
import { HeroSlider } from "~/components/home/hero-slider";
import { HomeVideo } from "~/components/home/home-video";
import { PromoTiles } from "~/components/home/promo-tiles";
import { TopCategories } from "~/components/home/top-categories";
import { JsonLd } from "~/components/seo/json-ld";
import { fetchBrands, fetchProductsPage } from "~/lib/api";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type { Brand, HomepageCategoryShelf, ProductSummary } from "~/lib/types";
import { useNavCategories, useSiteSettings } from "~/routes/[lang]/layout";

const emptyPage = (perPage: number) => ({
  data: [] as ProductSummary[],
  meta: { current_page: 1, last_page: 1, per_page: perPage, total: 0 },
});

export const useFeaturedProducts = routeLoader$(async ({ params }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  try {
    return await fetchProductsPage({ featured: 1, per_page: 8, in_stock_only: true }, locale);
  } catch {
    return emptyPage(8);
  }
});

export const useBestSellingProducts = routeLoader$(async ({ params }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  try {
    return await fetchProductsPage({ sort: "bestsellers", per_page: 6, in_stock_only: true }, locale);
  } catch {
    return emptyPage(6);
  }
});

export const useHomeBrands = routeLoader$(async ({ params }): Promise<Brand[]> => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  try {
    const { data } = await fetchBrands(locale);
    return data ?? [];
  } catch {
    return [];
  }
});

export const useCategoryShelves = routeLoader$(
  async ({
    params,
    resolveValue,
  }): Promise<Array<{ shelf: HomepageCategoryShelf; products: ProductSummary[] }>> => {
    const locale = isSupportedLocale(params.lang) ? params.lang : "en";
    const settings = await resolveValue(useSiteSettings);
    const shelves = settings.homepage?.category_shelves ?? [];

    const rows = await Promise.all(
      shelves.map(async (shelf) => {
        if (!shelf.category_slug) {
          return { shelf, products: [] as ProductSummary[] };
        }
        try {
          const page = await fetchProductsPage(
            {
              category_slug: shelf.category_slug,
              per_page: 6,
              in_stock_only: true,
            },
            locale,
          );
          return { shelf, products: page.data };
        } catch {
          return { shelf, products: [] as ProductSummary[] };
        }
      }),
    );

    return rows;
  },
);

export default component$(() => {
  const settings = useSiteSettings();
  const featured = useFeaturedProducts();
  const bestsellers = useBestSellingProducts();
  const brands = useHomeBrands();
  const shelves = useCategoryShelves();
  const categoriesLoad = useNavCategories();
  const loc = useLocation();
  const { locale } = useI18n();
  const origin = loc.url.origin;

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

      <HeroSlider />
      <PromoTiles />
      <HomeVideo />

      <PromoBanners banners={settings.value.banners ?? []} placement="home" />

      <FeaturedSlider products={featured.value.data} settings={settings.value} />
      <TopCategories categories={categoriesLoad.value.items} />

      {shelves.value.map(({ shelf, products }, i) => (
        <CategoryShelf
          key={`${shelf.title}-${shelf.category_slug}-${i}`}
          shelf={shelf}
          products={products}
          settings={settings.value}
        />
      ))}

      <BrandSlider brands={brands.value} />
      <BestSelling products={bestsellers.value.data} settings={settings.value} />

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
