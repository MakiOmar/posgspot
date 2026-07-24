import FontAwesome from "@expo/vector-icons/FontAwesome";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Alert,
  Dimensions,
  FlatList,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
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
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../src/components/ui";
import { absoluteMediaUrl } from "../../src/lib/storefront-href";
import { paramString } from "../../src/lib/product-path";
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
      try {
        const rev = await fetchProductReviews(reviewKey, 1, 20, locale);
        setReviews(Array.isArray(rev.data) ? rev.data : []);
      } catch {
        setReviews([]);
      }
      if (token) {
        try {
          const el = await fetchReviewEligibility(reviewKey, token, locale);
          setEligibility(el.data);
        } catch {
          setEligibility(null);
        }
      } else {
        setEligibility(null);
      }
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
      Alert.alert(t("catalog.addedToCart"), product.name);
      router.push("/(tabs)/cart");
    } catch (e) {
      Alert.alert(t("common.error"), e instanceof Error ? e.message : t("common.error"));
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
      Alert.alert(t("reviews.submitted"));
      setReviewBody("");
      setReviewTitle("");
      const rev = await fetchProductReviews(key, 1, 20, locale);
      setReviews(Array.isArray(rev.data) ? rev.data : []);
      const el = await fetchReviewEligibility(key, token, locale);
      setEligibility(el.data);
    } catch (e) {
      Alert.alert(t("common.error"), e instanceof Error ? e.message : t("common.error"));
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

  return (
    <Screen padded={false}>
      <Stack.Screen options={{ title: product.name }} />
      <ScrollView contentContainerStyle={styles.pad}>
        <ScrollView
          horizontal
          pagingEnabled
          showsHorizontalScrollIndicator={false}
          onMomentumScrollEnd={(e) => {
            const i = Math.round(e.nativeEvent.contentOffset.x / SCREEN_W);
            setGalleryIndex(i);
          }}
        >
          {(images.length ? images : [null]).map((uri, idx) =>
            uri ? (
              <Image key={`${uri}-${idx}`} source={{ uri }} style={styles.image} />
            ) : (
              <View key={`ph-${idx}`} style={[styles.image, styles.imagePh]} />
            ),
          )}
        </ScrollView>
        {images.length > 1 ? (
          <Text style={styles.dots}>
            {galleryIndex + 1}/{images.length}
          </Text>
        ) : null}

        <View style={styles.titleRow}>
          <Text style={styles.title}>{product.name}</Text>
          <Pressable
            style={styles.wish}
            onPress={() => void toggle(product).catch((e) => Alert.alert(t("common.error"), String(e)))}
          >
            <FontAwesome name={wished ? "heart" : "heart-o"} size={22} color={wished ? "#E11D48" : "#333"} />
          </Pressable>
        </View>

        <Text style={[styles.price, { color: accent }]}>{price.toFixed(2)} EGP</Text>
        <Text style={[styles.stock, { color: inStock ? "#0B6E4F" : "#B00020" }]}>
          {inStock ? t("catalog.inStock") : t("catalog.outOfStock")}
        </Text>
        {product.brand?.name ? <Text style={styles.meta}>{product.brand.name}</Text> : null}

        {(product.variations?.length || 0) > 1 ? (
          <View style={styles.vars}>
            <Text style={styles.section}>{t("product.options")}</Text>
            <View style={styles.varRow}>
              {product.variations!.map((v) => {
                const active = v.id === variationId;
                return (
                  <Pressable
                    key={v.id}
                    style={[styles.varChip, active && { borderColor: accent, backgroundColor: "#fff8e8" }]}
                    onPress={() => setVariationId(v.id)}
                  >
                    <Text style={styles.varText}>{v.name || `#${v.id}`}</Text>
                  </Pressable>
                );
              })}
            </View>
          </View>
        ) : null}

        <View style={styles.qtyRow}>
          <Text style={styles.section}>{t("product.quantity")}</Text>
          <View style={styles.qtyCtrl}>
            <Pressable style={styles.qtyBtn} onPress={() => setQty((q) => Math.max(1, q - 1))}>
              <Text style={styles.qtyBtnText}>−</Text>
            </Pressable>
            <Text style={styles.qty}>{qty}</Text>
            <Pressable style={styles.qtyBtn} onPress={() => setQty((q) => q + 1)}>
              <Text style={styles.qtyBtnText}>+</Text>
            </Pressable>
          </View>
        </View>

        <Text style={styles.desc}>
          {(product.description || "").replace(/<[^>]+>/g, " ").trim() || t("product.noDescription")}
        </Text>

        {inStock && variationId ? (
          <PrimaryButton
            label={adding ? t("catalog.addingToCart") : t("common.addToCart")}
            disabled={adding}
            onPress={() => void onAddToCart()}
          />
        ) : variationId ? (
          <PrimaryButton
            label={t("catalog.checkAvailability")}
            onPress={() => void onCheckAvailability()}
          />
        ) : null}

        {(product.related_products?.length || 0) > 0 ? (
          <View style={styles.related}>
            <Text style={styles.section}>{t("product.related")}</Text>
            <FlatList
              horizontal
              data={product.related_products}
              keyExtractor={(item) => String(item.id)}
              renderItem={({ item }) => (
                <View style={{ width: SCREEN_W * 0.42, marginRight: 10 }}>
                  <ProductCard product={item} wide />
                </View>
              )}
              showsHorizontalScrollIndicator={false}
            />
          </View>
        ) : null}

        <View style={styles.reviews}>
          <Text style={styles.section}>
            {t("reviews.title")} ({reviews.length})
          </Text>
          {reviews.map((r) => (
            <View key={r.id} style={styles.reviewCard}>
              <Text style={styles.reviewStars}>{"★".repeat(r.rating)}{"☆".repeat(Math.max(0, 5 - r.rating))}</Text>
              {r.title ? <Text style={styles.reviewTitle}>{r.title}</Text> : null}
              <Text style={styles.reviewBody}>{r.body}</Text>
              <Text style={styles.reviewMeta}>{r.author_name || "Customer"}</Text>
            </View>
          ))}
          {token && eligibility?.can_review ? (
            <View style={styles.reviewForm}>
              <Text style={styles.section}>{t("reviews.write")}</Text>
              <View style={styles.varRow}>
                {[1, 2, 3, 4, 5].map((n) => (
                  <Pressable key={n} onPress={() => setReviewRating(n)}>
                    <Text style={{ fontSize: 22, color: n <= reviewRating ? accent : "#ccc" }}>★</Text>
                  </Pressable>
                ))}
              </View>
              <TextInput
                style={styles.input}
                placeholder={t("reviews.titlePlaceholder")}
                value={reviewTitle}
                onChangeText={setReviewTitle}
              />
              <TextInput
                style={[styles.input, { height: 90, textAlignVertical: "top" }]}
                placeholder={t("reviews.bodyPlaceholder")}
                value={reviewBody}
                onChangeText={setReviewBody}
                multiline
              />
              <PrimaryButton
                label={reviewBusy ? t("common.loading") : t("reviews.submit")}
                disabled={reviewBusy || !reviewBody.trim()}
                onPress={() => void onSubmitReview()}
              />
            </View>
          ) : token && eligibility && !eligibility.can_review ? (
            <Text style={styles.meta}>{eligibility.message || t("reviews.notEligible")}</Text>
          ) : !token ? (
            <PrimaryButton label={t("reviews.signIn")} onPress={() => router.push("/login")} />
          ) : null}
        </View>
      </ScrollView>

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
  pad: { paddingBottom: 48 },
  image: { width: SCREEN_W, height: 300, backgroundColor: "#eee" },
  imagePh: { alignItems: "center", justifyContent: "center" },
  dots: { textAlign: "center", color: "#888", marginVertical: 8 },
  titleRow: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 12,
    paddingHorizontal: 16,
  },
  title: { flex: 1, fontSize: 22, fontWeight: "800", marginBottom: 8 },
  wish: { padding: 6 },
  price: { fontSize: 20, fontWeight: "700", paddingHorizontal: 16, marginBottom: 4 },
  stock: { paddingHorizontal: 16, fontWeight: "600", marginBottom: 6 },
  meta: { color: "#666", paddingHorizontal: 16, marginBottom: 6 },
  section: { fontSize: 16, fontWeight: "800", marginBottom: 8, color: "#111" },
  vars: { paddingHorizontal: 16, marginTop: 8 },
  varRow: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  varChip: {
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    backgroundColor: "#fff",
  },
  varText: { fontWeight: "600", fontSize: 13 },
  qtyRow: { paddingHorizontal: 16, marginTop: 12 },
  qtyCtrl: { flexDirection: "row", alignItems: "center", gap: 12 },
  qtyBtn: {
    width: 36,
    height: 36,
    borderRadius: 8,
    backgroundColor: "#eee",
    alignItems: "center",
    justifyContent: "center",
  },
  qtyBtnText: { fontSize: 20, fontWeight: "700" },
  qty: { minWidth: 28, textAlign: "center", fontWeight: "800", fontSize: 16 },
  desc: { color: "#333", lineHeight: 22, marginVertical: 16, paddingHorizontal: 16 },
  related: { marginTop: 20, paddingLeft: 16 },
  reviews: { paddingHorizontal: 16, marginTop: 24 },
  reviewCard: {
    backgroundColor: "#fff",
    borderRadius: 10,
    padding: 12,
    marginBottom: 8,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "#eee",
  },
  reviewStars: { color: "#F5A623", marginBottom: 4 },
  reviewTitle: { fontWeight: "700", marginBottom: 4 },
  reviewBody: { color: "#333", lineHeight: 20 },
  reviewMeta: { color: "#888", fontSize: 12, marginTop: 6 },
  reviewForm: { marginTop: 12, gap: 8 },
  input: {
    backgroundColor: "#fff",
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "#e5e5e5",
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
});
