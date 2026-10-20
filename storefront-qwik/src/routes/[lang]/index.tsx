import { component$, type JSXOutput } from "@builder.io/qwik";
import { routeLoader$, useLocation, type DocumentHead } from "@builder.io/qwik-city";
import { PromoBanners } from "~/components/catalog/promo-banners";
import { RecentlyViewed } from "~/components/catalog/recently-viewed";
import { BestSelling } from "~/components/home/best-selling";
import { BrandSlider } from "~/components/home/brand-slider";
import { CategoryShelf } from "~/components/home/category-shelf";
import { FeaturedSlider } from "~/components/home/featured-slider";
import { HeroSlider } from "~/components/home/hero-slider";
import { HomeSections } from "~/components/home/home-sections";
import { HomeVideo } from "~/components/home/home-video";
import { PromoBannerSection } from "~/components/home/promo-banner-section";
import { PromoTiles } from "~/components/home/promo-tiles";
import { TopCategories } from "~/components/home/top-categories";
import { TrustBadges } from "~/components/home/trust-badges";
import { JsonLd } from "~/components/seo/json-ld";
import {
  API_BASE,
  fetchBrands,
  fetchHomepage,
  fetchHomepageShelves,
  fetchProductsPage,
} from "~/lib/api";
import { cachedHomepageCatalog, cachedHomepageSections } from "~/lib/ssr-shell-cache";
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

/** Hand-picked product ids for a category shelf; empty when the shelf is in automatic mode. */
function categoryShelfPickedIds(settings: Record<string, unknown>): number[] {
  if (settings.product_mode !== "selected" || !Array.isArray(settings.product_ids)) {
    return [];
  }
  return settings.product_ids.map((id) => Number(id)).filter((id) => Number.isInteger(id) && id > 0);
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

type HomepageCatalog = {
  featured: ProductSummary[];
  bestsellers: ProductSummary[];
  brands: Brand[];
  shelves: Array<{ shelf: HomepageCategoryShelf; products: ProductSummary[] }>;
  /** Products for selected `category_shelf` sections, keyed by section id. */
  categoryShelfProducts: Record<string, ProductSummary[]>;
};

export const useHomepageSections = routeLoader$(async ({ params }): Promise<HomepageSection[]> => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  try {
    return await cachedHomepageSections(locale, async () => {
      const { data } = await fetchHomepage(locale);
      return Array.isArray(data?.sections) ? data.sections : [];
    });
  } catch (err) {
    console.error(`[storefront] homepage loader failed (API_BASE=${API_BASE})`, err);
    return [];
  }
});

export const useHomepageCatalog = routeLoader$(
  async ({ params, resolveValue }): Promise<HomepageCatalog> => {
    const locale = isSupportedLocale(params.lang) ? params.lang : "en";
    const sections = await resolveValue(useHomepageSections);
    const { catalog } = await cachedHomepageCatalog(
      `${locale}:${homepageCatalogSignature(sections)}`,
      () => loadHomepageCatalog(sections, locale),
      (result) => result.degraded,
    );
    return catalog;
  },
);

const CATALOG_SECTION_TYPES = new Set([
  "featured_products",
  "bestsellers",
  "brand_slider",
  "category_shelves",
  "category_shelf",
]);

/**
 * Cache-key fragment for the sections that drive catalog fetches. A failed sections
 * load (empty list) or a homepage builder edit maps to a different key, so neither
 * can overwrite or reuse the catalog built for another configuration.
 */
function homepageCatalogSignature(sections: HomepageSection[]): string {
  const source = JSON.stringify(
    sections
      .filter((s) => CATALOG_SECTION_TYPES.has(s.type))
      .map((s) => [s.id, s.type, s.settings]),
  );
  let hash = 5381;
  for (let i = 0; i < source.length; i++) {
    hash = ((hash << 5) + hash + source.charCodeAt(i)) | 0;
  }
  return (hash >>> 0).toString(36);
}

type MarkFailed = <T>(fallback: T) => () => T;

/** Products for each `category_shelves` row (shelf list comes from the API). */
async function loadShelfRows(
  shelfSec: HomepageSection,
  locale: string,
  failed: MarkFailed,
): Promise<HomepageCatalog["shelves"]> {
  const shelfLimit = sectionSettingNumber(shelfSec.settings, "limit", 6);
  const productsPerShelf = sectionSettingNumber(shelfSec.settings, "products_per_shelf", 6);
  const shelves = await fetchHomepageShelves(locale)
    .then(({ data }) => (data ?? []).slice(0, shelfLimit))
    .catch(failed([] as HomepageCategoryShelf[]));

  return Promise.all(
    shelves.map(async (shelf) => {
      if (!shelf.slug) {
        return { shelf, products: [] as ProductSummary[] };
      }
      const products = await fetchProductsPage(
        { category_slug: shelf.slug, per_page: productsPerShelf },
        locale,
      )
        .then((page) => page.data)
        .catch(failed([] as ProductSummary[]));
      return { shelf, products };
    }),
  );
}

/** Products for each standalone `category_shelf` section, keyed by section id. */
async function loadCategoryShelfProducts(
  categoryShelfSecs: HomepageSection[],
  locale: string,
  failed: MarkFailed,
): Promise<Record<string, ProductSummary[]>> {
  const entries = await Promise.all(
    categoryShelfSecs.map(async (sec) => {
      const shelf = sec.settings.shelf as HomepageCategoryShelf | undefined;
      const perPage = sectionSettingNumber(sec.settings, "products_per_shelf", 6);
      const pickedIds = categoryShelfPickedIds(sec.settings);
      if (!shelf?.slug) {
        return [sec.id, [] as ProductSummary[]] as const;
      }
      const products = await fetchProductsPage(
        pickedIds.length > 0
          ? { category_slug: shelf.slug, ids: pickedIds.join(","), per_page: pickedIds.length }
          : { category_slug: shelf.slug, per_page: perPage },
        locale,
      )
        .then((page) => page.data)
        .catch(failed([] as ProductSummary[]));
      return [sec.id, products] as const;
    }),
  );
  return Object.fromEntries(entries);
}

/** Fetch every catalog block the homepage sections need; `degraded` is true when any request failed. */
async function loadHomepageCatalog(
  sections: HomepageSection[],
  locale: string,
): Promise<{ catalog: HomepageCatalog; degraded: boolean }> {
  let degraded = false;
  const failed: MarkFailed = (fallback) => () => {
    degraded = true;
    return fallback;
  };

  const featuredSec = sections.find((s) => s.type === "featured_products");
  const bestSec = sections.find((s) => s.type === "bestsellers");
  const brandSec = sections.find((s) => s.type === "brand_slider");
  const shelfSec = sections.find((s) => s.type === "category_shelves");
  const categoryShelfSecs = sections.filter((s) => s.type === "category_shelf");

  const featuredPerPage = featuredSec ? sectionSettingNumber(featuredSec.settings, "per_page", 8) : 8;
  const bestPerPage = bestSec ? sectionSettingNumber(bestSec.settings, "per_page", 6) : 6;
  const bestInStock = bestSec ? sectionSettingBool(bestSec.settings, "in_stock_only", true) : true;
  const brandLimit = brandSec ? sectionSettingNumber(brandSec.settings, "limit", 24) : 24;

  const [featuredPage, bestPage, brands, shelves, categoryShelfProducts] = await Promise.all([
    featuredSec
      ? fetchProductsPage({ featured: 1, per_page: featuredPerPage }, locale).catch(
          failed(emptyPage(featuredPerPage)),
        )
      : Promise.resolve(emptyPage(0)),
    bestSec
      ? fetchProductsPage(
          { sort: "bestsellers", per_page: bestPerPage, ...(bestInStock ? { in_stock_only: true } : {}) },
          locale,
        ).catch(failed(emptyPage(bestPerPage)))
      : Promise.resolve(emptyPage(0)),
    brandSec
      ? fetchBrands(locale)
          .then((r) => (r.data ?? []).slice(0, brandLimit))
          .catch(failed([] as Brand[]))
      : Promise.resolve([] as Brand[]),
    shelfSec ? loadShelfRows(shelfSec, locale, failed) : Promise.resolve([]),
    loadCategoryShelfProducts(categoryShelfSecs, locale, failed),
  ]);

  return {
    catalog: {
      featured: featuredPage.data,
      bestsellers: bestPage.data,
      brands,
      shelves,
      categoryShelfProducts,
    },
    degraded,
  };
}

export default component$(() => {
  const settings = useSiteSettings();
  const sections = useHomepageSections();
  const catalog = useHomepageCatalog();
  const categoriesLoad = useNavCategories();
  const loc = useLocation();
  const { locale } = useI18n();
  const origin = loc.url.origin;

  const orderedSections = [...sections.value];
  const brandIdx = orderedSections.findIndex((s) => s.type === "brand_slider");
  const trustIdx = orderedSections.findIndex((s) => s.type === "trust_badges");
  if (brandIdx >= 0 && trustIdx >= 0 && brandIdx > trustIdx) {
    const [brandSection] = orderedSections.splice(brandIdx, 1);
    const insertAt = orderedSections.findIndex((s) => s.type === "trust_badges");
    orderedSections.splice(insertAt, 0, brandSection);
  }

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

      <HomeSections>
        {orderedSections.map((section) => {
          const width =
            section.type === "hero_slider" || section.layout_width === "full"
              ? "full"
              : "boxed";
          const shellClass = `home-section-shell home-section-shell--${width}`;
          const isHero = section.type === "hero_slider";
          const isPromoTiles = section.type === "promo_tiles";
          // Hero stays static; promo tiles use their own directional reveals.
          const skipReveal = isHero || isPromoTiles;

          let content: JSXOutput | null = null;
          switch (section.type) {
            case "hero_slider": {
              const slides = (section.settings.slides as HomepageHeroSlide[] | undefined) ?? [];
              content = <HeroSlider slides={slides} />;
              break;
            }
            case "promo_tiles": {
              const tiles = (section.settings.tiles as HomepagePromoTile[] | undefined) ?? [];
              content = <PromoTiles tiles={tiles} />;
              break;
            }
            case "video":
              content = (
                <HomeVideo
                  source={String(section.settings.source ?? "self")}
                  src={String(section.settings.url ?? "")}
                  embedUrl={
                    section.settings.embed_url == null ? null : String(section.settings.embed_url)
                  }
                  poster={String(section.settings.poster ?? "")}
                  title={String(section.settings.title ?? "")}
                />
              );
              break;
            case "trust_badges": {
              const items = (section.settings.items as HomepageTrustBadge[] | undefined) ?? [];
              content = <TrustBadges items={items} />;
              break;
            }
            case "promo_banners":
              content = (
                <PromoBanners banners={settings.value.banners ?? []} placement="home" />
              );
              break;
            case "promo_banner": {
              const banner = section.settings as unknown as HomepagePromoBanner;
              if (!banner.logo_url && !banner.image_url && !banner.top_title && !banner.main_title) {
                content = null;
              } else {
                content = <PromoBannerSection banner={banner} />;
              }
              break;
            }
            case "featured_products":
              content = (
                <FeaturedSlider products={catalog.value.featured} settings={settings.value} />
              );
              break;
            case "top_categories":
              content = (
                <TopCategories
                  categories={categoriesLoad.value.items}
                  limit={sectionSettingNumber(section.settings, "limit", 8)}
                />
              );
              break;
            case "category_shelves":
              content = (
                <div class="home-category-shelves">
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
              break;
            case "category_shelf": {
              const shelf = section.settings.shelf as HomepageCategoryShelf | undefined;
              content = shelf ? (
                <CategoryShelf
                  shelf={shelf}
                  products={catalog.value.categoryShelfProducts[section.id] ?? []}
                  settings={settings.value}
                />
              ) : null;
              break;
            }
            case "brand_slider":
              content = (
                <BrandSlider
                  brands={catalog.value.brands}
                  limit={sectionSettingNumber(section.settings, "limit", 24)}
                />
              );
              break;
            case "bestsellers":
              content = (
                <BestSelling
                  products={catalog.value.bestsellers}
                  settings={settings.value}
                  style={String(section.settings.style ?? "grid")}
                />
              );
              break;
            case "recently_viewed":
              content = (
                <RecentlyViewed
                  settings={settings.value}
                  headingId="home-recently-viewed-heading"
                  limit={sectionSettingNumber(section.settings, "limit", 8)}
                />
              );
              break;
            default:
              content = null;
          }

          if (!content) {
            return null;
          }

          return (
            <div
              key={section.id}
              class={shellClass}
              {...(skipReveal ? {} : { "data-home-reveal": "" })}
            >
              {content}
            </div>
          );
        })}
      </HomeSections>
    </>
  );
});

export const head: DocumentHead = ({ resolveValue, url, params }) => {
  const settings = resolveValue(useSiteSettings);
  const sections = resolveValue(useHomepageSections);
  const lang = isSupportedLocale(params.lang) ? params.lang : "en";
  const title = tStatic(lang, "seo.homeTitle", { businessName: settings.business_name });
  const description = tStatic(lang, "seo.homeDescription", {
    businessName: settings.business_name,
  });
  const canonical = publicSeoLinks(url.origin, "/", lang)[0]?.href;

  const heroSection = sections.find((s) => s.type === "hero_slider");
  const heroSlides =
    (heroSection?.settings?.slides as HomepageHeroSlide[] | undefined) ?? [];
  const lcpDesktop = heroSlides[0]?.image_url?.trim() || "";
  const lcpMobile =
    heroSlides[0]?.image_mobile_url?.trim() || lcpDesktop;

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
      links: [
        ...publicSeoLinks(url.origin, "/", lang),
        ...(lcpDesktop
          ? [
              {
                key: "preload-hero-lcp-desktop",
                rel: "preload",
                as: "image",
                href: lcpDesktop,
                media: "(min-width: 1024px)",
                fetchpriority: "high",
              } as {
                key: string;
                rel: string;
                as: string;
                href: string;
                media: string;
                fetchpriority: string;
              },
              {
                key: "preload-hero-lcp-mobile",
                rel: "preload",
                as: "image",
                href: lcpMobile,
                media: "(max-width: 1023px)",
                fetchpriority: "high",
              } as {
                key: string;
                rel: string;
                as: string;
                href: string;
                media: string;
                fetchpriority: string;
              },
            ]
          : []),
      ],
    },
    settings,
  );
};
