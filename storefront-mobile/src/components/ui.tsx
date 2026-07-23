import React from "react";
import {
  ActivityIndicator,
  Image,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { Link } from "expo-router";
import type { ProductSummary } from "../lib/types";
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
        <Pressable style={[styles.button, { backgroundColor: accent }]} onPress={onRetry}>
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
}: {
  label: string;
  onPress: () => void;
  disabled?: boolean;
}) {
  const { accent } = useApp();
  return (
    <Pressable
      style={[
        styles.button,
        { backgroundColor: accent, opacity: disabled ? 0.5 : 1 },
      ]}
      disabled={disabled}
      onPress={onPress}
    >
      <Text style={styles.buttonText}>{label}</Text>
    </Pressable>
  );
}

export function ProductCard({ product }: { product: ProductSummary }) {
  const { accent } = useApp();
  const price =
    product.storefront_sale_price_inc_tax ?? product.price_inc_tax ?? 0;
  return (
    <Link href={`/products/${product.slug}`} asChild>
      <Pressable style={styles.card}>
        {product.image_url ? (
          <Image source={{ uri: product.image_url }} style={styles.cardImage} />
        ) : (
          <View style={[styles.cardImage, styles.cardImagePlaceholder]} />
        )}
        <Text numberOfLines={2} style={styles.cardTitle}>
          {product.name}
        </Text>
        <Text style={[styles.cardPrice, { color: accent }]}>
          {Number(price).toFixed(2)} EGP
        </Text>
      </Pressable>
    </Link>
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
  cardImage: {
    width: "100%",
    height: 120,
    borderRadius: 8,
    marginBottom: 8,
    backgroundColor: "#eee",
  },
  cardImagePlaceholder: { backgroundColor: "#e5e5e5" },
  cardTitle: { fontSize: 14, fontWeight: "600", marginBottom: 4 },
  cardPrice: { fontSize: 14, fontWeight: "700" },
});
