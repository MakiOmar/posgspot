import { useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import {
  checkDigitalGameStock,
  fetchDigitalGame,
  fetchDigitalGames,
} from "../../src/lib/api";
import { absoluteMediaUrl } from "../../src/lib/storefront-href";
import type { DigitalGameSummary, DigitalSkus } from "../../src/lib/types";
import { useApp } from "../../src/contexts/AppContext";
import { useCart } from "../../src/contexts/CartContext";
import { RemoteImage } from "../../src/components/RemoteImage";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../src/components/ui";
import {
  digitalOfferEnabled,
  digitalOfferInStock,
  digitalOfferPrice,
  digitalOfferStock,
  liveCheckStockIsOut,
} from "../../src/lib/digital-game";
import { toast } from "../../src/lib/toast";
import { useRtl } from "../../src/lib/rtl";
import { buildWhatsAppUrl, normalizeWhatsAppDigits } from "../../src/lib/whatsapp";

type Offer = "primary" | "secondary";
type Platform = "4" | "5";

function stripHtml(html: string): string {
  return html.replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();
}

export default function GameDetailScreen() {
  const { id, platform: platformParam } = useLocalSearchParams<{
    id: string;
    platform?: string;
  }>();
  const platform: Platform = platformParam === "5" ? "5" : "4";
  const { locale, t, accent, settings } = useApp();
  const { textAlign, writingDirection } = useRtl();
  const { addItem } = useCart();
  const router = useRouter();
  const [game, setGame] = useState<Record<string, unknown> | null>(null);
  const [skus, setSkus] = useState<DigitalSkus | null>(null);
  const [alsoBought, setAlsoBought] = useState<DigitalGameSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [pending, setPending] = useState(false);
  const [selected, setSelected] = useState<Offer>("primary");
  const [faqOpen, setFaqOpen] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const gameId = Number(id);
      const [detailRes, listRes] = await Promise.all([
        fetchDigitalGame(gameId, locale),
        fetchDigitalGames(platform, 1, locale).catch(() => null),
      ]);
      setGame(detailRes.data.game);
      setSkus(detailRes.data.skus);
      const games = listRes?.data.games ?? [];
      setAlsoBought(games.filter((g) => Number(g.id) !== gameId).slice(0, 4));
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setLoading(false);
    }
  }, [id, locale, platform, t]);

  useEffect(() => {
    void load();
  }, [load]);

  const faqs = settings?.digital?.pdp_faqs ?? [];
  const askWhatsApp =
    normalizeWhatsAppDigits(settings?.digital?.ask_whatsapp) ||
    normalizeWhatsAppDigits(settings?.contact?.whatsapp) ||
    null;

  const title = game ? String(game.title || game.name || `Game #${id}`) : "";
  const imageRaw = game
    ? platform === "5"
      ? String(game.ps5_image_url || game.image_url || "")
      : String(game.ps4_image_url || game.image_url || "")
    : "";
  const image = absoluteMediaUrl(imageRaw) || imageRaw;

  const primaryPrice = game ? digitalOfferPrice(game, platform, "primary") : 0;
  const secondaryPrice = game ? digitalOfferPrice(game, platform, "secondary") : 0;
  const primaryOk = game ? digitalOfferEnabled(game, platform, "primary") : false;
  const secondaryOk = game ? digitalOfferEnabled(game, platform, "secondary") : false;
  const primaryInStock = game ? digitalOfferInStock(game, platform, "primary") : false;
  const secondaryInStock = game
    ? digitalOfferInStock(game, platform, "secondary")
    : false;

  const activeOffer: Offer = useMemo(() => {
    if (selected === "primary" && (!primaryOk || primaryPrice <= 0) && secondaryOk) {
      return "secondary";
    }
    if (selected === "secondary" && (!secondaryOk || secondaryPrice <= 0) && primaryOk) {
      return "primary";
    }
    return selected;
  }, [selected, primaryOk, secondaryOk, primaryPrice, secondaryPrice]);

  const activeInStock = activeOffer === "primary" ? primaryInStock : secondaryInStock;
  const activePrice = activeOffer === "primary" ? primaryPrice : secondaryPrice;
  const activeOk = activeOffer === "primary" ? primaryOk : secondaryOk;

  const descriptionPlain = useMemo(() => {
    const raw = game?.description;
    if (typeof raw !== "string" || !raw.trim()) {
      return null;
    }
    const plain = stripHtml(raw);
    return plain || null;
  }, [game]);

  const waHref = useMemo(() => {
    if (!askWhatsApp || !game) {
      return null;
    }
    const offerLabel =
      activeOffer === "primary" ? t("digital.primary") : t("digital.secondary");
    const message = t("digital.askWhatsAppMessage", {
      title,
      platform,
      offer: offerLabel,
      url: `games/${id}?platform=${platform}`,
    });
    return buildWhatsAppUrl(askWhatsApp, message);
  }, [askWhatsApp, game, activeOffer, t, title, platform, id]);

  if (loading) {
    return (
      <Screen>
        <LoadingBlock />
      </Screen>
    );
  }
  if (error || !game) {
    return (
      <Screen>
        <ErrorBlock message={error || undefined} onRetry={() => void load()} />
      </Screen>
    );
  }

  const addSelected = async () => {
    const offer = activeOffer;
    const sku = offer === "primary" ? skus?.primary : skus?.secondary;
    if (!sku) {
      toast.error(t("digital.skuMissing"));
      return;
    }
    const price = offer === "primary" ? primaryPrice : secondaryPrice;
    const stock = digitalOfferStock(game, platform, offer);
    const offerEnabled = offer === "primary" ? primaryOk : secondaryOk;
    if (!offerEnabled || price <= 0) {
      toast.error(t("digital.unavailable"));
      return;
    }
    if (stock <= 0) {
      toast.error(t("digital.outOfStock"));
      return;
    }

    setPending(true);
    try {
      const stockCheck = await checkDigitalGameStock(
        {
          game_id: Number(game.id || id),
          type: offer,
          platform,
        },
        locale,
      );
      const stockData = stockCheck.data as {
        is_available?: boolean;
        stock?: number | string;
      };
      if (liveCheckStockIsOut(stockData)) {
        toast.error(t("digital.outOfStock"));
        return;
      }

      const label =
        offer === "primary" ? t("digital.primary") : t("digital.secondary");
      const lineTitle = `${title} (${label} · PS${platform})`;
      await addItem({
        variationId: sku.variation_id,
        productId: sku.product_id,
        name: lineTitle,
        imageUrl: image || sku.image_url,
        unitPrice: price,
        quantity: 1,
        digital: {
          kind: "game",
          game_id: Number(game.id || id),
          type: offer,
          platform,
          line_key: `ps${platform}_${offer}_stock|game:${game.id || id}`,
          title: lineTitle,
          price,
        },
      });
      toast.success(t("digital.addedToCart"));
      router.push("/(tabs)/cart");
    } catch (e) {
      toast.error(e instanceof Error ? e.message : t("digital.stockFailed"));
    } finally {
      setPending(false);
    }
  };

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={styles.pad}>
        <RemoteImage uri={image} style={styles.image} contentFit="contain" />

        <Text
          style={[
            styles.stock,
            {
              color: activeInStock && activeOk ? accent : "#999",
              textAlign,
              writingDirection,
            },
          ]}
        >
          {activeInStock && activeOk
            ? t("catalog.inStock")
            : t("catalog.outOfStock")}
        </Text>

        <Text style={[styles.title, { textAlign, writingDirection }]}>{title}</Text>
        <Text style={[styles.meta, { textAlign, writingDirection }]}>
          {t("digital.platformLabel")} · PS{platform}
        </Text>

        {activeOk && activePrice > 0 ? (
          <Text style={[styles.heroPrice, { color: accent, textAlign }]}>
            {activePrice.toFixed(2)} EGP
          </Text>
        ) : null}

        <View style={styles.offers}>
          {primaryOk && primaryPrice > 0 ? (
            <Pressable
              onPress={() => setSelected("primary")}
              style={[
                styles.offer,
                activeOffer === "primary" ? { borderColor: accent, borderWidth: 2 } : null,
              ]}
            >
              <Text style={[styles.offerTitle, { textAlign, writingDirection }]}>
                {t("digital.primary")}
              </Text>
              <Text style={[styles.offerPrice, { color: accent, textAlign }]}>
                {primaryPrice.toFixed(2)} EGP
              </Text>
              <Text style={[styles.meta, { textAlign }]}>
                {primaryInStock ? t("catalog.inStock") : t("catalog.outOfStock")}
              </Text>
            </Pressable>
          ) : null}
          {secondaryOk && secondaryPrice > 0 ? (
            <Pressable
              onPress={() => setSelected("secondary")}
              style={[
                styles.offer,
                activeOffer === "secondary"
                  ? { borderColor: accent, borderWidth: 2 }
                  : null,
              ]}
            >
              <Text style={[styles.offerTitle, { textAlign, writingDirection }]}>
                {t("digital.secondary")}
              </Text>
              <Text style={[styles.offerPrice, { color: accent, textAlign }]}>
                {secondaryPrice.toFixed(2)} EGP
              </Text>
              <Text style={[styles.meta, { textAlign }]}>
                {secondaryInStock ? t("catalog.inStock") : t("catalog.outOfStock")}
              </Text>
            </Pressable>
          ) : null}
        </View>

        <View style={styles.notice}>
          <Text style={[styles.noticeTitle, { textAlign, writingDirection }]}>
            {t("digital.importantNoticeTitle")}
          </Text>
          <Text style={[styles.noticeBody, { textAlign, writingDirection }]}>
            {t("digital.importantNoticeBody")}
          </Text>
        </View>

        {!primaryOk && !secondaryOk ? (
          <Text style={[styles.meta, { textAlign }]}>{t("digital.unavailable")}</Text>
        ) : (
          <PrimaryButton
            label={pending ? t("common.loading") : t("common.addToCart")}
            disabled={!activeOk || !activeInStock || pending}
            onPress={() => void addSelected()}
          />
        )}

        {waHref ? (
          <Pressable
            onPress={() => void Linking.openURL(waHref)}
            style={[styles.waBtn, { borderColor: accent }]}
          >
            <Text style={[styles.waText, { color: accent, textAlign }]}>
              {t("digital.askWhatsApp")}
            </Text>
          </Pressable>
        ) : null}

        <View style={styles.trust}>
          <Text style={[styles.trustItem, { textAlign, writingDirection }]}>
            {t("digital.trustInstant")}
          </Text>
          <Text style={[styles.trustItem, { textAlign, writingDirection }]}>
            {t("digital.trustSupport")}
          </Text>
          <Text style={[styles.trustItem, { textAlign, writingDirection }]}>
            {t("digital.trustGenuine")}
          </Text>
          <Pressable onPress={() => router.push("/stores")}>
            <Text style={[styles.trustLink, { color: accent, textAlign }]}>
              {t("digital.trustStores")}
            </Text>
          </Pressable>
        </View>

        {descriptionPlain ? (
          <View style={styles.section}>
            <Text style={[styles.sectionTitle, { textAlign, writingDirection }]}>
              {t("digital.aboutTitle")}
            </Text>
            <Text style={[styles.body, { textAlign, writingDirection }]}>
              {descriptionPlain}
            </Text>
          </View>
        ) : null}

        {alsoBought.length > 0 ? (
          <View style={styles.section}>
            <Text style={[styles.sectionTitle, { textAlign, writingDirection }]}>
              {t("digital.alsoBought")}
            </Text>
            <ScrollView horizontal showsHorizontalScrollIndicator={false}>
              {alsoBought.map((g) => {
                const thumb = absoluteMediaUrl(String(g.image_url || "")) || g.image_url;
                const price =
                  g.primary_price != null && Number(g.primary_price) > 0
                    ? Number(g.primary_price)
                    : g.secondary_price != null && Number(g.secondary_price) > 0
                      ? Number(g.secondary_price)
                      : null;
                return (
                  <Pressable
                    key={g.id}
                    style={styles.alsoCard}
                    onPress={() =>
                      router.push(`/games/${g.id}?platform=${platform}`)
                    }
                  >
                    <RemoteImage
                      uri={thumb || undefined}
                      style={styles.alsoImage}
                      contentFit="contain"
                    />
                    <Text numberOfLines={2} style={styles.alsoTitle}>
                      {g.title || g.name || `#${g.id}`}
                    </Text>
                    {price != null ? (
                      <Text style={[styles.alsoPrice, { color: accent }]}>
                        {price.toFixed(2)} EGP
                      </Text>
                    ) : null}
                  </Pressable>
                );
              })}
            </ScrollView>
          </View>
        ) : null}

        {faqs.length > 0 ? (
          <View style={styles.section}>
            <Text style={[styles.sectionTitle, { textAlign, writingDirection }]}>
              {t("digital.faqTitle")}
            </Text>
            {faqs.map((item, index) => {
              const open = faqOpen === index;
              return (
                <View key={`${item.question}-${index}`} style={styles.faqItem}>
                  <Pressable
                    onPress={() => setFaqOpen(open ? null : index)}
                    style={styles.faqQ}
                  >
                    <Text style={[styles.faqQText, { textAlign, writingDirection }]}>
                      {item.question}
                    </Text>
                    <Text style={styles.faqToggle}>{open ? "−" : "+"}</Text>
                  </Pressable>
                  {open ? (
                    <Text style={[styles.faqA, { textAlign, writingDirection }]}>
                      {item.answer}
                    </Text>
                  ) : null}
                </View>
              );
            })}
          </View>
        ) : null}
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pad: { padding: 16, gap: 12, paddingBottom: 40 },
  image: {
    width: "100%",
    height: 280,
    borderRadius: 16,
    backgroundColor: "#111",
  },
  stock: { fontWeight: "700", fontSize: 13, textTransform: "uppercase" },
  title: { fontSize: 24, fontWeight: "800", color: "#111" },
  meta: { color: "#666", fontSize: 14 },
  heroPrice: { fontSize: 28, fontWeight: "800" },
  offers: { gap: 10 },
  offer: {
    backgroundColor: "#fff",
    borderRadius: 14,
    padding: 16,
    gap: 6,
    borderWidth: 1,
    borderColor: "#eee",
  },
  offerTitle: { fontWeight: "800", fontSize: 16 },
  offerPrice: { fontWeight: "800", fontSize: 20 },
  notice: {
    backgroundColor: "#f4f4f4",
    borderRadius: 12,
    padding: 14,
    gap: 6,
    borderLeftWidth: 3,
    borderLeftColor: "#00d4aa",
  },
  noticeTitle: { fontWeight: "800", fontSize: 15 },
  noticeBody: { color: "#555", fontSize: 14, lineHeight: 20 },
  waBtn: {
    borderWidth: 1.5,
    borderRadius: 12,
    paddingVertical: 12,
    paddingHorizontal: 16,
    alignItems: "center",
  },
  waText: { fontWeight: "700", fontSize: 15 },
  trust: { gap: 6, marginTop: 4 },
  trustItem: { color: "#555", fontSize: 14 },
  trustLink: { fontWeight: "700", fontSize: 14, textDecorationLine: "underline" },
  section: { gap: 10, marginTop: 8 },
  sectionTitle: { fontSize: 18, fontWeight: "800", color: "#111" },
  body: { color: "#444", fontSize: 15, lineHeight: 22 },
  alsoCard: { width: 140, marginEnd: 12, gap: 6 },
  alsoImage: {
    width: 140,
    height: 140,
    borderRadius: 12,
    backgroundColor: "#eee",
  },
  alsoTitle: { fontWeight: "700", fontSize: 13, color: "#111" },
  alsoPrice: { fontWeight: "700", fontSize: 13 },
  faqItem: {
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "#ddd",
    paddingBottom: 8,
  },
  faqQ: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 8,
    paddingVertical: 8,
  },
  faqQText: { flex: 1, fontWeight: "700", fontSize: 15, color: "#111" },
  faqToggle: { fontSize: 20, color: "#666" },
  faqA: { color: "#555", fontSize: 14, lineHeight: 20, paddingBottom: 8 },
});
