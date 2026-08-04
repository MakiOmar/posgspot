import { useEffect, useMemo, useState } from "react";
import { StyleSheet, View } from "react-native";
import {
  fetchBrands,
  fetchCategories,
  fetchHomepageShelves,
  fetchProducts,
} from "../../lib/api";
import type {
  Brand,
  Category,
  ContentLocale,
  HomepageCategoryShelf,
  HomepagePromoBanner,
  HomepageSection,
  ProductSummary,
} from "../../lib/types";
import { useApp } from "../../contexts/AppContext";
import {
  CategoryShelfBlock,
  HeroSlider,
  PromoBannerBlock,
  PromoTiles,
  SiteBannersBlock,
  TrustBadgesBlock,
  VideoBlock,
} from "./home-blocks";
import { BrandRail, CategoryRail, ProductRail } from "./home-rails";
import {
  asSlides,
  asTiles,
  asTrustBadges,
  mapPool,
  settingNumber,
} from "./home-utils";

export function HomepageSections({
  sections,
  locale,
}: {
  sections: HomepageSection[];
  locale: ContentLocale;
}) {
  const { t, settings } = useApp();
  const [featured, setFeatured] = useState<ProductSummary[]>([]);
  const [bestsellers, setBestsellers] = useState<ProductSummary[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [brands, setBrands] = useState<Brand[]>([]);
  const [legacyShelves, setLegacyShelves] = useState<
    Array<{ shelf: HomepageCategoryShelf; products: ProductSummary[] }>
  >([]);
  const [shelfProducts, setShelfProducts] = useState<
    Record<string, ProductSummary[]>
  >({});

  const meta = useMemo(() => {
    const featuredSec = sections.find((s) => s.type === "featured_products");
    const bestSec = sections.find((s) => s.type === "bestsellers");
    const topSec = sections.find((s) => s.type === "top_categories");
    const brandSec = sections.find((s) => s.type === "brand_slider");
    const shelvesSec = sections.find((s) => s.type === "category_shelves");
    const shelfSecs = sections.filter((s) => s.type === "category_shelf");
    return {
      featuredPerPage: featuredSec
        ? settingNumber(featuredSec.settings, "per_page", 8)
        : 0,
      bestPerPage: bestSec ? settingNumber(bestSec.settings, "per_page", 6) : 0,
      categoryLimit: topSec ? settingNumber(topSec.settings, "limit", 12) : 0,
      brandLimit: brandSec ? settingNumber(brandSec.settings, "limit", 24) : 0,
      shelvesLimit: shelvesSec
        ? settingNumber(shelvesSec.settings, "limit", 6)
        : 0,
      shelvesPer: shelvesSec
        ? settingNumber(shelvesSec.settings, "products_per_shelf", 6)
        : 6,
      shelfSecs,
    };
  }, [sections]);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      const tasks: Array<Promise<void>> = [];

      if (meta.featuredPerPage > 0) {
        tasks.push(
          fetchProducts({ featured: 1, per_page: meta.featuredPerPage }, locale)
            .then((r) => {
              if (!cancelled) setFeatured(r.data || []);
            })
            .catch(() => {
              if (!cancelled) setFeatured([]);
            }),
        );
      }

      if (meta.bestPerPage > 0) {
        tasks.push(
          fetchProducts(
            {
              sort: "bestsellers",
              per_page: meta.bestPerPage,
              in_stock_only: true,
            },
            locale,
          )
            .then((r) => {
              if (!cancelled) setBestsellers(r.data || []);
            })
            .catch(() => {
              if (!cancelled) setBestsellers([]);
            }),
        );
      }

      if (meta.categoryLimit > 0) {
        tasks.push(
          fetchCategories(locale)
            .then((r) => {
              if (!cancelled) {
                setCategories((r.data || []).slice(0, meta.categoryLimit));
              }
            })
            .catch(() => {
              if (!cancelled) setCategories([]);
            }),
        );
      }

      if (meta.brandLimit > 0) {
        tasks.push(
          fetchBrands(locale)
            .then((r) => {
              if (!cancelled) {
                setBrands((r.data || []).slice(0, meta.brandLimit));
              }
            })
            .catch(() => {
              if (!cancelled) setBrands([]);
            }),
        );
      }

      if (meta.shelvesLimit > 0) {
        tasks.push(
          (async () => {
            try {
              const { data } = await fetchHomepageShelves(locale);
              const shelves = (data || []).slice(0, meta.shelvesLimit);
              const rows = await mapPool(shelves, 3, async (shelf) => {
                if (!shelf.slug) {
                  return { shelf, products: [] as ProductSummary[] };
                }
                try {
                  const page = await fetchProducts(
                    {
                      category_slug: shelf.slug,
                      per_page: meta.shelvesPer,
                    },
                    locale,
                  );
                  return { shelf, products: page.data || [] };
                } catch {
                  return { shelf, products: [] as ProductSummary[] };
                }
              });
              if (!cancelled) setLegacyShelves(rows);
            } catch {
              if (!cancelled) setLegacyShelves([]);
            }
          })(),
        );
      }

      const shelfJobs = meta.shelfSecs
        .map((sec) => {
          const shelf = (sec.settings.shelf || {}) as HomepageCategoryShelf;
          const slug = shelf.slug;
          const per = settingNumber(sec.settings, "products_per_shelf", 6);
          const key = String(sec.id);
          if (!slug) return null;
          return { key, slug, per };
        })
        .filter((j): j is { key: string; slug: string; per: number } => !!j);

      if (shelfJobs.length) {
        tasks.push(
          (async () => {
            const pairs = await mapPool(shelfJobs, 3, async (job) => {
              try {
                const page = await fetchProducts(
                  { category_slug: job.slug, per_page: job.per },
                  locale,
                );
                return [job.key, page.data || []] as const;
              } catch {
                return [job.key, [] as ProductSummary[]] as const;
              }
            });
            if (!cancelled) {
              setShelfProducts((prev) => {
                const next = { ...prev };
                for (const [key, products] of pairs) {
                  next[key] = products;
                }
                return next;
              });
            }
          })(),
        );
      }

      await Promise.all(tasks);
    })();
    return () => {
      cancelled = true;
    };
  }, [locale, meta]);

  return (
    <View>
      {sections.map((section) => {
        const key = String(section.id);
        switch (section.type) {
          case "hero_slider":
            return <HeroSlider key={key} slides={asSlides(section.settings)} />;
          case "promo_tiles":
            return (
              <View key={key} style={styles.sectionBlock}>
                <PromoTiles tiles={asTiles(section.settings)} />
              </View>
            );
          case "video":
            return <VideoBlock key={key} settings={section.settings} />;
          case "trust_badges":
            return (
              <View key={key} style={styles.sectionBlock}>
                <TrustBadgesBlock items={asTrustBadges(section.settings)} />
              </View>
            );
          case "promo_banners":
            return (
              <SiteBannersBlock
                key={key}
                banners={settings?.banners || []}
              />
            );
          case "promo_banner":
            return (
              <PromoBannerBlock
                key={key}
                banner={section.settings as unknown as HomepagePromoBanner}
              />
            );
          case "featured_products":
            return (
              <ProductRail
                key={key}
                title={t("home.featured")}
                products={featured}
              />
            );
          case "bestsellers":
            return (
              <ProductRail
                key={key}
                title={t("home.bestsellers")}
                products={bestsellers}
              />
            );
          case "top_categories":
            return <CategoryRail key={key} categories={categories} />;
          case "brand_slider":
            return <BrandRail key={key} brands={brands} />;
          case "category_shelves":
            return (
              <View key={key}>
                {legacyShelves.map(({ shelf, products }) => (
                  <CategoryShelfBlock
                    key={String(shelf.id || shelf.slug)}
                    shelf={shelf}
                    products={products}
                  />
                ))}
              </View>
            );
          case "category_shelf": {
            const shelf = (section.settings.shelf ||
              {}) as HomepageCategoryShelf;
            return (
              <CategoryShelfBlock
                key={key}
                shelf={shelf}
                products={shelfProducts[key] || []}
              />
            );
          }
          case "recently_viewed":
            return null;
          default:
            return null;
        }
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  sectionBlock: { marginBottom: 20, paddingHorizontal: 16 },
});
