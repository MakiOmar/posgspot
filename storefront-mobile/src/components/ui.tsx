import React from "react";
import {
  ActivityIndicator,
  Image,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useRouter } from "expo-router";
import type { ProductSummary } from "../lib/types";
import { absoluteMediaUrl } from "../lib/storefront-href";
import { useApp } from "../contexts/AppContext";

export function Screen({
  children,
  padded = true,
}: {
  children: React.ReactNode;
  padded?: boolean;
}) {
  return (
    <View style={[styles.screen, padded && styles.padded]}>{children}</View>
  );
}

export function LoadingBlock() {
  const { t, accent } = useApp();
  return (
    <View style={styles.center}>
      <ActivityIndicator color={accent} />
      <Text style={styles.muted}>{t("common.loading")}</Text>
    </View>
  );
}

export function ErrorBlock({
  message,
  onRetry,
}: {
  message?: string;
  onRetry?: () => void;
}) {
  const { t, accent } = useApp();
  return (
    <View style={styles.center}>
      <Text style={styles.error}>{message || t("common.error")}</Text>
      {onRetry ? (
        <Pressable
          style={[styles.button, { backgroundColor: accent }]}
          onPress={onRetry}
        >
          <Text style={styles.buttonText}>{t("common.retry")}</Text>
        </Pressable>
      ) : null}
    </View>
  );
}

export function PrimaryButton({
  label,
  onPress,
  disabled,
  style,
  ...rest
}: {
  label: string;
  onPress?: () => void;
  disabled?: boolean;
  style?: object;
  [key: string]: unknown;
}) {
  const { accent } = useApp();
  return (
    <Pressable
      {...rest}
      style={StyleSheet.flatten([
        styles.button,
        { backgroundColor: accent, opacity: disabled ? 0.5 : 1 },
        style,
      ])}
      disabled={disabled}
      onPress={onPress}
    >
      <Text style={styles.buttonText}>{label}</Text>
    </Pressable>
  );
}

function productDisplayPrice(product: ProductSummary): number {
  const sale = product.storefront_sale_price_inc_tax;
  if (sale != null && Number(sale) > 0) {
    return Number(sale);
  }
  return Number(product.price ?? product.price_inc_tax ?? 0);
}

export function ProductCard({
  product,
  wide = false,
}: {
  product: ProductSummary;
  wide?: boolean;
}) {
  const { accent } = useApp();
  const router = useRouter();
  const price = productDisplayPrice(product);
  const compare =
    product.compare_at_price != null && Number(product.compare_at_price) > price
      ? Number(product.compare_at_price)
      : null;
  const image = absoluteMediaUrl(product.image_url);

  return (
    <Pressable
      style={StyleSheet.flatten([styles.card, wide && styles.cardWide])}
      onPress={() => router.push(`/products/${product.slug}` as never)}
    >
      {image ? (
        <Image source={{ uri: image }} style={styles.cardImage} />
      ) : (
        <View style={styles.cardImagePlaceholder} />
      )}
      <Text numberOfLines={2} style={styles.cardTitle}>
        {product.name}
      </Text>
      <View style={styles.priceRow}>
        <Text style={{ ...styles.cardPrice, color: accent }}>
          {price.toFixed(2)} EGP
        </Text>
        {compare ? (
          <Text style={styles.compare}>{compare.toFixed(2)}</Text>
        ) : null}
      </View>
      {product.in_stock === false ? (
        <Text style={styles.oos}>Out of stock</Text>
      ) : null}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: "#F7F7F5" },
  padded: { padding: 16 },
  center: { flex: 1, alignItems: "center", justifyContent: "center", gap: 12 },
  muted: { color: "#666" },
  error: { color: "#B00020", textAlign: "center" },
  button: {
    paddingVertical: 14,
    paddingHorizontal: 18,
    borderRadius: 10,
    alignItems: "center",
  },
  buttonText: { color: "#fff", fontWeight: "700" },
  card: {
    width: "48%",
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 10,
    marginBottom: 12,
  },
  cardWide: { width: "100%" },
  cardImage: {
    width: "100%",
    height: 120,
    borderRadius: 8,
    marginBottom: 8,
    backgroundColor: "#eee",
  },
  cardImagePlaceholder: {
    width: "100%",
    height: 120,
    borderRadius: 8,
    marginBottom: 8,
    backgroundColor: "#e5e5e5",
  },
  cardTitle: { fontSize: 14, fontWeight: "600", marginBottom: 4, minHeight: 36 },
  priceRow: { flexDirection: "row", alignItems: "center", gap: 6 },
  cardPrice: { fontSize: 14, fontWeight: "700" },
  compare: {
    fontSize: 12,
    color: "#999",
    textDecorationLine: "line-through",
  },
  oos: { marginTop: 4, fontSize: 12, color: "#B00020" },
});
