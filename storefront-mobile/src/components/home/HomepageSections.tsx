import { useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import {
  Dimensions,
  Image,
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useRouter } from "expo-router";
import {
  fetchBrands,
  fetchCategories,
  fetchHomepageShelves,
  fetchProducts,
} from "../../lib/api";
import { absoluteMediaUrl, hrefToAppPath } from "../../lib/storefront-href";
import type {
  Brand,
  Category,
  ContentLocale,
  HomepageCategoryShelf,
  HomepageHeroSlide,
  HomepagePromoBanner,
  HomepagePromoTile,
  HomepageSection,
  HomepageTrustBadge,
  ProductSummary,
} from "../../lib/types";
import { useApp } from "../../contexts/AppContext";
import { ProductCard } from "../ui";

const SCREEN_W = Dimensions.get("window").width;

function asSlides(settings: Record<string, unknown>): HomepageHeroSlide[] {
  const raw = settings.slides;
  if (!Array.isArray(raw)) return [];
  return raw.filter(
    (s): s is HomepageHeroSlide =>
      !!s &&
      typeof s === "object" &&
      typeof (s as HomepageHeroSlide).image_url === "string",
  );
}

function asTiles(settings: Record<string, unknown>): HomepagePromoTile[] {
  const raw = settings.tiles;
  if (!Array.isArray(raw)) return [];
  return raw.filter(
    (t): t is HomepagePromoTile =>
      !!t &&
      typeof t === "object" &&
      typeof (t as HomepagePromoTile).image_url === "string",
  );
}

function asTrustBadges(settings: Record<string, unknown>): HomepageTrustBadge[] {
  const raw = settings.items;
  if (!Array.isArray(raw)) return [];
  return raw as HomepageTrustBadge[];
}

function settingNumber(
  settings: Record<string, unknown>,
  key: string,
  fallback: number,
): number {
  const raw = settings[key];
  const n = typeof raw === "number" ? raw : Number(raw);
  return Number.isFinite(n) && n > 0 ? n : fallback;
}

function AppLink({
  href,
  children,
  style,
}: {
  href: string | null | undefined;
  children: ReactNode;
  style?: object;
}) {
  const router = useRouter();
  const appPath = hrefToAppPath(href);
  if (!appPath) {
    return <View style={style}>{children}</View>;
  }
  if (appPath.startsWith("http")) {
    return (
      <Pressable style={style} onPress={() => void Linking.openURL(appPath)}>
        {children}
      </Pressable>
    );
  }
  return (
    <Pressable style={style} onPress={() => router.push(appPath as never)}>
      {children}
    </Pressable>
  );
}

function HeroSlider({ slides }: { slides: HomepageHeroSlide[] }) {
  const { t, accent } = useApp();
  const [index, setIndex] = useState(0);
  const timer = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    if (slides.length < 2) return;
    timer.current = setInterval(() => {
      setIndex((i) => (i + 1) % slides.length);
    }, 6000);
    return () => {
      if (timer.current) clearInterval(timer.current);
    };
  }, [slides.length]);

  if (!slides.length) return null;
  const slide = slides[index] ?? slides[0];
  const image = absoluteMediaUrl(slide.image_url);

  return (
    <View style={styles.hero}>
      {image ? (
        <Image source={{ uri: image }} style={styles.heroImage} resizeMode="cover" />
      ) : (
        <View style={[styles.heroImage, { backgroundColor: "#222" }]} />
      )}
      <View style={styles.heroScrim} />
      <View style={styles.heroContent}>
        {slide.kicker ? (
          <Text style={styles.heroKicker}>{slide.kicker}</Text>
        ) : null}
        {slide.title ? (
          <Text style={styles.heroTitle}>{slide.title}</Text>
        ) : null}
        <AppLink href={slide.href}>
          <View style={[styles.heroCta, { backgroundColor: accent }]}>
            <Text style={styles.heroCtaText}>{t("home.shopNow")}</Text>
          </View>
        </AppLink>
      </View>
      {slides.length > 1 ? (
        <View style={styles.dots}>
          {slides.map((s, i) => (
            <Pressable
              key={s.id || String(i)}
              onPress={() => setIndex(i)}
              style={[styles.dot, i === index && styles.dotActive]}
            />
          ))}
        </View>
      ) : null}
    </View>
  );
}

function PromoTiles({ tiles }: { tiles: HomepagePromoTile[] }) {
  if (!tiles.length) return null;
  return (
    <View style={styles.tileGrid}>
      {tiles.map((tile) => {
        const image = absoluteMediaUrl(tile.image_url);
        return (
          <AppLink key={tile.id} href={tile.href} style={styles.tile}>
            {image ? (
              <Image source={{ uri: image }} style={styles.tileImage} />
            ) : (
              <View style={[styles.tileImage, { backgroundColor: "#ddd" }]} />
            )}
            {tile.label ? (
              <Text style={styles.tileLabel} numberOfLines={2}>
                {tile.label}
              </Text>
            ) : null}
          </AppLink>
        );
      })}
    </View>
  );
}

function ProductRail({
  title,
  products,
}: {
  title: string;
  products: ProductSummary[];
}) {
  if (!products.length) return null;
  return (
    <View style={styles.sectionBlock}>
      <Text style={styles.sectionTitle}>{title}</Text>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.rail}
      >
        {products.map((p) => (
          <View key={p.id} style={styles.railCard}>
            <ProductCard product={p} wide />
          </View>
        ))}
      </ScrollView>
    </View>
  );
}

function CategoryRail({ categories }: { categories: Category[] }) {
  const { t } = useApp();
  const router = useRouter();
  if (!categories.length) return null;
  return (
    <View style={styles.sectionBlock}>
      <Text style={styles.sectionTitle}>{t("home.topCategories")}</Text>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.rail}
      >
        {categories.map((cat) => {
          const image = absoluteMediaUrl(cat.image_url);
          return (
            <Pressable
              key={cat.id}
              style={styles.catCard}
              onPress={() => router.push(`/category/${cat.slug}` as never)}
            >
              {image ? (
                <Image source={{ uri: image }} style={styles.catImage} />
              ) : (
                <View style={styles.catImagePlaceholder} />
              )}
              <Text numberOfLines={2} style={styles.catName}>
                {cat.name}
              </Text>
            </Pressable>
          );
        })}
      </ScrollView>
    </View>
  );
}

function BrandRail({ brands }: { brands: Brand[] }) {
  const { t } = useApp();
  const router = useRouter();
  if (!brands.length) return null;
  return (
    <View style={styles.sectionBlock}>
      <Text style={styles.sectionTitle}>{t("common.brands")}</Text>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.rail}
      >
        {brands.map((brand) => {
          const image = absoluteMediaUrl(brand.image_url);
          return (
            <Pressable
              key={brand.id}
              style={styles.brandCard}
              onPress={() => router.push(`/brands/${brand.slug}` as never)}
            >
              {image ? (
                <Image
                  source={{ uri: image }}
                  style={styles.brandImage}
                  resizeMode="contain"
                />
              ) : (
                <Text style={styles.brandFallback} numberOfLines={2}>
                  {brand.name}
                </Text>
              )}
            </Pressable>
          );
        })}
      </ScrollView>
    </View>
  );
}

function VideoBlock({ settings }: { settings: Record<string, unknown> }) {
  const { t, accent } = useApp();
  const title =
    typeof settings.title === "string" ? settings.title : t("home.video");
  const poster = absoluteMediaUrl(
    typeof settings.poster === "string" ? settings.poster : null,
  );
  const openUrl =
    (typeof settings.embed_url === "string" && settings.embed_url) ||
    (typeof settings.url === "string" && settings.url) ||
    null;

  if (!openUrl && !poster) return null;

  return (
    <View style={styles.sectionBlock}>
      <Text style={styles.sectionTitle}>{title}</Text>
      <Pressable
        style={styles.video}
        onPress={() => {
          if (openUrl) void Linking.openURL(openUrl);
        }}
      >
        {poster ? (
          <Image source={{ uri: poster }} style={styles.videoPoster} />
        ) : (
          <View style={[styles.videoPoster, { backgroundColor: "#111" }]} />
        )}
        <View style={[styles.playBtn, { backgroundColor: accent }]}>
          <Text style={styles.playText}>▶</Text>
        </View>
      </Pressable>
    </View>
  );
}

function TrustBadgesBlock({ items }: { items: HomepageTrustBadge[] }) {
  const { accent } = useApp();
  if (!items.length) return null;
  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={styles.trustRail}
    >
      {items.map((item, i) => {
        const icon = absoluteMediaUrl(item.icon_url);
        return (
          <View key={item.id || String(i)} style={styles.trustCard}>
            {icon ? (
              <Image
                source={{ uri: icon }}
                style={[
                  styles.trustIcon,
                  item.icon_color ? { tintColor: item.icon_color } : null,
                ]}
                resizeMode="contain"
              />
            ) : (
              <View
                style={[
                  styles.trustIcon,
                  { backgroundColor: item.icon_color || accent },
                ]}
              />
            )}
            {item.title ? (
              <Text style={styles.trustTitle}>{item.title}</Text>
            ) : null}
            {item.description ? (
              <Text style={styles.trustDesc}>{item.description}</Text>
            ) : null}
          </View>
        );
      })}
    </ScrollView>
  );
}

function PromoBannerBlock({ banner }: { banner: HomepagePromoBanner }) {
  if (
    !banner.logo_url &&
    !banner.image_url &&
    !banner.top_title &&
    !banner.main_title
  ) {
    return null;
  }
  const logo = absoluteMediaUrl(banner.logo_url);
  const image = absoluteMediaUrl(banner.image_url);
  const btn = banner.button;

  return (
    <View style={styles.sectionBlock}>
      <View
        style={[
          styles.promoShell,
          {
            backgroundColor: banner.background_color || "#111",
            borderRadius: banner.border_radius ?? 16,
            borderWidth: banner.border_thickness ?? 0,
            borderColor: banner.border_color || "transparent",
            minHeight: banner.min_height ?? 160,
          },
        ]}
      >
        <View style={styles.promoCopy}>
          {logo ? (
            <Image source={{ uri: logo }} style={styles.promoLogo} />
          ) : null}
          {banner.top_title ? (
            <Text
              style={[
                styles.promoTop,
                { color: banner.top_title_color || "#fff" },
              ]}
            >
              {banner.top_title}
            </Text>
          ) : null}
          {banner.main_title ? (
            <Text
              style={[
                styles.promoMain,
                { color: banner.main_title_color || "#fff" },
              ]}
            >
              {banner.main_title}
            </Text>
          ) : null}
          {btn?.label ? (
            <AppLink href={btn.link}>
              <View
                style={[
                  styles.promoCta,
                  {
                    backgroundColor: btn.background_color || "#fff",
                    borderRadius: btn.border_radius ?? 10,
                  },
                ]}
              >
                <Text style={{ color: btn.text_color || "#111", fontWeight: "700" }}>
                  {btn.label}
                  {btn.show_arrow ? " →" : ""}
                </Text>
              </View>
            </AppLink>
          ) : null}
        </View>
        {image ? (
          <Image source={{ uri: image }} style={styles.promoImage} resizeMode="contain" />
        ) : null}
      </View>
    </View>
  );
}

function SiteBannersBlock({
  banners,
}: {
  banners: Array<{
    id?: string | number;
    placement?: string;
    title?: string;
    link?: string;
    image_url?: string;
  }>;
}) {
  const home = banners.filter(
    (b) => !b.placement || b.placement === "home",
  );
  if (!home.length) return null;
  return (
    <View style={styles.sectionBlock}>
      {home.map((b, i) => {
        const image = absoluteMediaUrl(b.image_url);
        return (
          <AppLink key={b.id || String(i)} href={b.link} style={styles.siteBanner}>
            {image ? (
              <Image source={{ uri: image }} style={styles.siteBannerImage} />
            ) : null}
            {b.title ? (
              <Text style={styles.siteBannerTitle}>{b.title}</Text>
            ) : null}
          </AppLink>
        );
      })}
    </View>
  );
}

/** Qwik-parity category shelf: stacked banner (bg + kicker + title + FG + CTA) + products. */
function CategoryShelfBlock({
  shelf,
  products,
}: {
  shelf: HomepageCategoryShelf;
  products: ProductSummary[];
}) {
  const { t, accent } = useApp();
  const router = useRouter();
  const bg = absoluteMediaUrl(shelf.banner_image_url);
  const fg = absoluteMediaUrl(shelf.banner_fg_image_url);
  const heading = shelf.heading || shelf.name || t("home.categoryShelf");
  const moreHref = hrefToAppPath(
    shelf.view_more_path ||
      shelf.banner_link ||
      (shelf.slug ? `/category/${shelf.slug}` : null),
  );
  const buttonText = shelf.button_text || t("home.shopNow");
  const hasBanner = !!(bg || fg || shelf.banner_kicker || shelf.banner_text);

  if (!hasBanner && !products.length) {
    return null;
  }

  return (
    <View style={styles.sectionBlock}>
      <View style={styles.shelfHeader}>
        <Text style={styles.sectionTitle}>{heading}</Text>
        {moreHref ? (
          <Pressable onPress={() => router.push(moreHref as never)}>
            <Text style={{ color: accent, fontWeight: "600" }}>
              {shelf.view_more_label || t("home.viewMore")}
            </Text>
          </Pressable>
        ) : null}
      </View>

      {hasBanner ? (
        <AppLink href={shelf.banner_link || shelf.view_more_path}>
          <View style={styles.shelfBanner}>
            {bg ? (
              <Image
                source={{ uri: bg }}
                style={styles.shelfBannerBg}
                resizeMode="cover"
              />
            ) : (
              <View style={[styles.shelfBannerBg, { backgroundColor: "#0a0a0c" }]} />
            )}
            <View style={styles.shelfBannerScrim} />
            <View style={styles.shelfBannerContent}>
              {shelf.banner_kicker ? (
                <Text style={styles.shelfKicker}>{shelf.banner_kicker}</Text>
              ) : null}
              {shelf.banner_text ? (
                <Text style={styles.shelfBannerTitle}>{shelf.banner_text}</Text>
              ) : null}
              {fg ? (
                <Image
                  source={{ uri: fg }}
                  style={styles.shelfFg}
                  resizeMode="contain"
                />
              ) : null}
              <View style={styles.shelfBtn}>
                <Text style={styles.shelfBtnText}>
                  {buttonText}{" "}
                  <Text style={{ color: accent }}>→</Text>
                </Text>
              </View>
            </View>
          </View>
        </AppLink>
      ) : null}

      {products.length > 0 ? (
        <View style={styles.productGrid}>
          {products.map((p) => (
            <ProductCard key={p.id} product={p} />
          ))}
        </View>
      ) : (
        <Text style={styles.emptyShelf}>{t("common.noProducts")}</Text>
      )}
    </View>
  );
}

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
              const rows = await Promise.all(
                shelves.map(async (shelf) => {
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
                }),
              );
              if (!cancelled) setLegacyShelves(rows);
            } catch {
              if (!cancelled) setLegacyShelves([]);
            }
          })(),
        );
      }

      for (const sec of meta.shelfSecs) {
        const shelf = (sec.settings.shelf || {}) as HomepageCategoryShelf;
        const slug = shelf.slug;
        const per = settingNumber(sec.settings, "products_per_shelf", 6);
        const key = String(sec.id);
        if (!slug) continue;
        tasks.push(
          fetchProducts({ category_slug: slug, per_page: per }, locale)
            .then((r) => {
              if (!cancelled) {
                setShelfProducts((prev) => ({
                  ...prev,
                  [key]: r.data || [],
                }));
              }
            })
            .catch(() => {
              if (!cancelled) {
                setShelfProducts((prev) => ({ ...prev, [key]: [] }));
              }
            }),
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
  hero: {
    width: SCREEN_W,
    height: Math.round(SCREEN_W * 0.72),
    backgroundColor: "#111",
    marginBottom: 16,
    justifyContent: "flex-end",
  },
  heroImage: { ...StyleSheet.absoluteFill, width: "100%", height: "100%" },
  heroScrim: {
    ...StyleSheet.absoluteFill,
    backgroundColor: "rgba(0,0,0,0.35)",
  },
  heroContent: { padding: 20, gap: 8 },
  heroKicker: { color: "#fff", opacity: 0.9, fontSize: 13, fontWeight: "600" },
  heroTitle: { color: "#fff", fontSize: 26, fontWeight: "800", maxWidth: "90%" },
  heroCta: {
    alignSelf: "flex-start",
    marginTop: 6,
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 10,
  },
  heroCtaText: { color: "#fff", fontWeight: "700" },
  dots: {
    position: "absolute",
    bottom: 12,
    left: 0,
    right: 0,
    flexDirection: "row",
    justifyContent: "center",
    gap: 6,
  },
  dot: {
    width: 7,
    height: 7,
    borderRadius: 4,
    backgroundColor: "rgba(255,255,255,0.45)",
  },
  dotActive: { backgroundColor: "#fff", width: 16 },
  sectionBlock: { marginBottom: 20, paddingHorizontal: 16 },
  sectionTitle: {
    fontSize: 18,
    fontWeight: "800",
    marginBottom: 12,
    color: "#111",
    flexShrink: 1,
  },
  tileGrid: { flexDirection: "row", flexWrap: "wrap", gap: 10 },
  tile: {
    width: "48%",
    borderRadius: 12,
    overflow: "hidden",
    backgroundColor: "#fff",
  },
  tileImage: { width: "100%", height: 110, backgroundColor: "#eee" },
  tileLabel: { padding: 10, fontWeight: "600", fontSize: 13 },
  rail: { gap: 10, paddingRight: 8 },
  railCard: { width: 160 },
  catCard: { width: 110, marginRight: 4 },
  catImage: { width: 110, height: 110, borderRadius: 12, backgroundColor: "#eee" },
  catImagePlaceholder: {
    width: 110,
    height: 110,
    borderRadius: 12,
    backgroundColor: "#e8e8e8",
  },
  catName: { marginTop: 6, fontSize: 13, fontWeight: "600", textAlign: "center" },
  brandCard: {
    width: 100,
    height: 72,
    borderRadius: 12,
    backgroundColor: "#fff",
    alignItems: "center",
    justifyContent: "center",
    padding: 8,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "#e5e5e5",
  },
  brandImage: { width: 84, height: 48 },
  brandFallback: { fontSize: 12, fontWeight: "600", textAlign: "center" },
  video: { borderRadius: 14, overflow: "hidden", backgroundColor: "#111" },
  videoPoster: { width: "100%", height: 180 },
  playBtn: {
    position: "absolute",
    alignSelf: "center",
    top: "40%",
    width: 56,
    height: 56,
    borderRadius: 28,
    alignItems: "center",
    justifyContent: "center",
  },
  playText: { color: "#fff", fontSize: 18, marginLeft: 2 },
  trustRail: { gap: 10, paddingRight: 8 },
  trustCard: {
    width: 160,
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 14,
    gap: 6,
  },
  trustIcon: { width: 40, height: 40, borderRadius: 8 },
  trustTitle: { fontWeight: "800", fontSize: 14 },
  trustDesc: { color: "#666", fontSize: 12, lineHeight: 16 },
  promoShell: {
    overflow: "hidden",
    flexDirection: "row",
    alignItems: "center",
    padding: 16,
    gap: 12,
  },
  promoCopy: { flex: 1, gap: 6 },
  promoLogo: { width: 56, height: 56, marginBottom: 4 },
  promoTop: { fontSize: 13, fontWeight: "600" },
  promoMain: { fontSize: 20, fontWeight: "800" },
  promoCta: {
    alignSelf: "flex-start",
    marginTop: 8,
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  promoImage: { width: 120, height: 120 },
  siteBanner: {
    marginBottom: 12,
    borderRadius: 12,
    overflow: "hidden",
    backgroundColor: "#fff",
  },
  siteBannerImage: { width: "100%", height: 140 },
  siteBannerTitle: { padding: 12, fontWeight: "700" },
  shelfHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 8,
    gap: 8,
  },
  shelfBanner: {
    width: "100%",
    minHeight: 280,
    borderRadius: 14,
    overflow: "hidden",
    marginBottom: 12,
    backgroundColor: "#0a0a0c",
  },
  shelfBannerBg: {
    ...StyleSheet.absoluteFill,
    width: "100%",
    height: "100%",
  },
  shelfBannerScrim: {
    ...StyleSheet.absoluteFill,
    backgroundColor: "rgba(0,0,0,0.35)",
  },
  shelfBannerContent: {
    padding: 16,
    alignItems: "center",
    gap: 10,
  },
  shelfKicker: {
    color: "#fff",
    fontSize: 12,
    fontWeight: "700",
    letterSpacing: 0.5,
    textTransform: "uppercase",
    textAlign: "center",
    opacity: 0.95,
  },
  shelfBannerTitle: {
    color: "#fff",
    fontSize: 18,
    fontWeight: "800",
    textAlign: "center",
    lineHeight: 24,
  },
  shelfFg: {
    width: "78%",
    height: 140,
    marginVertical: 4,
  },
  shelfBtn: {
    backgroundColor: "#fff",
    borderRadius: 999,
    paddingHorizontal: 18,
    paddingVertical: 10,
    marginTop: 4,
  },
  shelfBtnText: { fontWeight: "800", color: "#111" },
  emptyShelf: { color: "#888", marginBottom: 8 },
  productGrid: {
    flexDirection: "row",
    flexWrap: "wrap",
    justifyContent: "space-between",
  },
});
