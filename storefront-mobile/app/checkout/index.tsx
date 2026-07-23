import { useMemo, useState } from "react";
import {
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useRouter } from "expo-router";
import { checkout, validateCart } from "../../src/lib/api";
import { toCartApiItem } from "../../src/lib/cart";
import { useApp } from "../../src/contexts/AppContext";
import { useCart } from "../../src/contexts/CartContext";
import { PrimaryButton, Screen } from "../../src/components/ui";

export default function CheckoutScreen() {
  const { t, token, settings, locale, contact } = useApp();
  const { items, clear, subtotal } = useCart();
  const router = useRouter();
  const [name, setName] = useState(contact?.name || "");
  const [mobile, setMobile] = useState(contact?.mobile || "");
  const [email, setEmail] = useState(contact?.email || "");
  const [address, setAddress] = useState("");
  const [city, setCity] = useState("");
  const [stateName, setStateName] = useState("");
  const [coupon, setCoupon] = useState("");
  const [rewardPoints, setRewardPoints] = useState("");
  const [paymentMethod, setPaymentMethod] = useState<"cod" | "fawry">("cod");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  const fawryEnabled =
    !!settings?.online_payments?.enabled &&
    settings?.online_payments?.provider === "fawry";
  const codEnabled = settings?.cod_enabled !== false;

  const idempotencyKey = useMemo(
    () => `mobile-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`,
    [],
  );

  const placeOrder = async () => {
    setBusy(true);
    setMessage(null);
    try {
      const apiItems = items.map(toCartApiItem);
      const destination = {
        country: "EG",
        state: stateName || "Cairo",
        city: city || "Cairo",
      };
      const validation = await validateCart(
        apiItems,
        {
          destination,
          ...(coupon && token
            ? settings?.promo_codes?.allow_stacking
              ? { coupon_codes: [coupon] }
              : { coupon_code: coupon }
            : {}),
        },
        token,
      );
      const data = validation.data as {
        shipping_rate_id?: string;
        available_rates?: Array<{ id: string; method_type?: string }>;
        digital_only?: boolean;
        location_id?: number;
      };
      const rateId =
        data.shipping_rate_id ||
        data.available_rates?.[0]?.id;
      if (!rateId) {
        throw new Error("No shipping rate available. Check address.");
      }

      const order = await checkout(
        {
          idempotency_key: idempotencyKey,
          payment_method: paymentMethod,
          shipping_rate_id: rateId,
          location_id: data.location_id,
          items: apiItems,
          customer: {
            name: name || "Customer",
            mobile: mobile || undefined,
            email: email || undefined,
          },
          shipping_address: data.digital_only
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
      router.replace(`/account/orders/${order.data.id}`);
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

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={styles.pad}>
        <Text style={styles.title}>{t("checkout.title")}</Text>
        <Text style={styles.sub}>
          {t("cart.subtotal")}: {subtotal.toFixed(2)} EGP
        </Text>
        <TextInput style={styles.input} placeholder="Name" value={name} onChangeText={setName} />
        <TextInput style={styles.input} placeholder="Mobile" value={mobile} onChangeText={setMobile} keyboardType="phone-pad" />
        <TextInput style={styles.input} placeholder="Email" value={email} onChangeText={setEmail} autoCapitalize="none" />
        <TextInput style={styles.input} placeholder="Address" value={address} onChangeText={setAddress} />
        <TextInput style={styles.input} placeholder="City" value={city} onChangeText={setCity} />
        <TextInput style={styles.input} placeholder="Governorate / state" value={stateName} onChangeText={setStateName} />
        {token && settings?.promo_codes?.enabled_at_checkout ? (
          <TextInput style={styles.input} placeholder="Promo code" value={coupon} onChangeText={setCoupon} autoCapitalize="characters" />
        ) : null}
        {token ? (
          <TextInput style={styles.input} placeholder="Reward points" value={rewardPoints} onChangeText={setRewardPoints} keyboardType="number-pad" />
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
  pad: { padding: 16, paddingBottom: 40, gap: 10 },
  title: { fontSize: 22, fontWeight: "800" },
  sub: { marginBottom: 8, fontWeight: "600" },
  input: {
    backgroundColor: "#fff",
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "#e5e5e5",
    paddingHorizontal: 12,
    paddingVertical: 12,
  },
  methods: { marginVertical: 8 },
  message: { color: "#333", marginVertical: 8 },
});
