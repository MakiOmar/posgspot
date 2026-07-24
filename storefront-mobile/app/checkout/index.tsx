import { useEffect, useMemo, useState } from "react";
import {
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useLocalSearchParams, useRouter } from "expo-router";
import {
  checkout,
  fetchLocations,
  fetchRewardPoints,
  validateCart,
  validateCoupons,
} from "../../src/lib/api";
import { toCartApiItem } from "../../src/lib/cart";
import { useApp } from "../../src/contexts/AppContext";
import { useCart } from "../../src/contexts/CartContext";
import { PrimaryButton, Screen } from "../../src/components/ui";
import type { ShippingRate, StoreLocation } from "../../src/lib/types";

export default function CheckoutScreen() {
  const { t, token, settings, locale, contact, accent } = useApp();
  const { items, clear, subtotal } = useCart();
  const router = useRouter();
  const params = useLocalSearchParams<{ coupon?: string }>();

  const [name, setName] = useState(contact?.name || "");
  const [mobile, setMobile] = useState(contact?.mobile || "");
  const [email, setEmail] = useState(contact?.email || "");
  const [address, setAddress] = useState("");
  const [city, setCity] = useState("");
  const [stateName, setStateName] = useState("");
  const [coupon, setCoupon] = useState(params.coupon || "");
  const [rewardPoints, setRewardPoints] = useState("");
  const [pointsBalance, setPointsBalance] = useState<number | null>(null);
  const [paymentMethod, setPaymentMethod] = useState<"cod" | "fawry">("cod");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  const [rates, setRates] = useState<ShippingRate[]>([]);
  const [selectedRateId, setSelectedRateId] = useState<string | null>(null);
  const [digitalOnly, setDigitalOnly] = useState(false);
  const [pickupLocations, setPickupLocations] = useState<StoreLocation[]>([]);
  const [pickupId, setPickupId] = useState<number | null>(null);
  const [shippingAmount, setShippingAmount] = useState(0);
  const [couponDiscount, setCouponDiscount] = useState(0);
  const [quoteSubtotal, setQuoteSubtotal] = useState(subtotal);

  const fawryEnabled =
    !!settings?.online_payments?.enabled &&
    settings?.online_payments?.provider === "fawry";
  const codEnabled = settings?.cod_enabled !== false;

  const idempotencyKey = useMemo(
    () => `mobile-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`,
    [],
  );

  const destination = {
    country: "EG",
    state: stateName || "Cairo",
    city: city || "Cairo",
  };

  const refreshQuote = async () => {
    if (!items.length) return;
    try {
      const couponExtras =
        coupon && token
          ? settings?.promo_codes?.allow_stacking
            ? { coupon_codes: [coupon] }
            : { coupon_code: coupon }
          : {};
      const { data } = await validateCart(
        items.map(toCartApiItem),
        {
          destination,
          location_id: pickupId || undefined,
          shipping_rate_id: selectedRateId || undefined,
          ...couponExtras,
        },
        token,
      );
      setDigitalOnly(!!data.digital_only);
      setRates(data.available_rates || []);
      const rateId =
        selectedRateId && data.available_rates?.some((r) => r.id === selectedRateId)
          ? selectedRateId
          : data.shipping_rate_id || data.available_rates?.[0]?.id || null;
      setSelectedRateId(rateId);
      const rate = data.available_rates?.find((r) => r.id === rateId);
      setShippingAmount(Number(rate?.amount ?? rate?.price ?? data.shipping ?? 0));
      setQuoteSubtotal(Number(data.subtotal ?? subtotal));
      setCouponDiscount(Number(data.coupon_discount ?? data.discount ?? 0));
    } catch (e) {
      setMessage(e instanceof Error ? e.message : t("common.error"));
    }
  };

  useEffect(() => {
    void refreshQuote();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [items.length, stateName, city, pickupId]);

  useEffect(() => {
    if (!token) return;
    void fetchRewardPoints(token)
      .then(({ data }) => {
        setPointsBalance(
          Number(data.balance ?? data.available ?? data.points ?? 0),
        );
      })
      .catch(() => setPointsBalance(null));
    void fetchLocations(true, locale)
      .then(({ data }) => setPickupLocations(data || []))
      .catch(() => setPickupLocations([]));
  }, [token, locale]);

  const applyCoupon = async () => {
    if (!token || !coupon.trim()) return;
    try {
      const { data } = await validateCoupons(
        { code: coupon.trim(), items: items.map(toCartApiItem) },
        token,
      );
      setCouponDiscount(
        Number(
          (data as { coupon_discount?: number }).coupon_discount ??
            (data as { discount?: number }).discount ??
            0,
        ),
      );
      await refreshQuote();
      setMessage(t("cart.couponApplied"));
    } catch (e) {
      setMessage(e instanceof Error ? e.message : t("common.error"));
    }
  };

  const placeOrder = async () => {
    setBusy(true);
    setMessage(null);
    try {
      await refreshQuote();
      const apiItems = items.map(toCartApiItem);
      if (!selectedRateId && !digitalOnly) {
        throw new Error(t("checkout.noShipping"));
      }
      const rateId = selectedRateId || "digital";
      const isPickup =
        rates.find((r) => r.id === selectedRateId)?.method_type === "pickup";

      const order = await checkout(
        {
          idempotency_key: idempotencyKey,
          payment_method: paymentMethod,
          shipping_rate_id: selectedRateId || rateId,
          location_id: isPickup ? pickupId || undefined : undefined,
          items: apiItems,
          customer: {
            name: name || "Customer",
            mobile: mobile || undefined,
            email: email || undefined,
          },
          shipping_address: digitalOnly
            ? { country: "EG" }
            : {
                country: "EG",
                state: destination.state,
                city: destination.city,
                address_line_1: address || city || "Address",
              },
          ...(coupon && token
            ? settings?.promo_codes?.allow_stacking
              ? { coupon_codes: [coupon] }
              : { coupon_code: coupon }
            : {}),
          ...(rewardPoints && token
            ? { reward_points: Number(rewardPoints) }
            : {}),
          locale,
        },
        token,
      );

      if (paymentMethod === "fawry" && order.data.payment) {
        router.replace({
          pathname: "/checkout/payment",
          params: {
            orderId: String(order.data.id),
            storefrontOrderId: order.data.storefront_order_id,
          },
        });
        await clear();
        return;
      }

      await clear();
      setMessage(`${t("checkout.success")}: ${order.data.storefront_order_id}`);
      if (token) {
        router.replace(`/account/orders/${order.data.id}`);
      }
    } catch (e) {
      setMessage(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setBusy(false);
    }
  };

  if (!items.length) {
    return (
      <Screen>
        <Text>{t("cart.empty")}</Text>
      </Screen>
    );
  }

  const total = Math.max(
    0,
    quoteSubtotal + shippingAmount - couponDiscount,
  );

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={styles.pad}>
        <Text style={styles.title}>{t("checkout.title")}</Text>

        <TextInput style={styles.input} placeholder="Name" value={name} onChangeText={setName} />
        <TextInput style={styles.input} placeholder="Mobile" value={mobile} onChangeText={setMobile} keyboardType="phone-pad" />
        <TextInput style={styles.input} placeholder="Email" value={email} onChangeText={setEmail} autoCapitalize="none" />

        {!digitalOnly ? (
          <>
            <TextInput style={styles.input} placeholder="Address" value={address} onChangeText={setAddress} />
            <TextInput style={styles.input} placeholder="City" value={city} onChangeText={setCity} />
            <TextInput style={styles.input} placeholder="Governorate / state" value={stateName} onChangeText={setStateName} />
          </>
        ) : (
          <Text style={styles.hint}>{t("checkout.digitalOnly")}</Text>
        )}

        {rates.length > 0 ? (
          <View style={styles.block}>
            <Text style={styles.section}>{t("checkout.shipping")}</Text>
            {rates.map((rate) => {
              const active = rate.id === selectedRateId;
              const amount = Number(rate.amount ?? rate.price ?? 0);
              return (
                <Pressable
                  key={rate.id}
                  style={[styles.rate, active && { borderColor: accent }]}
                  onPress={() => {
                    setSelectedRateId(rate.id);
                    setShippingAmount(amount);
                  }}
                >
                  <Text style={styles.rateName}>
                    {rate.label || rate.name || rate.id}
                  </Text>
                  <Text>{amount.toFixed(2)} EGP</Text>
                </Pressable>
              );
            })}
          </View>
        ) : null}

        {rates.some((r) => r.id === selectedRateId && r.method_type === "pickup") &&
        pickupLocations.length > 0 ? (
          <View style={styles.block}>
            <Text style={styles.section}>{t("checkout.pickup")}</Text>
            {pickupLocations.map((loc) => (
              <Pressable
                key={loc.id}
                style={[styles.rate, pickupId === loc.id && { borderColor: accent }]}
                onPress={() => setPickupId(loc.id)}
              >
                <Text style={styles.rateName}>{loc.name}</Text>
                {loc.address ? <Text style={styles.hint}>{loc.address}</Text> : null}
              </Pressable>
            ))}
          </View>
        ) : null}

        {token && settings?.promo_codes?.enabled_at_checkout ? (
          <View style={styles.couponRow}>
            <TextInput
              style={[styles.input, { flex: 1, marginBottom: 0 }]}
              placeholder={t("cart.promoCode")}
              value={coupon}
              onChangeText={setCoupon}
              autoCapitalize="characters"
            />
            <PrimaryButton label={t("cart.apply")} onPress={() => void applyCoupon()} />
          </View>
        ) : null}

        {token ? (
          <>
            {pointsBalance != null ? (
              <Text style={styles.hint}>
                {t("checkout.pointsBalance")}: {pointsBalance}
              </Text>
            ) : null}
            <TextInput
              style={styles.input}
              placeholder={t("checkout.rewardPoints")}
              value={rewardPoints}
              onChangeText={setRewardPoints}
              keyboardType="number-pad"
            />
          </>
        ) : null}

        <View style={styles.methods}>
          {codEnabled ? (
            <PrimaryButton
              label={paymentMethod === "cod" ? `✓ ${t("checkout.cod")}` : t("checkout.cod")}
              onPress={() => setPaymentMethod("cod")}
            />
          ) : null}
          {fawryEnabled ? (
            <>
              <View style={{ height: 8 }} />
              <PrimaryButton
                label={
                  paymentMethod === "fawry"
                    ? `✓ ${t("checkout.fawry")}`
                    : t("checkout.fawry")
                }
                onPress={() => setPaymentMethod("fawry")}
              />
            </>
          ) : null}
        </View>

        <View style={styles.totals}>
          <Text>
            {t("cart.subtotal")}: {quoteSubtotal.toFixed(2)} EGP
          </Text>
          <Text>
            {t("checkout.shipping")}: {shippingAmount.toFixed(2)} EGP
          </Text>
          {couponDiscount > 0 ? (
            <Text>
              {t("cart.discount")}: −{couponDiscount.toFixed(2)} EGP
            </Text>
          ) : null}
          <Text style={styles.total}>
            {t("cart.total")}: {total.toFixed(2)} EGP
          </Text>
        </View>

        {message ? <Text style={styles.message}>{message}</Text> : null}
        <PrimaryButton
          label={busy ? t("common.loading") : t("checkout.placeOrder")}
          disabled={busy}
          onPress={() => void placeOrder()}
        />
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pad: { padding: 16, paddingBottom: 40 },
  title: { fontSize: 22, fontWeight: "800", marginBottom: 12 },
  input: {
    backgroundColor: "#fff",
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "#e5e5e5",
    paddingHorizontal: 12,
    paddingVertical: 12,
    marginBottom: 10,
  },
  methods: { marginVertical: 12 },
  message: { marginVertical: 10, color: "#333" },
  hint: { color: "#666", marginBottom: 8 },
  section: { fontWeight: "800", marginBottom: 8 },
  block: { marginBottom: 12 },
  rate: {
    backgroundColor: "#fff",
    borderWidth: 1,
    borderColor: "#e5e5e5",
    borderRadius: 10,
    padding: 12,
    marginBottom: 8,
  },
  rateName: { fontWeight: "700", marginBottom: 2 },
  couponRow: { flexDirection: "row", gap: 8, alignItems: "center", marginBottom: 10 },
  totals: { gap: 4, marginVertical: 12 },
  total: { fontSize: 18, fontWeight: "800", marginTop: 4 },
});
