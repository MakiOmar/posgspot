import { useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import {
  Image,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { fetchAvailability, fetchProduct, fetchProductReviews } from "../../src/lib/api";
import { useApp } from "../../src/contexts/AppContext";
import { useCart } from "../../src/contexts/CartContext";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../src/components/ui";
import { absoluteMediaUrl } from "../../src/lib/storefront-href";
import type { ProductDetail } from "../../src/lib/types";

export default function ProductScreen() {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const { locale, t, accent } = useApp();
  const { addItem } = useCart();
  const router = useRouter();
  const [product, setProduct] = useState<ProductDetail | null>(null);
  const [availability, setAvailability] = useState<string>("");
  const [reviewsCount, setReviewsCount] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    if (!slug) {
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const { data } = await fetchProduct(slug, locale);
      setProduct(data);
      try {
        const avail = await fetchAvailability(data.id, locale);
        const locs = (avail.data as { locations?: Array<{ name: string; in_stock?: boolean }> })
          ?.locations;
        if (Array.isArray(locs)) {
          const inStock = locs.filter((l) => l.in_stock).map((l) => l.name);
          setAvailability(
            inStock.length
              ? `In stock: ${inStock.slice(0, 5).join(", ")}`
              : "Check stores for availability",
          );
        }
      } catch {
        // optional
      }
      try {
        const reviews = await fetchProductReviews(slug, locale);
        const items = (reviews.data as { items?: unknown[] })?.items;
        setReviewsCount(Array.isArray(items) ? items.length : 0);
      } catch {
        setReviewsCount(0);
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setLoading(false);
    }
  }, [slug, locale, t]);

  useEffect(() => {
    void load();
  }, [load]);

  if (loading) {
    return (
      <Screen>
        <LoadingBlock />
      </Screen>
    );
  }
  if (error || !product) {
    return (
      <Screen>
        <ErrorBlock message={error || undefined} onRetry={() => void load()} />
      </Screen>
    );
  }

  const variation = product.variations?.[0];
  const variationId = variation?.id ?? product.id;
  const price =
    variation?.storefront_sale_price_inc_tax ??
    variation?.price_inc_tax ??
    product.storefront_sale_price_inc_tax ??
    product.price ??
    product.price_inc_tax ??
    0;
  const image = absoluteMediaUrl(product.images?.[0] || product.image_url);

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={styles.pad}>
        {image ? (
          <Image source={{ uri: image }} style={styles.image} />
        ) : null}
        <Text style={styles.title}>{product.name}</Text>
        <Text style={[styles.price, { color: accent }]}>
          {Number(price).toFixed(2)} EGP
        </Text>
        {reviewsCount > 0 ? (
          <Text style={styles.meta}>{reviewsCount} reviews</Text>
        ) : null}
        {availability ? <Text style={styles.meta}>{availability}</Text> : null}
        <Text style={styles.desc}>
          {(product.description || "").replace(/<[^>]+>/g, " ").slice(0, 600)}
        </Text>
        <PrimaryButton
          label={t("common.addToCart")}
          onPress={() => {
            void addItem({
              variationId,
              productId: product.id,
              name: product.name,
              slug: product.slug,
              imageUrl: product.image_url,
              unitPrice: Number(price),
              quantity: 1,
            }).then(() => router.push("/(tabs)/cart"));
          }}
        />
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pad: { padding: 16, paddingBottom: 40 },
  image: { width: "100%", height: 280, borderRadius: 12, marginBottom: 16 },
  title: { fontSize: 22, fontWeight: "800", marginBottom: 8 },
  price: { fontSize: 20, fontWeight: "700", marginBottom: 8 },
  meta: { color: "#666", marginBottom: 6 },
  desc: { color: "#333", lineHeight: 22, marginBottom: 20 },
});
