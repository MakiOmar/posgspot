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
import { PromoBannerSection } from "~/components/home/promo-banner-section";
import { PromoTiles } from "~/components/home/promo-tiles";
import { TopCategories } from "~/components/home/top-categories";
import { TrustBadges } from "~/components/home/trust-badges";
import { JsonLd } from "~/components/seo/json-ld";
import {
  fetchBrands,
  fetchHomepage,
  fetchHomepageShelves,
  fetchProductsPage,
} from "~/lib/api";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type {
  Brand,
  HomepageCategoryShelf,
  HomepageHeroSlide,
  HomepagePromoBanner,
  HomepagePromoTile,
  HomepageSection,
  HomepageTrustBadge,
  ProductSummary,
} from "~/lib/types";
import { useNavCategories, useSiteSettings } from "~/routes/[lang]/layout";

const emptyPage = (perPage: number) => ({
  data: [] as ProductSummary[],
  meta: { current_page: 1, last_page: 1, per_page: perPage, total: 0 },
});

function sectionSettingNumber(settings: Record<string, unknown>, key: string, fallback: number): number {
  const raw = settings[key];
  const n = typeof raw === "number" ? raw : Number(raw);
  return Number.isFinite(n) && n > 0 ? n : fallback;
}

function sectionSettingBool(settings: Record<string, unknown>, key: string, fallback: boolean): boolean {
  const raw = settings[key];
  if (typeof raw === "boolean") {
    return raw;
  }
  if (raw === "1" || raw === "true") {
    return true;
  }
  if (raw === "0" || raw === "false") {
    return false;
  }
  return fallback;
}

export const useHomepageSections = routeLoader$(async ({ params }): Promise<HomepageSection[]> => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  try {
    const { data } = await fetchHomepage(locale);
    return Array.isArray(data?.sections) ? data.sections : [];
  } catch {
    return [];
  }
});

export const useHomepageCatalog = routeLoader$(
  async ({
    params,
    resolveValue,
  }): Promise<{
    featured: ProductSummary[];
    bestsellers: ProductSummary[];
    brands: Brand[];
    shelves: Array<{ shelf: HomepageCategoryShelf; products: ProductSummary[] }>;
    /** Products for selected `category_shelf` sections, keyed by section id. */
    categoryShelfProducts: Record<string, ProductSummary[]>;
  }> => {
    const locale = isSupportedLocale(params.lang) ? params.lang : "en";
    const sections = await resolveValue(useHomepageSections);

    const featuredSec = sections.find((s) => s.type === "featured_products");
    const bestSec = sections.find((s) => s.type === "bestsellers");
    const brandSec = sections.find((s) => s.type === "brand_slider");
    const shelfSec = sections.find((s) => s.type === "category_shelves");
    const categoryShelfSecs = sections.filter((s) => s.type === "category_shelf");

    const featuredPerPage = featuredSec
      ? sectionSettingNumber(featuredSec.settings, "per_page", 8)
      : 8;
    const bestPerPage = bestSec ? sectionSettingNumber(bestSec.settings, "per_page", 6) : 6;
    const bestInStock = bestSec
      ? sectionSettingBool(bestSec.settings, "in_stock_only", true)
      : true;
    const brandLimit = brandSec ? sectionSettingNumber(brandSec.settings, "limit", 24) : 24;
    const shelfLimit = shelfSec ? sectionSettingNumber(shelfSec.settings, "limit", 6) : 6;
    const productsPerShelf = shelfSec
      ? sectionSettingNumber(shelfSec.settings, "products_per_shelf", 6)
      : 6;

    const [featuredPage, bestPage, brands, shelfRows, categoryShelfProductEntries] =
      await Promise.all([
        featuredSec
          ? fetchProductsPage({ featured: 1, per_page: featuredPerPage }, locale).catch(() =>
              emptyPage(featuredPerPage),
            )
          : Promise.resolve(emptyPage(0)),
        bestSec
          ? fetchProductsPage(
              {
                sort: "bestsellers",
                per_page: bestPerPage,
                ...(bestInStock ? { in_stock_only: true } : {}),
              },
              locale,
            ).catch(() => emptyPage(bestPerPage))
          : Promise.resolve(emptyPage(0)),
        brandSec
          ? fetchBrands(locale)
              .then((r) => (r.data ?? []).slice(0, brandLimit))
              .catch(() => [] as Brand[])
          : Promise.resolve([] as Brand[]),
        shelfSec
          ? (async () => {
              let shelves: HomepageCategoryShelf[] = [];
              try {
                const { data } = await fetchHomepageShelves(locale);
                shelves = (data ?? []).slice(0, shelfLimit);
              } catch {
                shelves = [];
              }
              return Promise.all(
                shelves.map(async (shelf) => {
                  if (!shelf.slug) {
                    return { shelf, products: [] as ProductSummary[] };
                  }
                  try {
                    const page = await fetchProductsPage(
                      { category_slug: shelf.slug, per_page: productsPerShelf },
                      locale,
                    );
                    return { shelf, products: page.data };
                  } catch {
                    return { shelf, products: [] as ProductSummary[] };
                  }
                }),
              );
            })()
          : Promise.resolve([]),
        Promise.all(
          categoryShelfSecs.map(async (sec) => {
            const shelf = sec.settings.shelf as HomepageCategoryShelf | undefined;
            const perPage = sectionSettingNumber(sec.settings, "products_per_shelf", 6);
            if (!shelf?.slug) {
              return [sec.id, [] as ProductSummary[]] as const;
            }
            try {
              const page = await fetchProductsPage(
                { category_slug: shelf.slug, per_page: perPage },
                locale,
              );
              return [sec.id, page.data] as const;
            } catch {
              return [sec.id, [] as ProductSummary[]] as const;
            }
          }),
        ),
      ]);

    const categoryShelfProducts: Record<string, ProductSummary[]> = {};
    for (const [id, products] of categoryShelfProductEntries) {
      categoryShelfProducts[id] = products;
    }

    return {
      featured: featuredPage.data,
      bestsellers: bestPage.data,
      brands,
      shelves: shelfRows,
      categoryShelfProducts,
    };
  },
);

export default component$(() => {
  const settings = useSiteSettings();
  const sections = useHomepageSections();
  const catalog = useHomepageCatalog();
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

      {sections.value.map((section) => {
        switch (section.type) {
          case "hero_slider": {
            const slides = (section.settings.slides as HomepageHeroSlide[] | undefined) ?? [];
            return <HeroSlider key={section.id} slides={slides} />;
          }
          case "promo_tiles": {
            const tiles = (section.settings.tiles as HomepagePromoTile[] | undefined) ?? [];
            return <PromoTiles key={section.id} tiles={tiles} />;
          }
          case "video":
            return (
              <HomeVideo
                key={section.id}
                source={String(section.settings.source ?? "self")}
                src={String(section.settings.url ?? "")}
                embedUrl={
                  section.settings.embed_url == null ? null : String(section.settings.embed_url)
                }
                poster={String(section.settings.poster ?? "")}
                title={String(section.settings.title ?? "")}
              />
            );
          case "trust_badges": {
            const items = (section.settings.items as HomepageTrustBadge[] | undefined) ?? [];
            return <TrustBadges key={section.id} items={items} />;
          }
          case "promo_banners":
            return (
              <PromoBanners
                key={section.id}
                banners={settings.value.banners ?? []}
                placement="home"
              />
            );
          case "promo_banner": {
            const banner = section.settings as unknown as HomepagePromoBanner;
            if (!banner.logo_url && !banner.image_url && !banner.top_title && !banner.main_title) {
              return null;
            }
            return <PromoBannerSection key={section.id} banner={banner} />;
          }
          case "featured_products":
            return (
              <FeaturedSlider
                key={section.id}
                products={catalog.value.featured}
                settings={settings.value}
              />
            );
          case "top_categories":
            return (
              <TopCategories
                key={section.id}
                categories={categoriesLoad.value.items}
                limit={sectionSettingNumber(section.settings, "limit", 8)}
              />
            );
          case "category_shelves":
            return (
              <div key={section.id}>
                {catalog.value.shelves.map(({ shelf, products }) => (
                  <CategoryShelf
                    key={shelf.id}
                    shelf={shelf}
                    products={products}
                    settings={settings.value}
                  />
                ))}
              </div>
            );
          case "category_shelf": {
            const shelf = section.settings.shelf as HomepageCategoryShelf | undefined;
            if (!shelf) {
              return null;
            }
            return (
              <CategoryShelf
                key={section.id}
                shelf={shelf}
                products={catalog.value.categoryShelfProducts[section.id] ?? []}
                settings={settings.value}
              />
            );
          }
          case "brand_slider":
            return (
              <BrandSlider
                key={section.id}
                brands={catalog.value.brands}
                limit={sectionSettingNumber(section.settings, "limit", 24)}
              />
            );
          case "bestsellers":
            return (
              <BestSelling
                key={section.id}
                products={catalog.value.bestsellers}
                settings={settings.value}
                style={String(section.settings.style ?? "grid")}
              />
            );
          case "recently_viewed":
            return (
              <RecentlyViewed
                key={section.id}
                settings={settings.value}
                headingId="home-recently-viewed-heading"
                limit={sectionSettingNumber(section.settings, "limit", 8)}
              />
            );
          default:
            return null;
        }
      })}
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
