import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useLocalSearchParams, useRouter } from "expo-router";
import {
  checkout,
  fetchBostaDistricts,
  fetchGeoCountries,
  fetchGeoStates,
  fetchLocations,
  fetchRewardPoints,
  validateCart,
  validateCoupons,
} from "../../src/lib/api";
import { toCartApiItem } from "../../src/lib/cart";
import { useApp } from "../../src/contexts/AppContext";
import { useCart } from "../../src/contexts/CartContext";
import { LabeledInput } from "../../src/components/LabeledInput";
import { SelectField } from "../../src/components/SelectField";
import { CouponPicker } from "../../src/components/checkout/CouponPicker";
import { RewardPointsRedeem } from "../../src/components/checkout/RewardPointsRedeem";
import { PrimaryButton, Screen } from "../../src/components/ui";
import type {
  BostaDistrict,
  GeoCountry,
  GeoState,
  RewardPointsBalance,
  ShippingRate,
  StoreLocation,
} from "../../src/lib/types";

function normalizeCountry(code: string | undefined | null): string {
  const raw = (code || "").trim().toUpperCase();
  if (!raw || raw === "EGYPT" || raw === "EGY") return "EG";
  return raw.slice(0, 2);
}

function rateTitle(rate: ShippingRate): string {
  return rate.title || rate.label || rate.name || rate.id;
}

function isPickupRate(rate: ShippingRate | undefined): boolean {
  const t = (rate?.method_type || "").toLowerCase();
  return t === "local_pickup" || t === "pickup";
}

function isDigitalRate(rate: ShippingRate | undefined): boolean {
  return (rate?.method_type || "").toLowerCase() === "digital";
}

export default function CheckoutScreen() {
  const { t, token, settings, locale, contact, accent } = useApp();
  const { items, clear, subtotal } = useCart();
  const router = useRouter();
  const params = useLocalSearchParams<{ coupon?: string }>();

  const nameParts = (contact?.name || "").trim().split(/\s+/);
  const [firstName, setFirstName] = useState(
    contact?.first_name || nameParts[0] || "",
  );
  const [lastName, setLastName] = useState(
    contact?.last_name || nameParts.slice(1).join(" ") || "",
  );
  const [mobile, setMobile] = useState(contact?.mobile || "");
  const [email, setEmail] = useState(contact?.email || "");
  const [country, setCountry] = useState(
    normalizeCountry(contact?.country) || "EG",
  );
  const [stateCode, setStateCode] = useState(contact?.state || "");
  const [stateText, setStateText] = useState(contact?.state || "");
  const [city, setCity] = useState(contact?.city || "Cairo");
  const [address, setAddress] = useState("");
  const [districtId, setDistrictId] = useState("");
  const [districtLabel, setDistrictLabel] = useState("");
  const [orderNote, setOrderNote] = useState("");
  const [coupon, setCoupon] = useState(params.coupon || "");
  const [rewardPoints, setRewardPoints] = useState(0);
  const [rewardAmount, setRewardAmount] = useState(0);
  const [rewardValid, setRewardValid] = useState(true);
  const [pointsBalance, setPointsBalance] =
    useState<RewardPointsBalance | null>(null);
  const [paymentMethod, setPaymentMethod] = useState<"cod" | "fawry">("cod");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  const [countries, setCountries] = useState<GeoCountry[]>([]);
  const [states, setStates] = useState<GeoState[]>([]);
  const [districts, setDistricts] = useState<BostaDistrict[]>([]);
  const [rates, setRates] = useState<ShippingRate[]>([]);
  const [selectedRateId, setSelectedRateId] = useState<string | null>(null);
  const [digitalOnly, setDigitalOnly] = useState(false);
  const [pickupLocations, setPickupLocations] = useState<StoreLocation[]>([]);
  const [pickupId, setPickupId] = useState<number | null>(null);
  const [shippingAmount, setShippingAmount] = useState(0);
  const [couponDiscount, setCouponDiscount] = useState(0);
  const [quoteSubtotal, setQuoteSubtotal] = useState(subtotal);

  const bostaEnabled = !!settings?.couriers?.bosta?.enabled;
  const fawryEnabled =
    !!settings?.online_payments?.enabled &&
    settings?.online_payments?.provider === "fawry";
  const codEnabled = settings?.cod_enabled !== false;
  const selectedRate = rates.find((r) => r.id === selectedRateId);
  const pickupMode = isPickupRate(selectedRate);
  const digitalMode = digitalOnly || isDigitalRate(selectedRate);
  const showAddress = !digitalMode && !pickupMode;
  const useStateSelect = states.length > 0;
  const needDistrict =
    showAddress && bostaEnabled && districts.length > 0 && !districtId;

  const idempotencyKey = useMemo(
    () => `mobile-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`,
    [],
  );

  const effectiveState = useStateSelect ? stateCode : stateText;

  const refreshQuote = useCallback(async () => {
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
          destination: {
            country: normalizeCountry(country),
            state: effectiveState || undefined,
            city: city || undefined,
          },
          location_id: pickupId || undefined,
          shipping_rate_id: selectedRateId || undefined,
          ...couponExtras,
        },
        token,
      );
      setDigitalOnly(!!data.digital_only);
      const available = data.available_rates || [];
      setRates(available);
      const rateId =
        selectedRateId && available.some((r) => r.id === selectedRateId)
          ? selectedRateId
          : data.shipping_rate_id || available[0]?.id || null;
      setSelectedRateId(rateId);
      const rate = available.find((r) => r.id === rateId);
      setShippingAmount(Number(rate?.amount ?? rate?.price ?? data.shipping ?? 0));
      setQuoteSubtotal(Number(data.subtotal ?? subtotal));
      setCouponDiscount(Number(data.coupon_discount ?? data.discount ?? 0));
      if (data.location_id && !pickupId) {
        setPickupId(data.location_id);
      }
    } catch (e) {
      setMessage(e instanceof Error ? e.message : t("common.error"));
    }
  }, [
    items,
    coupon,
    token,
    settings,
    country,
    effectiveState,
    city,
    pickupId,
    selectedRateId,
    subtotal,
    t,
  ]);

  useEffect(() => {
    void fetchGeoCountries()
      .then(({ data }) => setCountries(data || []))
      .catch(() => setCountries([{ code: "EG", name: "Egypt" }]));
  }, []);

  useEffect(() => {
    const code = normalizeCountry(country);
    if (!code) return;
    void fetchGeoStates(code)
      .then(({ data }) => {
        const list = data || [];
        setStates(list);
        if (list.length && stateCode && !list.some((s) => s.code === stateCode)) {
          setStateCode("");
          setSelectedRateId(null);
        }
      })
      .catch(() => setStates([]));
  }, [country]);

  useEffect(() => {
    setDistrictId("");
    setDistrictLabel("");
    setDistricts([]);
    if (!bostaEnabled || !effectiveState || digitalMode || pickupMode) return;
    void fetchBostaDistricts(effectiveState, locale)
      .then(({ data }) => {
        setDistricts(data.districts || []);
        if (data.city_name && !city) {
          setCity(data.city_name);
        }
      })
      .catch(() => setDistricts([]));
  }, [effectiveState, bostaEnabled, digitalMode, pickupMode, locale]);

  useEffect(() => {
    const timer = setTimeout(() => void refreshQuote(), 350);
    return () => clearTimeout(timer);
  }, [refreshQuote]);

  useEffect(() => {
    if (!token) return;
    void fetchRewardPoints(token)
      .then(({ data }) => setPointsBalance(data))
      .catch(() => setPointsBalance(null));
    void fetchLocations(true, locale)
      .then(({ data }) => setPickupLocations(data || []))
      .catch(() => setPickupLocations([]));
  }, [token, locale]);

  const applyCoupon = async () => {
    if (!token || !coupon.trim()) {
      setMessage(t("checkout.couponNeedLogin"));
      return;
    }
    try {
      await validateCoupons(
        { code: coupon.trim(), items: items.map(toCartApiItem) },
        token,
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
      if (!firstName.trim() || !email.trim() || !mobile.trim()) {
        throw new Error(t("checkout.requiredContact"));
      }
      if (showAddress) {
        if (!country || !effectiveState || !city.trim() || !address.trim()) {
          throw new Error(t("checkout.requiredAddress"));
        }
        if (needDistrict) {
          throw new Error(t("checkout.requiredDistrict"));
        }
      }
      await refreshQuote();
      if (!selectedRateId) {
        throw new Error(t("checkout.noShipping"));
      }
      if (pickupMode && !pickupId) {
        throw new Error(t("checkout.requiredPickup"));
      }
      if (rewardPoints > 0 && !rewardValid) {
        throw new Error(t("rewards.fixBeforeOrder"));
      }

      const shipping_address = digitalMode
        ? {
            country: "EG",
            city: "",
            state: "",
            address_line_1: "Digital delivery",
          }
        : pickupMode
          ? {
              country: normalizeCountry(country),
              city: city || "",
              state: effectiveState || "",
              address_line_1: "Store pickup",
            }
          : {
              country: normalizeCountry(country),
              city: city.trim(),
              state: effectiveState,
              address_line_1: address.trim(),
              ...(districtId
                ? { district_id: districtId, district_label: districtLabel }
                : {}),
            };

      const order = await checkout(
        {
          idempotency_key: idempotencyKey,
          payment_method: paymentMethod,
          shipping_rate_id: selectedRateId,
          location_id: pickupId || undefined,
          items: items.map(toCartApiItem),
          customer: {
            first_name: firstName.trim(),
            last_name: lastName.trim(),
            email: email.trim(),
            mobile: mobile.trim(),
          },
          shipping_address,
          ...(orderNote.trim() ? { order_note: orderNote.trim() } : {}),
          ...(coupon && token
            ? settings?.promo_codes?.allow_stacking
              ? { coupon_codes: [coupon] }
              : { coupon_code: coupon }
            : {}),
          ...(rewardPoints > 0 && token && rewardValid
            ? { reward_points: rewardPoints }
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
    quoteSubtotal + shippingAmount - couponDiscount - rewardAmount,
  );

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={styles.pad}>
        <Text style={styles.title}>{t("checkout.title")}</Text>

        <Text style={styles.section}>{t("checkout.contact")}</Text>
        <LabeledInput
          label={t("checkout.firstName")}
          value={firstName}
          onChangeText={setFirstName}
        />
        <LabeledInput
          label={t("checkout.lastName")}
          value={lastName}
          onChangeText={setLastName}
        />
        <LabeledInput
          label={t("checkout.email")}
          value={email}
          onChangeText={setEmail}
          autoCapitalize="none"
          keyboardType="email-address"
        />
        <LabeledInput
          label={t("checkout.mobile")}
          value={mobile}
          onChangeText={setMobile}
          keyboardType="phone-pad"
        />

        {digitalMode ? (
          <Text style={styles.hint}>{t("checkout.digitalOnly")}</Text>
        ) : pickupMode ? (
          <Text style={styles.hint}>{t("checkout.pickupHint")}</Text>
        ) : (
          <>
            <Text style={styles.section}>{t("checkout.shippingAddress")}</Text>
            <SelectField
              label={t("checkout.country")}
              value={country}
              options={countries.map((c) => ({ value: c.code, label: c.name }))}
              onChange={(code) => {
                setCountry(normalizeCountry(code));
                setStateCode("");
                setStateText("");
                setSelectedRateId(null);
              }}
            />
            {useStateSelect ? (
              <SelectField
                label={t("checkout.state")}
                value={stateCode}
                options={states.map((s) => ({ value: s.code, label: s.name }))}
                onChange={(code) => {
                  setStateCode(code);
                  setSelectedRateId(null);
                }}
                placeholder={t("checkout.selectState")}
              />
            ) : (
              <LabeledInput
                label={t("checkout.state")}
                value={stateText}
                onChangeText={(v) => {
                  setStateText(v);
                  setSelectedRateId(null);
                }}
                placeholder={t("checkout.statePlaceholder")}
              />
            )}
            {bostaEnabled && districts.length > 0 ? (
              <SelectField
                label={t("checkout.district")}
                value={districtId}
                options={districts.map((d) => ({
                  value: d.id,
                  label: d.label,
                }))}
                onChange={(id, opt) => {
                  setDistrictId(id);
                  setDistrictLabel(opt.label);
                }}
                placeholder={t("checkout.selectDistrict")}
              />
            ) : null}
            <LabeledInput
              label={t("checkout.city")}
              value={city}
              onChangeText={setCity}
            />
            <LabeledInput
              label={t("checkout.address")}
              value={address}
              onChangeText={setAddress}
              multiline
            />
          </>
        )}

        <Text style={styles.section}>{t("checkout.shippingMethod")}</Text>
        {rates.length === 0 ? (
          <Text style={styles.hint}>{t("checkout.enterStateForRates")}</Text>
        ) : (
          rates.map((rate) => {
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
                <Text style={styles.rateName}>{rateTitle(rate)}</Text>
                {rate.eta_label ? (
                  <Text style={styles.hint}>{rate.eta_label}</Text>
                ) : null}
                <Text>{amount.toFixed(2)} EGP</Text>
              </Pressable>
            );
          })
        )}

        {pickupMode && pickupLocations.length > 0 ? (
          <View style={styles.block}>
            <Text style={styles.section}>{t("checkout.pickup")}</Text>
            {pickupLocations.map((loc) => (
              <Pressable
                key={loc.id}
                style={[
                  styles.rate,
                  pickupId === loc.id && { borderColor: accent },
                ]}
                onPress={() => setPickupId(loc.id)}
              >
                <Text style={styles.rateName}>{loc.name}</Text>
                {loc.address ? (
                  <Text style={styles.hint}>{loc.address}</Text>
                ) : null}
              </Pressable>
            ))}
          </View>
        ) : null}

        <LabeledInput
          label={t("checkout.orderNote")}
          value={orderNote}
          onChangeText={setOrderNote}
          placeholder={t("checkout.orderNotePlaceholder")}
        />

        {settings?.promo_codes?.enabled_at_checkout ? (
          <View style={styles.couponBlock}>
            <LabeledInput
              label={t("cart.promoCode")}
              value={coupon}
              onChangeText={setCoupon}
              autoCapitalize="characters"
              placeholder={t("cart.promoCodePlaceholder")}
            />
            <PrimaryButton
              label={t("cart.apply")}
              onPress={() => void applyCoupon()}
            />
            <CouponPicker
              items={items}
              token={token}
              appliedCodes={coupon.trim() ? [coupon.trim()] : []}
              onSelect={async (code) => {
                setCoupon(code);
                if (!token) {
                  setMessage(t("checkout.couponNeedLogin"));
                  return;
                }
                try {
                  await validateCoupons(
                    { code, items: items.map(toCartApiItem) },
                    token,
                  );
                  await refreshQuote();
                  setMessage(t("cart.couponApplied"));
                } catch (e) {
                  setMessage(
                    e instanceof Error ? e.message : t("common.error"),
                  );
                }
              }}
            />
            {!token ? (
              <Text style={styles.hint}>{t("checkout.couponNeedLogin")}</Text>
            ) : null}
          </View>
        ) : null}

        {token &&
        settings?.reward_points?.enabled !== false &&
        pointsBalance ? (
          <RewardPointsRedeem
            token={token}
            balance={pointsBalance}
            orderTotal={Math.max(
              0,
              quoteSubtotal + shippingAmount - couponDiscount,
            )}
            pointsToRedeem={rewardPoints}
            onChange={(points, amount, isValid) => {
              setRewardPoints(points);
              setRewardAmount(amount);
              setRewardValid(isValid || points === 0);
            }}
          />
        ) : null}

        <Text style={styles.section}>{t("checkout.payment")}</Text>
        <View style={styles.methods}>
          {codEnabled ? (
            <PrimaryButton
              label={
                paymentMethod === "cod"
                  ? `✓ ${t("checkout.cod")}`
                  : t("checkout.cod")
              }
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
          {rewardAmount > 0 ? (
            <Text>
              {t("rewards.discount")} −{rewardAmount.toFixed(2)} EGP
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
  section: { fontWeight: "800", fontSize: 16, marginTop: 8, marginBottom: 10 },
  methods: { marginVertical: 8 },
  message: { marginVertical: 10, color: "#333" },
  hint: { color: "#666", marginBottom: 8, lineHeight: 18 },
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
  couponBlock: { marginBottom: 8 },
  totals: { gap: 4, marginVertical: 12 },
  total: { fontSize: 18, fontWeight: "800", marginTop: 4 },
});
