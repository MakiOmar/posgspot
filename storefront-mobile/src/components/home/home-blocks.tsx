import { useEffect, useRef, useState } from "react";
import {
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useRouter } from "expo-router";
import { absoluteMediaUrl, hrefToAppPath } from "../../lib/storefront-href";
import type {
  HomepageCategoryShelf,
  HomepageHeroSlide,
  HomepagePromoBanner,
  HomepagePromoTile,
  HomepageTrustBadge,
  ProductSummary,
} from "../../lib/types";
import { useApp } from "../../contexts/AppContext";
import { RemoteImage } from "../RemoteImage";
import { ProductCard } from "../ui";
import { AppLink } from "./AppLink";
import { homeRailStyles } from "./home-rails";
import { useHomeScreenWidth } from "./home-utils";

export function HeroSlider({ slides }: { slides: HomepageHeroSlide[] }) {
  const { t, accent } = useApp();
  const screenW = useHomeScreenWidth();
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
    <View
      style={[
        styles.hero,
        { width: screenW, height: Math.round(screenW * 0.72) },
      ]}
    >
      <RemoteImage uri={image} style={styles.heroImage} placeholderColor="#222" />
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

export function PromoTiles({ tiles }: { tiles: HomepagePromoTile[] }) {
  if (!tiles.length) return null;
  return (
    <View style={styles.tileGrid}>
      {tiles.map((tile) => {
        const image = absoluteMediaUrl(tile.image_url);
        return (
          <AppLink key={tile.id} href={tile.href} style={styles.tile}>
            <RemoteImage
              uri={image}
              style={styles.tileImage}
              placeholderColor="#ddd"
            />
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

export function VideoBlock({ settings }: { settings: Record<string, unknown> }) {
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
    <View style={homeRailStyles.sectionBlock}>
      <Text style={homeRailStyles.sectionTitle}>{title}</Text>
      <Pressable
        style={styles.video}
        onPress={() => {
          if (openUrl) void Linking.openURL(openUrl);
        }}
      >
        {poster ? (
          <RemoteImage uri={poster} style={styles.videoPoster} />
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

export function TrustBadgesBlock({ items }: { items: HomepageTrustBadge[] }) {
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
              <RemoteImage
                uri={icon}
                style={styles.trustIcon}
                contentFit="contain"
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

export function PromoBannerBlock({ banner }: { banner: HomepagePromoBanner }) {
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
    <View style={homeRailStyles.sectionBlock}>
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
            <RemoteImage uri={logo} style={styles.promoLogo} />
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
                <Text
                  style={{ color: btn.text_color || "#111", fontWeight: "700" }}
                >
                  {btn.label}
                  {btn.show_arrow ? " →" : ""}
                </Text>
              </View>
            </AppLink>
          ) : null}
        </View>
        {image ? (
          <RemoteImage
            uri={image}
            style={styles.promoImage}
            contentFit="contain"
          />
        ) : null}
      </View>
    </View>
  );
}

export function SiteBannersBlock({
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
    <View style={homeRailStyles.sectionBlock}>
      {home.map((b, i) => {
        const image = absoluteMediaUrl(b.image_url);
        return (
          <AppLink
            key={b.id || String(i)}
            href={b.link}
            style={styles.siteBanner}
          >
            {image ? (
              <RemoteImage uri={image} style={styles.siteBannerImage} />
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
export function CategoryShelfBlock({
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
    <View style={homeRailStyles.sectionBlock}>
      <View style={styles.shelfHeader}>
        <Text style={homeRailStyles.sectionTitle}>{heading}</Text>
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
              <RemoteImage uri={bg} style={styles.shelfBannerBg} />
            ) : (
              <View
                style={[styles.shelfBannerBg, { backgroundColor: "#0a0a0c" }]}
              />
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
                <RemoteImage
                  uri={fg}
                  style={styles.shelfFg}
                  contentFit="contain"
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
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={homeRailStyles.rail}
        >
          {products.map((item) => (
            <View key={String(item.id)} style={homeRailStyles.railCard}>
              <ProductCard product={item} wide />
            </View>
          ))}
        </ScrollView>
      ) : (
        <Text style={styles.emptyShelf}>{t("common.noProducts")}</Text>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  hero: {
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
  heroKicker: {
    color: "#fff",
    opacity: 0.9,
    fontSize: 13,
    fontWeight: "600",
  },
  heroTitle: {
    color: "#fff",
    fontSize: 26,
    fontWeight: "800",
    maxWidth: "90%",
  },
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
  tileGrid: { flexDirection: "row", flexWrap: "wrap", gap: 10 },
  tile: {
    width: "48%",
    borderRadius: 12,
    overflow: "hidden",
    backgroundColor: "#fff",
  },
  tileImage: { width: "100%", height: 110, backgroundColor: "#eee" },
  tileLabel: { padding: 10, fontWeight: "600", fontSize: 13 },
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
