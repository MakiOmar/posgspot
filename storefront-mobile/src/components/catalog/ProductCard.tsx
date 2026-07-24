import FontAwesome from "@expo/vector-icons/FontAwesome";
import { useRouter } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Image,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { fetchAvailability } from "../../lib/api";
import { productPath } from "../../lib/product-path";
import { absoluteMediaUrl } from "../../lib/storefront-href";
import type { ProductAvailability, ProductSummary } from "../../lib/types";
import { useApp } from "../../contexts/AppContext";
import { useCart } from "../../contexts/CartContext";
import { useWishlist } from "../../contexts/WishlistContext";
import { AvailabilityModal } from "./AvailabilityModal";

function productDisplayPrice(product: ProductSummary): number {
  const sale = product.storefront_sale_price_inc_tax;
  if (sale != null && Number(sale) > 0) {
    return Number(sale);
  }
  return Number(product.price ?? product.price_inc_tax ?? 0);
}

/**
 * Catalog card parity with Qwik ProductCard: wishlist, cart / options / availability.
 */
export function ProductCard({
  product,
  wide = false,
}: {
  product: ProductSummary;
  wide?: boolean;
}) {
  const { accent, t, settings, locale } = useApp();
  const { addItem } = useCart();
  const { isInWishlist, toggle } = useWishlist();
  const router = useRouter();
  const [adding, setAdding] = useState(false);
  const [wishBusy, setWishBusy] = useState(false);
  const [availOpen, setAvailOpen] = useState(false);
  const [availLoading, setAvailLoading] = useState(false);
  const [availError, setAvailError] = useState<string | null>(null);
  const [availability, setAvailability] = useState<ProductAvailability | null>(
    null,
  );

  const price = productDisplayPrice(product);
  const compare =
    product.compare_at_price != null && Number(product.compare_at_price) > price
      ? Number(product.compare_at_price)
      : null;
  const image = absoluteMediaUrl(product.image_url);
  const wished = isInWishlist(product.id);
  const outOfStock = product.in_stock === false;
  const showCardAvailability =
    outOfStock &&
    (settings?.catalog?.show_availability_on_cards ?? true) &&
    product.variation_id != null;
  const showActions = showCardAvailability || !outOfStock;
  const hasOptions = Boolean(product.has_options);

  const openPdp = () => router.push(productPath(product) as never);

  const onWishlist = async () => {
    if (wishBusy) {
      return;
    }
    setWishBusy(true);
    try {
      await toggle(product);
    } catch (e) {
      Alert.alert(
        t("common.error"),
        e instanceof Error ? e.message : t("common.error"),
      );
    } finally {
      setWishBusy(false);
    }
  };

  const onAddToCart = async () => {
    const variationId = product.variation_id;
    if (!variationId || adding) {
      return;
    }
    setAdding(true);
    try {
      await addItem({
        variationId,
        productId: product.id,
        name: product.name,
        slug: product.slug || String(product.id),
        imageUrl: product.image_url,
        unitPrice: price,
        quantity: 1,
      });
      Alert.alert(t("catalog.addedToCart"), product.name);
    } catch (e) {
      Alert.alert(
        t("common.error"),
        e instanceof Error ? e.message : t("common.error"),
      );
    } finally {
      setAdding(false);
    }
  };

  const onCheckAvailability = async () => {
    if (product.variation_id == null) {
      return;
    }
    setAvailOpen(true);
    setAvailLoading(true);
    setAvailError(null);
    setAvailability(null);
    try {
      const { data } = await fetchAvailability(
        product.id,
        product.variation_id,
        locale,
      );
      setAvailability(data);
    } catch (e) {
      setAvailError(
        e instanceof Error ? e.message : t("availability.loadError"),
      );
    } finally {
      setAvailLoading(false);
    }
  };

  return (
    <View style={StyleSheet.flatten([styles.card, wide && styles.cardWide])}>
      <View style={styles.media}>
        <Pressable onPress={openPdp} style={styles.mediaPress}>
          {image ? (
            <Image source={{ uri: image }} style={styles.cardImage} />
          ) : (
            <View style={styles.cardImagePlaceholder} />
          )}
          {product.on_sale ? (
            <View style={[styles.saleBadge, { backgroundColor: accent }]}>
              <Text style={styles.saleText}>
                {product.sale_percent && product.sale_percent > 0
                  ? `-${product.sale_percent}%`
                  : t("catalog.sale")}
              </Text>
            </View>
          ) : null}
        </Pressable>
        <Pressable
          style={styles.wishBtn}
          onPress={() => void onWishlist()}
          hitSlop={8}
          accessibilityRole="button"
          accessibilityLabel={t("common.wishlist")}
        >
          {wishBusy ? (
            <ActivityIndicator size="small" color={accent} />
          ) : (
            <FontAwesome
              name={wished ? "heart" : "heart-o"}
              size={16}
              color={wished ? "#E11D48" : "#333"}
            />
          )}
        </Pressable>
      </View>

      <Pressable onPress={openPdp}>
        <Text numberOfLines={2} style={styles.cardTitle}>
          {product.name}
        </Text>
      </Pressable>
      <View style={styles.priceRow}>
        <Text style={{ ...styles.cardPrice, color: accent }}>
          {price.toFixed(2)} EGP
        </Text>
        {compare ? (
          <Text style={styles.compare}>{compare.toFixed(2)}</Text>
        ) : null}
      </View>
      <Text style={[styles.stock, outOfStock ? styles.oos : styles.ins]}>
        {outOfStock ? t("catalog.outOfStock") : t("catalog.inStock")}
      </Text>

      {showActions ? (
        <View style={styles.actions}>
          {showCardAvailability ? (
            <Pressable
              style={styles.secondaryBtn}
              onPress={() => void onCheckAvailability()}
            >
              <Text style={styles.secondaryText}>
                {t("catalog.checkAvailability")}
              </Text>
            </Pressable>
          ) : hasOptions ? (
            <Pressable style={styles.secondaryBtn} onPress={openPdp}>
              <Text style={styles.secondaryText}>
                {t("catalog.viewOptions")}
              </Text>
            </Pressable>
          ) : (
            <Pressable
              style={[
                styles.primaryBtn,
                {
                  backgroundColor: accent,
                  opacity: !product.variation_id || adding ? 0.5 : 1,
                },
              ]}
              disabled={!product.variation_id || adding}
              onPress={() => void onAddToCart()}
            >
              {adding ? (
                <ActivityIndicator color="#fff" />
              ) : (
                <FontAwesome name="shopping-bag" size={14} color="#111" />
              )}
              <Text style={styles.primaryText}>
                {adding ? t("catalog.addingToCart") : t("common.addToCart")}
              </Text>
            </Pressable>
          )}
        </View>
      ) : null}

      <AvailabilityModal
        open={availOpen}
        loading={availLoading}
        error={availError}
        availability={availability}
        onClose={() => setAvailOpen(false)}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    width: "48%",
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 10,
    marginBottom: 12,
  },
  cardWide: { width: "100%" },
  media: { position: "relative", marginBottom: 8 },
  mediaPress: { width: "100%" },
  cardImage: {
    width: "100%",
    height: 120,
    borderRadius: 8,
    backgroundColor: "#eee",
  },
  cardImagePlaceholder: {
    width: "100%",
    height: 120,
    borderRadius: 8,
    backgroundColor: "#e5e5e5",
  },
  saleBadge: {
    position: "absolute",
    top: 8,
    left: 8,
    borderRadius: 6,
    paddingHorizontal: 6,
    paddingVertical: 3,
  },
  saleText: { fontSize: 11, fontWeight: "800", color: "#111" },
  wishBtn: {
    position: "absolute",
    top: 8,
    right: 8,
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: "#fff",
    alignItems: "center",
    justifyContent: "center",
    elevation: 2,
    shadowColor: "#000",
    shadowOpacity: 0.12,
    shadowRadius: 3,
    shadowOffset: { width: 0, height: 1 },
  },
  cardTitle: { fontSize: 14, fontWeight: "600", marginBottom: 4, minHeight: 36 },
  priceRow: { flexDirection: "row", alignItems: "center", gap: 6 },
  cardPrice: { fontSize: 14, fontWeight: "700" },
  compare: {
    fontSize: 12,
    color: "#999",
    textDecorationLine: "line-through",
  },
  stock: { marginTop: 4, fontSize: 11, fontWeight: "600" },
  ins: { color: "#0B6E4F" },
  oos: { color: "#B00020" },
  actions: { marginTop: 8 },
  primaryBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
    borderRadius: 10,
    paddingVertical: 10,
    paddingHorizontal: 8,
  },
  primaryText: { color: "#111", fontWeight: "800", fontSize: 12 },
  secondaryBtn: {
    borderRadius: 10,
    paddingVertical: 10,
    paddingHorizontal: 8,
    borderWidth: 1,
    borderColor: "#ddd",
    backgroundColor: "#fafafa",
    alignItems: "center",
  },
  secondaryText: {
    color: "#222",
    fontWeight: "700",
    fontSize: 11,
    textAlign: "center",
  },
});
