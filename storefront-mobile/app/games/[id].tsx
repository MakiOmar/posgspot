import { useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  Dimensions,
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
  type ScrollView as ScrollViewType,
} from "react-native";
import {
  ApiError,
  checkDigitalGameStock,
  fetchDigitalGame,
  fetchDigitalGames,
  submitDigitalReview,
} from "../../src/lib/api";
import { absoluteMediaUrl } from "../../src/lib/storefront-href";
import type { DigitalGameSummary, DigitalSkus } from "../../src/lib/types";
import { useApp } from "../../src/contexts/AppContext";
import { useCart } from "../../src/contexts/CartContext";
import { RemoteImage } from "../../src/components/RemoteImage";
import { StarRating } from "../../src/components/catalog/StarRating";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../src/components/ui";
import {
  DIGITAL_OFFER_TYPES,
  digitalGalleryUrls,
  digitalOfferEnabled,
  digitalOfferInStock,
  digitalOfferPrice,
  digitalOfferStock,
  digitalReviewsFromGame,
  liveCheckStockIsOut,
  pickDefaultDigitalOffer,
  type DigitalOfferType,
} from "../../src/lib/digital-game";
import { toast } from "../../src/lib/toast";
import { useRtl } from "../../src/lib/rtl";
import { buildWhatsAppUrl, normalizeWhatsAppDigits } from "../../src/lib/whatsapp";

type Platform = "4" | "5";

const SCREEN_W = Dimensions.get("window").width;

function stripHtml(html: string): string {
  return html.replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();
}

function offerLabelKey(offer: DigitalOfferType): string {
  if (offer === "secondary") return "digital.secondary";
  if (offer === "full") return "digital.full";
  return "digital.primary";
}

export default function GameDetailScreen() {
  const { id, platform: platformParam } = useLocalSearchParams<{
    id: string;
    platform?: string;
  }>();
  const platform: Platform = platformParam === "5" ? "5" : "4";
  const { locale, t, accent, settings, contact, token } = useApp();
  const { textAlign, writingDirection, row } = useRtl();
  const { addItem } = useCart();
  const router = useRouter();
  const [game, setGame] = useState<Record<string, unknown> | null>(null);
  const [skus, setSkus] = useState<DigitalSkus | null>(null);
  const [alsoBought, setAlsoBought] = useState<DigitalGameSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [pending, setPending] = useState(false);
  const [selected, setSelected] = useState<DigitalOfferType>("primary");
  const [faqOpen, setFaqOpen] = useState<number | null>(null);
  const [galleryIndex, setGalleryIndex] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [reviewStars, setReviewStars] = useState(5);
  const [reviewComment, setReviewComment] = useState("");
  const [reviewBusy, setReviewBusy] = useState(false);
  const [reviewSubmitted, setReviewSubmitted] = useState(false);
  /** Live Accounts OOS flags keyed by offer type (mirrors Qwik `liveOut`). */
  const [liveOut, setLiveOut] = useState<Partial<Record<DigitalOfferType, boolean>>>(
    {},
  );
  const scrollRef = useRef<ScrollViewType>(null);
  const reviewsOffsetY = useRef(0);

  const scrollToReviews = () => {
    scrollRef.current?.scrollTo({
      y: Math.max(0, reviewsOffsetY.current - 16),
      animated: true,
    });
  };

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const gameId = Number(id);
      const [detailRes, listRes] = await Promise.all([
        fetchDigitalGame(gameId, locale),
        fetchDigitalGames(platform, 1, locale).catch(() => null),
      ]);
      const nextGame = detailRes.data.game;
      setGame(nextGame);
      setSkus(detailRes.data.skus);
      setSelected(pickDefaultDigitalOffer(nextGame, platform));
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

  // Confirm Accounts stock after load so an OOS offer cannot stay clickable.
  useEffect(() => {
    if (!game) {
      return;
    }
    let cancelled = false;
    setLiveOut({});
    const gameId = Number(game.id ?? id);
    void (async () => {
      const next: Partial<Record<DigitalOfferType, boolean>> = {};
      for (const offer of DIGITAL_OFFER_TYPES) {
        if (!digitalOfferInStock(game, platform, offer)) {
          next[offer] = true;
          continue;
        }
        try {
          const stockCheck = await checkDigitalGameStock({
            game_id: gameId,
            type: offer,
            platform,
          });
          next[offer] = liveCheckStockIsOut(
            stockCheck.data as { is_available?: boolean; stock?: number | string },
          );
        } catch (e) {
          next[offer] = e instanceof ApiError && e.status === 422;
        }
      }
      if (!cancelled) {
        setLiveOut(next);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [game, platform, id]);

  const faqs = settings?.digital?.pdp_faqs ?? [];
  const askWhatsApp =
    normalizeWhatsAppDigits(settings?.digital?.ask_whatsapp) ||
    normalizeWhatsAppDigits(settings?.contact?.whatsapp) ||
    null;

  const title = game ? String(game.title || game.name || `Game #${id}`) : "";
  const galleryImages = useMemo(() => {
    if (!game) return [];
    return digitalGalleryUrls(game, platform)
      .map((src) => absoluteMediaUrl(src) || src)
      .filter(Boolean);
  }, [game, platform]);
  const image = galleryImages[0] || "";

  const offerMeta = useMemo(() => {
    if (!game) {
      return DIGITAL_OFFER_TYPES.map((type) => ({
        type,
        ok: false,
        price: 0,
        inStock: false,
      }));
    }
    return DIGITAL_OFFER_TYPES.map((type) => {
      const ok = digitalOfferEnabled(game, platform, type);
      const price = digitalOfferPrice(game, platform, type);
      const inStock =
        digitalOfferInStock(game, platform, type) && !liveOut[type];
      return { type, ok, price, inStock };
    });
  }, [game, platform, liveOut]);

  const activeOffer: DigitalOfferType = useMemo(() => {
    const current = offerMeta.find((o) => o.type === selected);
    if (current && current.ok && current.price > 0) {
      return selected;
    }
    const fallback = offerMeta.find((o) => o.ok && o.price > 0);
    return fallback?.type ?? selected;
  }, [selected, offerMeta]);

  const active = offerMeta.find((o) => o.type === activeOffer);
  const activeInStock = active?.inStock ?? false;
  const activePrice = active?.price ?? 0;
  const activeOk = Boolean(active?.ok && activePrice > 0);
  const anyOfferOk = offerMeta.some((o) => o.ok && o.price > 0);

  const reviews = useMemo(
    () => (game ? digitalReviewsFromGame(game) : { average: 0, count: 0, items: [] }),
    [game],
  );

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
    const message = t("digital.askWhatsAppMessage", {
      title,
      platform,
      offer: t(offerLabelKey(activeOffer)),
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
    const sku = offer === "secondary" ? skus?.secondary : skus?.primary;
    if (!sku) {
      toast.error(t("digital.skuMissing"));
      return;
    }
    const price = digitalOfferPrice(game, platform, offer);
    const stock = digitalOfferStock(game, platform, offer);
    const offerEnabled = digitalOfferEnabled(game, platform, offer);
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

      const label = t(offerLabelKey(offer));
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

  const submitReview = async () => {
    if (!token) {
      router.push("/login");
      return;
    }
    const phone = (contact?.mobile || "").trim();
    if (!phone) {
      toast.error(t("digital.reviewPhoneMissing"));
      return;
    }
    setReviewBusy(true);
    try {
      await submitDigitalReview(
        {
          stars: reviewStars,
          comment: reviewComment.trim() || undefined,
          game_id: Number(game.id || id),
        },
        token,
        locale,
      );
      setReviewSubmitted(true);
      setReviewComment("");
      toast.success(t("digital.reviewPending"));
    } catch (e) {
      toast.error(e instanceof Error ? e.message : t("digital.reviewFailed"));
    } finally {
      setReviewBusy(false);
    }
  };

  return (
    <Screen padded={false}>
      <ScrollView ref={scrollRef} contentContainerStyle={styles.pad}>
        <View style={styles.galleryBox}>
          <ScrollView
            horizontal
            pagingEnabled
            showsHorizontalScrollIndicator={false}
            onMomentumScrollEnd={(e) => {
              const w = e.nativeEvent.layoutMeasurement.width;
              const i = Math.round(e.nativeEvent.contentOffset.x / Math.max(w, 1));
              setGalleryIndex(i);
            }}
          >
            {(galleryImages.length ? galleryImages : [null]).map((uri, idx) => (
              <RemoteImage
                key={`${uri || "ph"}-${idx}`}
                uri={uri || undefined}
                style={styles.image}
                contentFit="contain"
              />
            ))}
          </ScrollView>
          {galleryImages.length > 1 ? (
            <Text style={styles.galleryDots}>
              {galleryIndex + 1}/{galleryImages.length}
            </Text>
          ) : null}
        </View>

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

        <Pressable
          onPress={scrollToReviews}
          style={styles.ratingLink}
          accessibilityRole="link"
          accessibilityLabel={t("reviews.seeReviews")}
        >
          <StarRating
            average={reviews.average}
            count={reviews.count}
            size="sm"
          />
          <Text style={[styles.ratingCta, { color: accent, textAlign }]}>
            {t("reviews.seeReviews")}
          </Text>
        </Pressable>

        {activeOk && activePrice > 0 ? (
          <Text style={[styles.heroPrice, { color: accent, textAlign }]}>
            {activePrice.toFixed(2)} EGP
          </Text>
        ) : null}

        <View style={styles.offers}>
          {offerMeta.map(({ type, ok, price, inStock }) =>
            ok && price > 0 ? (
              <Pressable
                key={type}
                onPress={() => setSelected(type)}
                style={[
                  styles.offer,
                  activeOffer === type ? { borderColor: accent, borderWidth: 2 } : null,
                ]}
              >
                <View style={[styles.offerRow, { flexDirection: row }]}>
                  <Text
                    style={[styles.offerTitle, { textAlign, writingDirection, flex: 1 }]}
                  >
                    {t(offerLabelKey(type))}
                  </Text>
                  <Text style={[styles.offerPrice, { color: accent }]}>
                    {price.toFixed(2)} EGP
                  </Text>
                </View>
                <View
                  style={[
                    styles.stockPill,
                    inStock ? styles.stockPillIn : styles.stockPillOut,
                  ]}
                >
                  <Text
                    style={[
                      styles.stockPillText,
                      inStock ? styles.stockPillTextIn : styles.stockPillTextOut,
                    ]}
                  >
                    {inStock ? t("catalog.inStock") : t("catalog.outOfStock")}
                  </Text>
                </View>
              </Pressable>
            ) : null,
          )}
        </View>

        <View style={styles.notice}>
          <Text style={[styles.noticeTitle, { textAlign, writingDirection }]}>
            {t("digital.importantNoticeTitle")}
          </Text>
          <Text style={[styles.noticeBody, { textAlign, writingDirection }]}>
            {t("digital.importantNoticeBody")}
          </Text>
        </View>

        {!anyOfferOk ? (
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

        <View
          style={styles.section}
          onLayout={(e) => {
            reviewsOffsetY.current = e.nativeEvent.layout.y;
          }}
        >
          <Text style={[styles.sectionTitle, { textAlign, writingDirection }]}>
            {t("digital.reviewTitle")}
          </Text>
          {reviews.count > 0 ? (
            <Text style={[styles.meta, { textAlign }]}>
              {reviews.average.toFixed(1)} · {reviews.count} {t("digital.reviewTitle")}
            </Text>
          ) : (
            <Text style={[styles.meta, { textAlign }]}>{t("digital.reviewEmpty")}</Text>
          )}
          {reviews.items.map((r) => {
            const name = r.reviewer_name || "Customer";
            const initials = name
              .trim()
              .split(/\s+/)
              .filter(Boolean)
              .slice(0, 2)
              .map((p) => p[0])
              .join("")
              .toUpperCase() || "?";
            return (
              <View key={r.id || `${name}-${r.stars}`} style={styles.reviewCard}>
                <View style={[styles.reviewHead, { flexDirection: row }]}>
                  {r.avatar_url ? (
                    <RemoteImage
                      uri={absoluteMediaUrl(r.avatar_url) || r.avatar_url}
                      style={styles.reviewAvatarImg}
                    />
                  ) : (
                    <View style={styles.reviewAvatar}>
                      <Text style={styles.reviewAvatarText}>{initials}</Text>
                    </View>
                  )}
                  <View style={{ flex: 1, gap: 2 }}>
                    <Text style={[styles.reviewAuthor, { textAlign }]}>{name}</Text>
                    <StarRating average={r.stars} count={0} size="sm" showAverage={false} />
                  </View>
                </View>
                {r.comment ? (
                  <Text style={[styles.body, { textAlign, writingDirection }]}>{r.comment}</Text>
                ) : null}
              </View>
            );
          })}
          {reviewSubmitted ? (
            <Text style={[styles.meta, { textAlign }]}>{t("digital.reviewPending")}</Text>
          ) : !token ? (
            <View style={styles.reviewForm}>
              <Text style={[styles.meta, { textAlign, writingDirection }]}>
                {t("reviews.signIn")}
              </Text>
              <PrimaryButton
                label={t("auth.signIn")}
                onPress={() => router.push("/login")}
              />
            </View>
          ) : !(contact?.mobile || "").trim() ? (
            <View style={styles.reviewForm}>
              <Text style={[styles.meta, { textAlign, writingDirection }]}>
                {t("digital.reviewPhoneMissing")}
              </Text>
              <PrimaryButton
                label={t("digital.reviewUpdateProfile")}
                onPress={() => router.push("/account/profile")}
              />
            </View>
          ) : (
            <View style={styles.reviewForm}>
              <Text style={[styles.meta, { textAlign }]}>
                {t("digital.reviewAs", { phone: (contact?.mobile || "").trim() })}
              </Text>
              <View style={[styles.starsRow, { flexDirection: row }]}>
                {[1, 2, 3, 4, 5].map((n) => (
                  <Pressable key={n} onPress={() => setReviewStars(n)}>
                    <Text style={{ fontSize: 22, color: n <= reviewStars ? accent : "#ccc" }}>
                      ★
                    </Text>
                  </Pressable>
                ))}
              </View>
              <TextInput
                style={[styles.input, styles.inputMulti]}
                placeholder={t("digital.reviewCommentPlaceholder")}
                value={reviewComment}
                onChangeText={setReviewComment}
                multiline
              />
              <PrimaryButton
                label={reviewBusy ? t("common.loading") : t("digital.reviewSubmit")}
                disabled={reviewBusy}
                onPress={() => void submitReview()}
              />
            </View>
          )}
        </View>

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
                      : g.full_price != null && Number(g.full_price) > 0
                        ? Number(g.full_price)
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
                    <StarRating
                      average={Number(g.rating_average ?? 0)}
                      count={Number(g.rating_count ?? 0)}
                      size="sm"
                    />
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
  galleryBox: {
    borderRadius: 16,
    overflow: "hidden",
    backgroundColor: "#111",
    position: "relative",
  },
  image: {
    width: SCREEN_W - 32,
    height: 320,
    backgroundColor: "#111",
  },
  galleryDots: {
    position: "absolute",
    bottom: 10,
    alignSelf: "center",
    color: "#fff",
    backgroundColor: "rgba(0,0,0,0.45)",
    overflow: "hidden",
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    fontSize: 12,
    fontWeight: "700",
  },
  stock: { fontWeight: "700", fontSize: 13, textTransform: "uppercase" },
  title: { fontSize: 24, fontWeight: "800", color: "#111" },
  meta: { color: "#666", fontSize: 14 },
  ratingLink: { gap: 6, marginTop: 4 },
  ratingCta: { fontSize: 14, fontWeight: "600" },
  heroPrice: { fontSize: 28, fontWeight: "800" },
  offers: { gap: 10 },
  offer: {
    backgroundColor: "#fff",
    borderRadius: 14,
    padding: 16,
    gap: 10,
    borderWidth: 1,
    borderColor: "#eee",
  },
  offerRow: {
    alignItems: "center",
    justifyContent: "space-between",
    gap: 12,
  },
  offerTitle: { fontWeight: "800", fontSize: 16, color: "#111" },
  offerPrice: { fontWeight: "800", fontSize: 18 },
  stockPill: {
    alignSelf: "flex-start",
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
  },
  stockPillIn: { backgroundColor: "#dcfce7" },
  stockPillOut: { backgroundColor: "#fee2e2" },
  stockPillText: { fontSize: 12, fontWeight: "700" },
  stockPillTextIn: { color: "#166534" },
  stockPillTextOut: { color: "#991b1b" },
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
  reviewCard: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 12,
    gap: 8,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "#eee",
  },
  reviewHead: { gap: 10, alignItems: "center" },
  reviewAvatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: "#1f2937",
    alignItems: "center",
    justifyContent: "center",
  },
  reviewAvatarImg: {
    width: 40,
    height: 40,
    borderRadius: 20,
  },
  reviewAvatarText: { color: "#fff", fontWeight: "800", fontSize: 13 },
  reviewAuthor: { fontWeight: "700", fontSize: 15, color: "#111" },
  reviewStars: { color: "#f5a623", fontSize: 14 },
  reviewForm: { gap: 10 },
  starsRow: { gap: 6 },
  input: {
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    backgroundColor: "#fff",
  },
  inputMulti: { minHeight: 80, textAlignVertical: "top" },
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
