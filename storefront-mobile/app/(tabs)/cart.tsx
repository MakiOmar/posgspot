import { useCallback, useEffect, useState } from "react";
import {
  FlatList,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useRouter } from "expo-router";
import { validateCart, validateCoupons } from "../../src/lib/api";
import { toCartApiItem } from "../../src/lib/cart";
import { useCart } from "../../src/contexts/CartContext";
import { useApp } from "../../src/contexts/AppContext";
import { LabeledInput } from "../../src/components/LabeledInput";
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
  const [validation, setValidation] = useState<CartValidationResult | null>(
    null,
  );
  const [error, setError] = useState<string | null>(null);

  const runValidate = useCallback(async () => {
    if (!count) return;
    setValidating(true);
    setError(null);
    try {
      const { data } = await validateCart(items.map(toCartApiItem), {}, token);
      setValidation(data);
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
      setCouponDiscount(Number(data.coupon_discount ?? data.discount ?? 0));
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setValidating(false);
    }
  }, [count, items, token, setItems, t]);

  useEffect(() => {
    void runValidate();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [count]);

  const applyCoupon = async () => {
    if (!token) {
      setCouponMsg(t("checkout.couponNeedLogin"));
      return;
    }
    if (!coupon.trim()) return;
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
        (data as { coupon_discount?: number; discount?: number })
          .coupon_discount ??
          (data as { discount?: number }).discount ??
          0,
      );
      setCouponDiscount(discount);
      setCouponMsg(t("cart.couponApplied"));
      await runValidate();
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
      <Text style={styles.title}>{t("cart.title")}</Text>
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
              <Text style={styles.meta}>
                {item.unitPrice.toFixed(2)} EGP × {item.quantity}
              </Text>
              <Text style={styles.lineTotal}>
                {(item.unitPrice * item.quantity).toFixed(2)} EGP
              </Text>
            </View>
            {!item.digital ? (
              <View style={styles.qtyRow}>
                <PrimaryButton
                  label="−"
                  style={styles.qtyBtn}
                  onPress={() =>
                    void updateQty(item.variationId, item.quantity - 1)
                  }
                />
                <Text style={styles.qty}>{item.quantity}</Text>
                <PrimaryButton
                  label="+"
                  style={styles.qtyBtn}
                  onPress={() =>
                    void updateQty(item.variationId, item.quantity + 1)
                  }
                />
              </View>
            ) : (
              <Text style={styles.meta}>{t("cart.digitalQtyFixed")}</Text>
            )}
            <PrimaryButton
              label={t("cart.remove")}
              style={styles.removeBtn}
              onPress={() =>
                void removeItem(item.variationId, item.digital?.line_key)
              }
            />
          </View>
        )}
      />

      {settings?.promo_codes?.enabled_at_checkout ? (
        <View style={styles.couponBlock}>
          <LabeledInput
            label={t("cart.promoCode")}
            placeholder={t("cart.promoCodePlaceholder")}
            value={coupon}
            onChangeText={setCoupon}
            autoCapitalize="characters"
          />
          <PrimaryButton
            label={t("cart.apply")}
            onPress={() => void applyCoupon()}
          />
          {!token ? (
            <Text style={styles.hint}>{t("checkout.couponNeedLogin")}</Text>
          ) : null}
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
      <Text style={styles.shippingHint}>{t("cart.shippingAtCheckout")}</Text>
      <Text style={styles.total}>
        {t("cart.total")}: {total.toFixed(2)} EGP
      </Text>
      <PrimaryButton
        label={validating ? t("common.loading") : t("common.checkout")}
        disabled={validating || !!error}
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
  title: { fontSize: 22, fontWeight: "800", marginBottom: 12 },
  center: { flex: 1, justifyContent: "center", gap: 16 },
  empty: { textAlign: "center", fontSize: 16, color: "#666" },
  list: { flex: 1 },
  row: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 12,
    marginBottom: 10,
    gap: 10,
  },
  name: { fontWeight: "700", marginBottom: 4 },
  meta: { color: "#666", marginBottom: 2 },
  lineTotal: { fontWeight: "700" },
  qtyRow: { flexDirection: "row", alignItems: "center", gap: 8 },
  qtyBtn: { paddingVertical: 8, paddingHorizontal: 14 },
  qty: { minWidth: 24, textAlign: "center", fontWeight: "700" },
  removeBtn: { paddingVertical: 10 },
  subtotal: { fontSize: 16, fontWeight: "700", marginTop: 8 },
  discount: { fontSize: 14, color: "#0B6E4F", fontWeight: "600" },
  shippingHint: { color: "#666", marginTop: 4 },
  total: { fontSize: 18, fontWeight: "800", marginVertical: 8 },
  error: { color: "#B00020", marginBottom: 8 },
  couponBlock: { marginTop: 8, gap: 8 },
  couponMsg: { color: "#666", marginTop: 4, marginBottom: 4 },
  hint: { color: "#666", marginTop: 4 },
});
