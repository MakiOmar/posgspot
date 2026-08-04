import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Dimensions,
  FlatList,
  Pressable,
  Share,
  StyleSheet,
  Text,
  View,
  Platform,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import {
  fetchAvailability,
  fetchProduct,
  fetchProductReviews,
  fetchReviewEligibility,
  submitProductReview,
} from "../../src/lib/api";
import { useApp } from "../../src/contexts/AppContext";
import { useCart } from "../../src/contexts/CartContext";
import { useWishlist } from "../../src/contexts/WishlistContext";
import { AvailabilityModal } from "../../src/components/catalog/AvailabilityModal";
import { ProductCard } from "../../src/components/catalog/ProductCard";
import { StarRating } from "../../src/components/catalog/StarRating";
import { ProductGallery } from "../../src/components/pdp/ProductGallery";
import { ProductReviews } from "../../src/components/pdp/ProductReviews";
import { ProductStickyBar } from "../../src/components/pdp/ProductStickyBar";
import {
  ErrorBlock,
  FormScrollView,
  LoadingBlock,
  Screen,
} from "../../src/components/ui";
import { STOREFRONT_WEB_URL } from "../../src/lib/config";
import { absoluteMediaUrl } from "../../src/lib/storefront-href";
import { paramString } from "../../src/lib/product-path";
import { useRtl } from "../../src/lib/rtl";
import { toast } from "../../src/lib/toast";
import type {
  ProductAvailability,
  ProductDetail,
  ProductReviewItem,
  ReviewEligibility,
} from "../../src/lib/types";

const SCREEN_W = Dimensions.get("window").width;

export default function ProductScreen() {
  const params = useLocalSearchParams<{ slug: string | string[] }>();
  const idOrSlug = paramString(params.slug);
  const { locale, t, accent, token } = useApp();
  const { addItem } = useCart();
  const { isInWishlist, toggle } = useWishlist();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { row, textAlign, writingDirection } = useRtl();

  const [product, setProduct] = useState<ProductDetail | null>(null);
  const [variationId, setVariationId] = useState<number | null>(null);
  const [qty, setQty] = useState(1);
  const [galleryIndex, setGalleryIndex] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [adding, setAdding] = useState(false);

  const [availOpen, setAvailOpen] = useState(false);
  const [availLoading, setAvailLoading] = useState(false);
  const [availError, setAvailError] = useState<string | null>(null);
  const [availability, setAvailability] = useState<ProductAvailability | null>(null);

  const [reviews, setReviews] = useState<ProductReviewItem[]>([]);
  const [eligibility, setEligibility] = useState<ReviewEligibility | null>(null);
  const [reviewRating, setReviewRating] = useState(5);
  const [reviewTitle, setReviewTitle] = useState("");
  const [reviewBody, setReviewBody] = useState("");
  const [reviewBusy, setReviewBusy] = useState(false);

  const load = useCallback(async () => {
    if (!idOrSlug) {
      setLoading(false);
      setError(t("common.error"));
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const { data } = await fetchProduct(idOrSlug, locale);
      setProduct(data);
      const firstVar = data.variations?.[0]?.id ?? data.variation_id ?? null;
      setVariationId(firstVar);
      setQty(1);
      setGalleryIndex(0);

      const reviewKey = data.slug || String(data.id);
      const [revResult, elResult] = await Promise.all([
        fetchProductReviews(reviewKey, 1, 20, locale).catch(() => null),
        token
          ? fetchReviewEligibility(reviewKey, token, locale).catch(() => null)
          : Promise.resolve(null),
      ]);
      setReviews(
        revResult && Array.isArray(revResult.data) ? revResult.data : [],
      );
      setEligibility(elResult?.data ?? null);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
      setProduct(null);
    } finally {
      setLoading(false);
    }
  }, [idOrSlug, locale, t, token]);

  useEffect(() => {
    void load();
  }, [load]);

  const variation = useMemo(
    () => product?.variations?.find((v) => v.id === variationId) ?? product?.variations?.[0],
    [product, variationId],
  );

  const price = useMemo(() => {
    if (!product) return 0;
    return Number(
      variation?.storefront_sale_price_inc_tax ??
        variation?.price ??
        variation?.price_inc_tax ??
        product.storefront_sale_price_inc_tax ??
        product.price ??
        product.price_inc_tax ??
        0,
    );
  }, [product, variation]);

  const images = useMemo(() => {
    if (!product) return [] as string[];
    const list = [
      ...(product.images || []),
      ...(variation?.images || []),
      product.image_url || "",
    ]
      .map((u) => absoluteMediaUrl(u))
      .filter((u): u is string => !!u);
    return Array.from(new Set(list));
  }, [product, variation]);

  const inStock = variation?.in_stock ?? product?.in_stock ?? true;
  const wished = product ? isInWishlist(product.id) : false;

  const onAddToCart = async () => {
    if (!product || !variationId || adding) return;
    setAdding(true);
    try {
      await addItem({
        variationId,
        productId: product.id,
        name: product.name,
        slug: product.slug || String(product.id),
        imageUrl: images[0] || product.image_url || null,
        unitPrice: price,
        quantity: qty,
      });
      toast.success(t("catalog.addedToCart"), product.name);
      router.push("/(tabs)/cart");
    } catch (e) {
      toast.error(
        t("common.error"),
        e instanceof Error ? e.message : t("common.error"),
      );
    } finally {
      setAdding(false);
    }
  };

  const onCheckAvailability = async () => {
    if (!product || variationId == null) return;
    setAvailOpen(true);
    setAvailLoading(true);
    setAvailError(null);
    setAvailability(null);
    try {
      const { data } = await fetchAvailability(product.id, variationId, locale);
      setAvailability(data);
    } catch (e) {
      setAvailError(e instanceof Error ? e.message : t("availability.loadError"));
    } finally {
      setAvailLoading(false);
    }
  };

  const onShare = async () => {
    if (!product) return;
    const path = `/products/${encodeURIComponent(product.slug || String(product.id))}`;
    const url = `${STOREFRONT_WEB_URL}/${locale}${path}`;
    try {
      await Share.share(
        Platform.OS === "ios"
          ? { title: product.name, message: product.name, url }
          : { title: product.name, message: `${product.name}\n${url}` },
        { dialogTitle: t("share.label") },
      );
    } catch (e) {
      // User dismiss is not an error on some platforms.
      if (e && typeof e === "object" && "message" in e) {
        const msg = String((e as { message?: string }).message || "");
        if (/cancel|dismiss/i.test(msg)) return;
      }
      toast.error(t("common.error"), t("share.failed"));
    }
  };

  const onSubmitReview = async () => {
    if (!product || !token || !reviewBody.trim()) return;
    setReviewBusy(true);
    try {
      const key = product.slug || String(product.id);
      await submitProductReview(
        key,
        token,
        { rating: reviewRating, title: reviewTitle.trim() || undefined, body: reviewBody.trim() },
        locale,
      );
      toast.success(t("reviews.submitted"));
      setReviewBody("");
      setReviewTitle("");
      const rev = await fetchProductReviews(key, 1, 20, locale);
      setReviews(Array.isArray(rev.data) ? rev.data : []);
      const el = await fetchReviewEligibility(key, token, locale);
      setEligibility(el.data);
    } catch (e) {
      toast.error(
        t("common.error"),
        e instanceof Error ? e.message : t("common.error"),
      );
    } finally {
      setReviewBusy(false);
    }
  };

  if (loading) {
    return (
      <Screen>
        <Stack.Screen options={{ title: t("common.loading") }} />
        <LoadingBlock />
      </Screen>
    );
  }
  if (error || !product) {
    return (
      <Screen>
        <Stack.Screen options={{ title: t("nav.shop") }} />
        <ErrorBlock message={error || undefined} onRetry={() => void load()} />
      </Screen>
    );
  }

  const stickyPad = 88 + Math.max(insets.bottom, 8);

  return (
    <Screen padded={false} avoidKeyboard={false}>
      <Stack.Screen options={{ title: product.name }} />
      <View style={styles.body}>
        <FormScrollView
          style={styles.scroll}
          contentContainerStyle={[styles.pad, { paddingBottom: stickyPad }]}
          bottomInset={stickyPad}
        >
          <ProductGallery
            images={images}
            galleryIndex={galleryIndex}
            onGalleryIndexChange={setGalleryIndex}
            wished={wished}
            onShare={() => void onShare()}
            onToggleWishlist={() =>
              void toggle(product).catch((e) =>
                toast.error(t("common.error"), String(e)),
              )
            }
          />

          <View style={[styles.titleRow, { flexDirection: row }]}>
            <Text style={[styles.title, { textAlign, writingDirection }]}>
              {product.name}
            </Text>
          </View>

          <Text
            style={[styles.price, { color: accent, textAlign, writingDirection }]}
          >
            {price.toFixed(2)} EGP
          </Text>
          {(product.rating?.count ?? product.rating_count ?? 0) > 0 ? (
            <View style={styles.ratingWrap}>
              <StarRating
                average={Number(
                  product.rating?.average ?? product.rating_average ?? 0,
                )}
                count={Number(
                  product.rating?.count ?? product.rating_count ?? 0,
                )}
                size="md"
              />
            </View>
          ) : null}
          <Text
            style={[
              styles.stock,
              {
                color: inStock ? "#0B6E4F" : "#B00020",
                textAlign,
                writingDirection,
              },
            ]}
          >
            {inStock ? t("catalog.inStock") : t("catalog.outOfStock")}
          </Text>
          {product.brand?.name ? (
            <Text style={[styles.meta, { textAlign, writingDirection }]}>
              {product.brand.name}
            </Text>
          ) : null}

          {(product.variations?.length || 0) > 1 ? (
            <View style={styles.vars}>
              <Text style={[styles.section, { textAlign, writingDirection }]}>
                {t("product.options")}
              </Text>
              <View style={[styles.varRow, { flexDirection: row }]}>
                {product.variations!.map((v) => {
                  const active = v.id === variationId;
                  return (
                    <Pressable
                      key={v.id}
                      style={[
                        styles.varChip,
                        active && {
                          borderColor: accent,
                          backgroundColor: "#fff8e8",
                        },
                      ]}
                      onPress={() => setVariationId(v.id)}
                    >
                      <Text style={styles.varText}>{v.name || `#${v.id}`}</Text>
                    </Pressable>
                  );
                })}
              </View>
            </View>
          ) : null}

          <Text style={[styles.desc, { textAlign, writingDirection }]}>
            {(product.description || "").replace(/<[^>]+>/g, " ").trim() ||
              t("product.noDescription")}
          </Text>

          {(product.related_products?.length || 0) > 0 ? (
            <View style={styles.related}>
              <Text style={[styles.section, { textAlign, writingDirection }]}>
                {t("product.related")}
              </Text>
              <FlatList
                horizontal
                data={product.related_products}
                keyExtractor={(item) => String(item.id)}
                initialNumToRender={3}
                windowSize={5}
                renderItem={({ item }) => (
                  <View style={{ width: SCREEN_W * 0.42, marginRight: 10 }}>
                    <ProductCard product={item} wide />
                  </View>
                )}
                showsHorizontalScrollIndicator={false}
              />
            </View>
          ) : null}

          <ProductReviews
            reviews={reviews}
            eligibility={eligibility}
            token={token}
            reviewRating={reviewRating}
            reviewTitle={reviewTitle}
            reviewBody={reviewBody}
            reviewBusy={reviewBusy}
            onRatingChange={setReviewRating}
            onTitleChange={setReviewTitle}
            onBodyChange={setReviewBody}
            onSubmit={() => void onSubmitReview()}
            onSignIn={() => router.push("/login")}
          />
        </FormScrollView>

        {variationId ? (
          <ProductStickyBar
            inStock={inStock}
            qty={qty}
            onQtyChange={setQty}
            adding={adding}
            onAddToCart={() => void onAddToCart()}
            onCheckAvailability={() => void onCheckAvailability()}
          />
        ) : null}
      </View>

      <AvailabilityModal
        open={availOpen}
        loading={availLoading}
        error={availError}
        availability={availability}
        onClose={() => setAvailOpen(false)}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  body: { flex: 1 },
  scroll: { flex: 1 },
  pad: { paddingBottom: 24 },
  titleRow: {
    alignItems: "flex-start",
    gap: 12,
    paddingHorizontal: 16,
    marginTop: 12,
  },
  title: { flex: 1, fontSize: 22, fontWeight: "800", marginBottom: 8 },
  ratingWrap: { paddingHorizontal: 16, marginBottom: 8 },
  price: { fontSize: 20, fontWeight: "700", paddingHorizontal: 16, marginBottom: 4 },
  stock: { paddingHorizontal: 16, fontWeight: "600", marginBottom: 6 },
  meta: { color: "#666", paddingHorizontal: 16, marginBottom: 6 },
  section: { fontSize: 16, fontWeight: "800", marginBottom: 8, color: "#111" },
  vars: { paddingHorizontal: 16, marginTop: 8 },
  varRow: { flexWrap: "wrap", gap: 8 },
  varChip: {
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    backgroundColor: "#fff",
  },
  varText: { fontWeight: "600", fontSize: 13 },
  desc: { color: "#333", lineHeight: 22, marginVertical: 16, paddingHorizontal: 16 },
  related: { marginTop: 20, paddingLeft: 16 },
});
