import { useCallback, useEffect, useState } from "react";
import {
  FlatList,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useRouter } from "expo-router";
import { validateCart, validateCoupons } from "../../src/lib/api";
import { toCartApiItem } from "../../src/lib/cart";
import { useCart } from "../../src/contexts/CartContext";
import { useApp } from "../../src/contexts/AppContext";
import { PrimaryButton, Screen } from "../../src/components/ui";
import type { CartValidationResult } from "../../src/lib/types";

export default function CartScreen() {
  const { t, token, settings } = useApp();
  const router = useRouter();
  const { items, subtotal, updateQty, removeItem, count, setItems } = useCart();
  const [coupon, setCoupon] = useState("");
  const [couponMsg, setCouponMsg] = useState<string | null>(null);
  const [couponDiscount, setCouponDiscount] = useState(0);
  const [validating, setValidating] = useState(false);
  const [validation, setValidation] = useState<CartValidationResult | null>(null);
  const [error, setError] = useState<string | null>(null);

  const runValidate = useCallback(async () => {
    if (!count) return;
    setValidating(true);
    setError(null);
    try {
      const { data } = await validateCart(items.map(toCartApiItem), {}, token);
      setValidation(data);
      // Sync unit prices when API returns them
      if (Array.isArray(data.items) && data.items.length) {
        const priceMap = new Map(
          data.items.map((l) => [l.variation_id, l.unit_price]),
        );
        const next = items.map((item) => {
          const apiPrice = priceMap.get(item.variationId);
          if (apiPrice == null || item.digital) return item;
          return { ...item, unitPrice: Number(apiPrice) };
        });
        const oos = data.items.filter((l) => l.in_stock === false);
        if (oos.length) {
          setError(
            oos.map((l) => l.name || `#${l.variation_id}`).join(", ") +
              " — " +
              t("cart.outOfStockLines"),
          );
        }
        await setItems(next);
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setValidating(false);
    }
  }, [count, items, token, setItems, t]);

  useEffect(() => {
    void runValidate();
    // eslint-disable-next-line react-hooks/exhaustive-deps -- validate when cart count/identity changes
  }, [count]);

  const applyCoupon = async () => {
    if (!token || !coupon.trim()) return;
    setCouponMsg(null);
    try {
      const { data } = await validateCoupons(
        {
          code: coupon.trim(),
          items: items.map(toCartApiItem),
        },
        token,
      );
      const discount = Number(
        (data as { coupon_discount?: number; discount?: number }).coupon_discount ??
          (data as { discount?: number }).discount ??
          0,
      );
      setCouponDiscount(discount);
      setCouponMsg(t("cart.couponApplied"));
    } catch (e) {
      setCouponDiscount(0);
      setCouponMsg(e instanceof Error ? e.message : t("common.error"));
    }
  };

  if (!count) {
    return (
      <Screen>
        <View style={styles.center}>
          <Text style={styles.empty}>{t("cart.empty")}</Text>
          <PrimaryButton
            label={t("nav.shop")}
            onPress={() => router.push("/(tabs)/shop")}
          />
        </View>
      </Screen>
    );
  }

  const displaySubtotal = Number(validation?.subtotal ?? subtotal);
  const total = Math.max(0, displaySubtotal - couponDiscount);

  return (
    <Screen>
      {error ? <Text style={styles.error}>{error}</Text> : null}
      <FlatList
        style={styles.list}
        data={items}
        keyExtractor={(item) =>
          item.digital?.line_key || String(item.variationId)
        }
        renderItem={({ item }) => (
          <View style={styles.row}>
            <View style={{ flex: 1 }}>
              <Text style={styles.name}>{item.name}</Text>
              <Text>
                {(item.unitPrice * item.quantity).toFixed(2)} EGP
              </Text>
            </View>
            {!item.digital ? (
              <View style={styles.qtyRow}>
                <PrimaryButton
                  label="-"
                  onPress={() =>
                    void updateQty(item.variationId, item.quantity - 1)
                  }
                />
                <Text style={styles.qty}>{item.quantity}</Text>
                <PrimaryButton
                  label="+"
                  onPress={() =>
                    void updateQty(item.variationId, item.quantity + 1)
                  }
                />
              </View>
            ) : null}
            <PrimaryButton
              label="×"
              onPress={() =>
                void removeItem(item.variationId, item.digital?.line_key)
              }
            />
          </View>
        )}
      />

      {token && settings?.promo_codes?.enabled_at_checkout ? (
        <View style={styles.couponRow}>
          <TextInput
            style={styles.couponInput}
            placeholder={t("cart.promoCode")}
            value={coupon}
            onChangeText={setCoupon}
            autoCapitalize="characters"
          />
          <PrimaryButton label={t("cart.apply")} onPress={() => void applyCoupon()} />
        </View>
      ) : null}
      {couponMsg ? <Text style={styles.couponMsg}>{couponMsg}</Text> : null}

      <Text style={styles.subtotal}>
        {t("cart.subtotal")}: {displaySubtotal.toFixed(2)} EGP
      </Text>
      {couponDiscount > 0 ? (
        <Text style={styles.discount}>
          {t("cart.discount")}: −{couponDiscount.toFixed(2)} EGP
        </Text>
      ) : null}
      <Text style={styles.total}>
        {t("cart.total")}: {total.toFixed(2)} EGP
      </Text>
      <PrimaryButton
        label={validating ? t("common.loading") : t("common.checkout")}
        disabled={validating}
        onPress={() =>
          router.push({
            pathname: "/checkout",
            params: coupon.trim() ? { coupon: coupon.trim() } : {},
          })
        }
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, justifyContent: "center", gap: 16 },
  empty: { textAlign: "center", fontSize: 16, color: "#666" },
  list: { flex: 1 },
  row: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 12,
    marginBottom: 10,
    gap: 8,
  },
  name: { fontWeight: "700", marginBottom: 4 },
  qtyRow: { flexDirection: "row", alignItems: "center", gap: 8 },
  qty: { minWidth: 24, textAlign: "center", fontWeight: "700" },
  subtotal: { fontSize: 16, fontWeight: "700", marginTop: 8 },
  discount: { fontSize: 14, color: "#0B6E4F", fontWeight: "600" },
  total: { fontSize: 18, fontWeight: "800", marginVertical: 8 },
  error: { color: "#B00020", marginBottom: 8 },
  couponRow: { flexDirection: "row", gap: 8, alignItems: "center", marginTop: 8 },
  couponInput: {
    flex: 1,
    backgroundColor: "#fff",
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "#e5e5e5",
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  couponMsg: { color: "#666", marginTop: 4, marginBottom: 4 },
});
