import { $, component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { Link, routeLoader$, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { RewardPointsRedeem } from "~/components/checkout/reward-points-redeem";
import { CouponField } from "~/components/checkout/coupon-field";
import { PhoneInputWithDialCode } from "~/components/forms/phone-input-with-dial-code";
import { SearchableSelect } from "~/components/forms/searchable-select";
import { ApiError, checkout, fetchBostaDistricts, fetchGeoCountries, fetchGeoStates, fetchLocations, fetchPhoneCountries, fetchRewardPoints, validateCart, type BostaDistrict } from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { clearCart, clearAppliedCoupon, couponRequestPayload, loadAppliedCoupons, persistAppliedCoupons, toCartApiItem } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { storeFawryPaymentSession } from "~/lib/fawry-pay";
import { formatPrice } from "~/lib/format";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { usePendingState } from "~/lib/pending-context";
import type { AppliedCouponInfo, CheckoutOrder, RewardPointsBalance, ShippingRate } from "~/lib/types";
import { parseFullPhone, validatePhone, type GeoState } from "~/lib/phone-validation";
import { withPendingFeedback } from "~/lib/with-pending";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

function normalizeCheckoutCountry(code: string | null | undefined): string {
  const c = (code || "").trim().toUpperCase();
  if (!c || c === "EGYPT" || c === "EGY") return "EG";
  return c;
}

export const useCheckoutLocations = routeLoader$(async () => {
  try {
    const { data } = await fetchLocations();
    return data;
  } catch {
    return [];
  }
});

export const useCheckoutPhoneCountries = routeLoader$(async () => {
  try {
    const { data } = await fetchPhoneCountries();
    return data;
  } catch {
    return [];
  }
});

export const useCheckoutGeoCountries = routeLoader$(async () => {
  try {
    const { data } = await fetchGeoCountries();
    return data;
  } catch {
    return [];
  }
});

export default component$(() => {
  const settings = useSiteSettings();
  const { locale } = useI18n();
  const nav = useNavigate();
  const locations = useCheckoutLocations();
  const phoneCountries = useCheckoutPhoneCountries();
  const geoCountries = useCheckoutGeoCountries();
  const cart = useCart();
  const auth = useAuth();
  const pending = usePendingState();

  const checkoutPhone = useStore({
    dialCode: "+20",
    nationalNumber: "",
    mobile: "",
  });
  const phoneReady = useSignal(false);

  const sellingLocations = locations.value;
  const showLocationPicker = sellingLocations.length > 1;
  const locationId = useSignal(sellingLocations[0]?.id || 0);
  const submitting = useSignal(false);
  const validatingStock = useSignal(false);
  const error = useSignal<string | null>(null);
  const stockWarning = useSignal<string | null>(null);
  const order = useSignal<CheckoutOrder | null>(null);
  const rewardBalance = useSignal<RewardPointsBalance | null>(null);
  const pointsToRedeem = useSignal(0);
  const redeemAmount = useSignal(0);
  const redeemValid = useSignal(true);
  const validatedSubtotal = useSignal(0);
  const validatedShipping = useSignal(0);
  const validatedTotal = useSignal(0);
  const availableRates = useSignal<ShippingRate[]>([]);
  const shippingRateId = useSignal("");
  const digitalOnly = useSignal(false);
  const shipCountry = useSignal("EG");
  const shipState = useSignal("");
  const shipCity = useSignal("");
  const shipDistrictId = useSignal("");
  const shipDistrictLabel = useSignal("");
  const geoStates = useSignal<GeoState[]>([]);
  const geoStatesLoading = useSignal(false);
  const bostaDistricts = useSignal<BostaDistrict[]>([]);
  const bostaDistrictsLoading = useSignal(false);
  const appliedCoupons = useSignal<AppliedCouponInfo[]>([]);
  const couponDiscount = useSignal(0);
  const couponCodes = useSignal<string[]>(loadAppliedCoupons().map((coupon) => coupon.code));
  const stackWithRewardPoints = useSignal(true);
  const paymentMethod = useSignal<"cod" | "fawry">(
    settings.value.cod_enabled ? "cod" : settings.value.online_payments.enabled ? "fawry" : "cod",
  );

  const promoAtCheckout = settings.value.promo_codes?.enabled_at_checkout ?? true;
  const allowCouponStacking = settings.value.promo_codes?.allow_stacking ?? false;
  const couponCodesKey = couponCodes.value.join("|");
  const bostaEnabled = settings.value.couriers?.bosta?.enabled ?? false;

  const onlinePaymentsEnabled =
    settings.value.online_payments.enabled && settings.value.online_payments.provider === "fawry";
  const canCheckout = settings.value.cod_enabled || onlinePaymentsEnabled;

  const cartItemsKey = cart.items
    .map((line) => `${line.variationId}:${line.quantity}`)
    .join("|");

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => auth.contact?.mobile);
    const parsed = parseFullPhone(auth.contact?.mobile || "", phoneCountries.value);
    checkoutPhone.dialCode = parsed.dialCode;
    checkoutPhone.nationalNumber = parsed.nationalNumber;
    checkoutPhone.mobile = auth.contact?.mobile || parsed.dialCode + parsed.nationalNumber;
    phoneReady.value = true;
  });

  // Seed country/state from profile once auth is ready.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => auth.contact?.country);
    track(() => auth.contact?.state);
    track(() => auth.contact?.city);
    if (auth.contact?.country) {
      shipCountry.value = normalizeCheckoutCountry(auth.contact.country);
    }
    if (auth.contact?.state && !shipState.value) {
      shipState.value = auth.contact.state;
    }
    if (auth.contact?.city && !shipCity.value) {
      shipCity.value = auth.contact.city;
    }
  });

  // Load governorates/states when country changes.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => shipCountry.value);
    const country = normalizeCheckoutCountry(shipCountry.value);
    shipCountry.value = country;
    if (!country) {
      geoStates.value = [];
      shipState.value = "";
      return;
    }
    geoStatesLoading.value = true;
    try {
      const { data } = await fetchGeoStates(country);
      geoStates.value = data;
      if (data.length > 0 && !data.some((s) => s.code === shipState.value)) {
        shipState.value = "";
        shippingRateId.value = "";
      }
    } catch {
      geoStates.value = [];
      shipState.value = "";
      shippingRateId.value = "";
    } finally {
      geoStatesLoading.value = false;
    }
  });

  // Load Bosta districts when governorate changes (courier enabled).
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => shipState.value);
    track(() => bostaEnabled);
    shipDistrictId.value = "";
    shipDistrictLabel.value = "";
    if (!bostaEnabled || !shipState.value) {
      bostaDistricts.value = [];
      return;
    }
    bostaDistrictsLoading.value = true;
    try {
      const { data } = await fetchBostaDistricts(shipState.value, locale);
      bostaDistricts.value = data.districts ?? [];
      if (data.city_name && !shipCity.value) {
        shipCity.value = data.city_name;
      }
    } catch {
      bostaDistricts.value = [];
    } finally {
      bostaDistrictsLoading.value = false;
    }
  });

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track, cleanup }) => {
    track(() => locationId.value);
    track(() => cartItemsKey);
    track(() => couponCodesKey);
    track(() => auth.token);
    track(() => promoAtCheckout);
    track(() => shipCountry.value);
    track(() => shipState.value);
    track(() => shippingRateId.value);

    if (!auth.token || !promoAtCheckout) {
      couponCodes.value = [];
      appliedCoupons.value = [];
      couponDiscount.value = 0;
      clearAppliedCoupon();
    }

    if (cart.items.length === 0 || !locationId.value) {
      stockWarning.value = null;
      validatedSubtotal.value = 0;
      validatedShipping.value = 0;
      validatedTotal.value = 0;
      availableRates.value = [];
      digitalOnly.value = false;
      appliedCoupons.value = [];
      couponDiscount.value = 0;
      return;
    }

    const cartIsDigitalOnly =
      cart.items.length > 0 && cart.items.every((line) => Boolean(line.digital?.kind));
    digitalOnly.value = cartIsDigitalOnly;

    const timer = setTimeout(async () => {
      validatingStock.value = true;
      try {
        const couponPayload =
          auth.token && promoAtCheckout
            ? couponRequestPayload(couponCodes.value, allowCouponStacking)
            : {};
        const destination =
          cartIsDigitalOnly
            ? undefined
            : shipCountry.value || shipState.value
              ? {
                  country: shipCountry.value || "EG",
                  state: shipState.value || undefined,
                  city: shipCity.value || undefined,
                }
              : undefined;
        const { data } = await validateCart(
          {
            location_id: locationId.value,
            ...couponPayload,
            items: cart.items.map(toCartApiItem),
            destination,
            shipping_rate_id: shippingRateId.value || undefined,
          },
          auth.token ?? undefined,
        );
        validatedSubtotal.value = data.subtotal;
        validatedShipping.value = data.shipping;
        validatedTotal.value = data.total;
        availableRates.value = data.available_rates ?? [];
        digitalOnly.value = Boolean(data.digital_only ?? cartIsDigitalOnly);
        if (data.shipping_rate?.id && !shippingRateId.value) {
          shippingRateId.value = data.shipping_rate.id;
        } else if (
          shippingRateId.value &&
          !(data.available_rates ?? []).some((r) => r.id === shippingRateId.value) &&
          data.shipping_rate?.id
        ) {
          shippingRateId.value = data.shipping_rate.id;
        } else if (digitalOnly.value && data.shipping_rate?.id) {
          shippingRateId.value = data.shipping_rate.id;
        }
        appliedCoupons.value = data.coupons?.length
          ? data.coupons
          : data.coupon
            ? [data.coupon]
            : [];
        couponDiscount.value = data.coupon_discount ?? 0;
        stackWithRewardPoints.value = data.stack_with_reward_points ?? true;
        couponCodes.value = appliedCoupons.value.map((coupon) => coupon.code);
        if (appliedCoupons.value.length === 0) {
          clearAppliedCoupon();
        } else {
          persistAppliedCoupons(
            appliedCoupons.value.map((coupon) => ({ code: coupon.code, label: coupon.label })),
          );
        }
        stockWarning.value = null;
      } catch (err) {
        if (err instanceof ApiError) {
          const messages = Object.values(err.errors).flat();
          stockWarning.value = messages.length ? messages.join(" ") : err.message;
        } else {
          stockWarning.value =
            err instanceof Error ? err.message : tStatic(locale, "checkout.stockUnavailable");
        }
      } finally {
        validatingStock.value = false;
      }
    }, 350);

    cleanup(() => clearTimeout(timer));
  });

  const subtotal = validatedTotal.value > 0
    ? validatedSubtotal.value
    : cart.items.reduce((sum, line) => sum + line.price * line.quantity, 0);

  const checkoutTotal = validatedTotal.value > 0 ? validatedTotal.value : subtotal;
  const orderTotal = Math.max(0, checkoutTotal - redeemAmount.value);
  const selectedShippingRate =
    availableRates.value.find((r) => r.id === shippingRateId.value) ?? null;
  const isDigitalCheckout = digitalOnly.value || selectedShippingRate?.method_type === "digital";
  const isPickup = selectedShippingRate?.method_type === "local_pickup";
  const pickupLocationIds = Array.isArray(selectedShippingRate?.meta?.location_ids)
    ? (selectedShippingRate!.meta!.location_ids as number[])
    : [];
  const pickupLocations =
    pickupLocationIds.length > 0
      ? sellingLocations.filter((loc) => pickupLocationIds.includes(loc.id))
      : sellingLocations.filter(
          (loc) => loc.enable_pickup && loc.is_selling_location !== false,
        );

  const countryOptions = geoCountries.value.map((c) => ({
    value: c.code,
    label: c.name,
    searchText: `${c.name} ${c.code}`,
  }));
  const selectedCountry = geoCountries.value.find((c) => c.code === shipCountry.value);
  const stateOptions = geoStates.value.map((s) => ({
    value: s.code,
    label: s.name,
    searchText: `${s.name} ${s.code}`,
  }));
  const districtOptions = bostaDistricts.value.map((d) => ({
    value: d.id,
    label: d.label,
    searchText: `${d.label} ${d.zone ?? ""} ${d.id}`,
  }));
  const locationOptions = (
    isPickup && pickupLocations.length > 0 ? pickupLocations : sellingLocations
  ).map((loc) => ({
    value: String(loc.id),
    label: loc.name,
    searchText: loc.name,
  }));
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => auth.token);
    track(() => settings.value.reward_points.enabled);

    if (!auth.token || !settings.value.reward_points.enabled) {
      rewardBalance.value = null;
      pointsToRedeem.value = 0;
      redeemAmount.value = 0;
      redeemValid.value = true;
      return;
    }

    try {
      const { data } = await fetchRewardPoints(auth.token);
      rewardBalance.value = data;
    } catch {
      rewardBalance.value = null;
    }
  });

  const onRewardPointsChange$ = $((points: number, amount: number, isValid: boolean) => {
    pointsToRedeem.value = points;
    redeemAmount.value = amount;
    redeemValid.value = isValid || points === 0;
  });

  const onCouponApplied$ = $(
    (
      coupons: AppliedCouponInfo[],
      discount: number,
      totals?: { shipping: number; total: number },
    ) => {
      appliedCoupons.value = coupons;
      couponDiscount.value = discount;
      couponCodes.value = coupons.map((coupon) => coupon.code);
      if (totals) {
        validatedShipping.value = totals.shipping;
        validatedTotal.value = totals.total;
      } else if (coupons.length === 0) {
        stackWithRewardPoints.value = true;
        if (validatedSubtotal.value > 0) {
          validatedTotal.value = validatedSubtotal.value + validatedShipping.value;
        }
      }
    },
  );

  const submitOrder$ = $(async (form: HTMLFormElement) => {
    if (cart.items.length === 0 || !locationId.value) {
      error.value = tStatic(locale, "checkout.emptyOrNoLocation");
      return;
    }

    const phoneCheck = validatePhone(
      checkoutPhone.dialCode,
      checkoutPhone.nationalNumber,
      phoneCountries.value,
    );
    if (!phoneCheck.valid) {
      error.value = phoneCheck.message;
      return;
    }

    if (couponCodes.value.length > 0 && !auth.token) {
      error.value = tStatic(locale, "coupon.signInRequired");
      return;
    }

    if (couponCodes.value.length > 0 && !promoAtCheckout) {
      error.value = tStatic(locale, "coupon.unavailable");
      return;
    }

    if (pointsToRedeem.value > 0 && !redeemValid.value) {
      error.value = tStatic(locale, "checkout.fixRewardPoints");
      return;
    }

    if (pointsToRedeem.value > 0 && !stackWithRewardPoints.value) {
      error.value = tStatic(locale, "coupon.noStackRewardPoints");
      return;
    }

    await withPendingFeedback(pending, submitting, async () => {
      error.value = null;

      const formData = new FormData(form);
      const selectedPayment = String(formData.get("payment_method") || paymentMethod.value);
      const resolvedPayment = selectedPayment === "fawry" && onlinePaymentsEnabled ? "fawry" : "cod";
      const items = cart.items.map(toCartApiItem);
      const selectedRate =
        availableRates.value.find((r) => r.id === shippingRateId.value) ?? null;
      const pickupSelected = selectedRate?.method_type === "local_pickup";
      const digitalSelected =
        digitalOnly.value || selectedRate?.method_type === "digital";
      const payload: Record<string, unknown> = {
        idempotency_key: `web-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`,
        location_id: locationId.value,
        payment_method: resolvedPayment,
        items,
        customer: {
          first_name: String(formData.get("first_name") || ""),
          last_name: String(formData.get("last_name") || ""),
          email: String(formData.get("email") || ""),
          mobile: phoneCheck.fullPhone,
        },
        shipping_address: digitalSelected
          ? {
              country: "EG",
              city: "",
              state: "",
              address_line_1: "Digital delivery",
            }
          : pickupSelected
            ? {
                country: normalizeCheckoutCountry(shipCountry.value),
                city: String(formData.get("city") || ""),
                state: String(formData.get("state") || ""),
                address_line_1: String(formData.get("address_line_1") || "Store pickup"),
              }
            : {
                address_line_1: String(formData.get("address_line_1") || ""),
                city: String(formData.get("city") || shipCity.value || ""),
                state: String(formData.get("state") || shipState.value || ""),
                country: normalizeCheckoutCountry(
                  String(formData.get("country") || shipCountry.value),
                ),
                ...(bostaEnabled && shipDistrictId.value
                  ? {
                      district_id: shipDistrictId.value,
                      district_label:
                        shipDistrictLabel.value ||
                        String(formData.get("district_label") || ""),
                    }
                  : {}),
              },
        shipping_rate_id: shippingRateId.value,
        order_note: String(formData.get("order_note") || ""),
      };

      if (!shippingRateId.value) {
        error.value = tStatic(locale, "checkout.selectShipping");
        return;
      }

      if (
        !digitalSelected &&
        !pickupSelected &&
        bostaEnabled &&
        bostaDistricts.value.length > 0 &&
        !shipDistrictId.value
      ) {
        error.value = tStatic(locale, "checkout.districtRequired");
        return;
      }

      if (pointsToRedeem.value > 0) {
        payload.reward_points = pointsToRedeem.value;
      }

      if (auth.token && promoAtCheckout && couponCodes.value.length > 0) {
        Object.assign(payload, couponRequestPayload(couponCodes.value, allowCouponStacking));
      }

      try {
        await validateCart(
          {
            location_id: locationId.value,
            ...(auth.token && promoAtCheckout
              ? couponRequestPayload(couponCodes.value, allowCouponStacking)
              : {}),
            items,
            destination: payload.shipping_address as {
              country?: string;
              state?: string;
              city?: string;
            },
            shipping_rate_id: shippingRateId.value,
          },
          auth.token ?? undefined,
        );
        const { data } = await checkout(payload, auth.token ?? undefined);
        clearCart(cart);

        if (resolvedPayment === "fawry" && data.payment) {
          storeFawryPaymentSession(data.payment);
          await nav(
            localePath(locale, `/checkout/payment/?order=${encodeURIComponent(data.storefront_order_id)}`),
          );
          return;
        }

        order.value = data;
      } catch (err) {
        if (err instanceof ApiError) {
          const messages = Object.values(err.errors).flat();
          error.value = messages.length ? messages.join(" ") : err.message;
        } else {
          error.value = err instanceof Error ? err.message : tStatic(locale, "checkout.failed");
        }
      }
    });
  });

  if (cart.items.length === 0 && !order.value) {
    return (
      <section>
        <h1 class="page-title">{tStatic(locale, "checkout.title")}</h1>
        <div class="empty-state">
          <p>{tStatic(locale, "cart.empty")}</p>
          <Link href={localePath(locale, "/products")} class="btn btn-primary">
            {tStatic(locale, "cart.continueShopping")}
          </Link>
        </div>
      </section>
    );
  }

  if (order.value) {
    return (
      <section>
        <h1 class="page-title">{tStatic(locale, "checkout.orderConfirmed")}</h1>
        <div class="alert alert-success">
          {tStatic(locale, "checkout.orderThanks", {
            invoiceNo: order.value.invoice_no,
            paymentStatus: order.value.payment_status,
          })}
        </div>
        <Link href={localePath(locale, "/products")} class="btn btn-primary">
          {tStatic(locale, "cart.continueShopping")}
        </Link>
      </section>
    );
  }

  return (
    <section>
      <h1 class="page-title">{tStatic(locale, "checkout.title")}</h1>

      {error.value ? <div class="alert alert-error">{error.value}</div> : null}
      {stockWarning.value && !error.value ? (
        <div class="alert alert-error">{stockWarning.value}</div>
      ) : null}
      {validatingStock.value ? (
        <p class="footer-muted">{tStatic(locale, "checkout.checkingStock")}</p>
      ) : null}

      <div class="checkout-layout">
        <form
          id="checkout-form"
          preventdefault:submit
          onSubmit$={(event) => submitOrder$(event.target as HTMLFormElement)}
          class="form-grid"
        >
          <h2 style={{ margin: 0, fontSize: "1.125rem" }}>{tStatic(locale, "checkout.contact")}</h2>
          <div class="two-col">
            <div>
              <label for="first_name">{tStatic(locale, "forms.firstName")}</label>
              <input id="first_name" name="first_name" required defaultValue={auth.contact?.first_name || ""} />
            </div>
            <div>
              <label for="last_name">{tStatic(locale, "forms.lastName")}</label>
              <input id="last_name" name="last_name" defaultValue={auth.contact?.last_name || ""} />
            </div>
          </div>
          <div class="two-col">
            <div>
              <label for="email">{tStatic(locale, "forms.email")}</label>
              <input id="email" name="email" type="email" required defaultValue={auth.contact?.email || ""} />
            </div>
            <div>
              <label for="checkout-mobile">{tStatic(locale, "forms.mobile")}</label>
              {phoneReady.value ? (
                <PhoneInputWithDialCode
                  id="checkout-mobile"
                  countries={phoneCountries.value}
                  dialCode={checkoutPhone.dialCode}
                  nationalNumber={checkoutPhone.nationalNumber}
                  required
                  onChange$={(value) => {
                    checkoutPhone.dialCode = value.dialCode;
                    checkoutPhone.nationalNumber = value.nationalNumber;
                    checkoutPhone.mobile = value.fullPhone;
                  }}
                />
              ) : null}
            </div>
          </div>

          <h2 style={{ margin: "0.5rem 0 0", fontSize: "1.125rem" }}>
            {isDigitalCheckout
              ? tStatic(locale, "checkout.delivery")
              : tStatic(locale, "checkout.shipping")}
          </h2>
          {isDigitalCheckout ? (
            <p class="footer-muted">{tStatic(locale, "checkout.digitalDeliveryHint")}</p>
          ) : !isPickup ? (
            <>
              <div>
                <label for="country">{tStatic(locale, "forms.country")}</label>
                <input type="hidden" name="country" value={shipCountry.value} />
                <SearchableSelect
                  id="country"
                  options={countryOptions}
                  value={shipCountry.value}
                  displayLabel={selectedCountry?.name}
                  placeholder={tStatic(locale, "forms.searchCountries")}
                  required
                  onChange$={(code) => {
                    shipCountry.value = normalizeCheckoutCountry(code);
                    shipState.value = "";
                    shipDistrictId.value = "";
                    shipDistrictLabel.value = "";
                    shippingRateId.value = "";
                  }}
                />
              </div>
              <div>
                <label for="state">{tStatic(locale, "forms.state")}</label>
                <input type="hidden" name="state" value={shipState.value} />
                {geoStatesLoading.value ? (
                  <p class="footer-muted">{tStatic(locale, "checkout.loadingStates")}</p>
                ) : geoStates.value.length > 0 ? (
                  <SearchableSelect
                    id="state"
                    options={stateOptions}
                    value={shipState.value}
                    placeholder={tStatic(locale, "forms.searchStates")}
                    required
                    onChange$={(code) => {
                      shipState.value = code;
                      shipDistrictId.value = "";
                      shipDistrictLabel.value = "";
                      shippingRateId.value = "";
                    }}
                  />
                ) : (
                  <input
                    id="state"
                    required
                    value={shipState.value}
                    placeholder={tStatic(locale, "forms.state")}
                    onInput$={(_, el) => {
                      shipState.value = el.value;
                      shipDistrictId.value = "";
                      shipDistrictLabel.value = "";
                      shippingRateId.value = "";
                    }}
                  />
                )}
              </div>
              {bostaEnabled ? (
                <div>
                  <label for="district_id">{tStatic(locale, "forms.district")}</label>
                  <input type="hidden" name="district_id" value={shipDistrictId.value} />
                  <input type="hidden" name="district_label" value={shipDistrictLabel.value} />
                  {bostaDistrictsLoading.value ? (
                    <p class="footer-muted">{tStatic(locale, "forms.loadingDistricts")}</p>
                  ) : bostaDistricts.value.length > 0 ? (
                    <SearchableSelect
                      id="district_id"
                      options={districtOptions}
                      value={shipDistrictId.value}
                      placeholder={tStatic(locale, "forms.searchDistricts")}
                      required
                      onChange$={(id) => {
                        shipDistrictId.value = id;
                        const match = bostaDistricts.value.find((d) => d.id === id);
                        shipDistrictLabel.value = match?.label ?? "";
                      }}
                    />
                  ) : shipState.value ? (
                    <p class="footer-muted">{tStatic(locale, "forms.noMatches")}</p>
                  ) : null}
                </div>
              ) : null}
              <div class="two-col">
                <div>
                  <label for="city">{tStatic(locale, "forms.city")}</label>
                  <input
                    id="city"
                    name="city"
                    required
                    defaultValue={auth.contact?.city || tStatic(locale, "checkout.defaultCity")}
                    onInput$={(_, el) => {
                      shipCity.value = el.value;
                    }}
                  />
                </div>
                <div>
                  <label for="address_line_1">{tStatic(locale, "forms.address")}</label>
                  <input
                    id="address_line_1"
                    name="address_line_1"
                    required
                    defaultValue={auth.contact?.address_line_1 || ""}
                  />
                </div>
              </div>
            </>
          ) : (
            <p class="footer-muted">{tStatic(locale, "checkout.pickupAddressHint")}</p>
          )}

          {availableRates.value.length > 0 ? (
            <fieldset
              class={`checkout-choice-group${availableRates.value.length === 1 ? " checkout-choice-group--solo" : ""}`}
            >
              <legend>
                {isDigitalCheckout
                  ? tStatic(locale, "checkout.deliveryMethod")
                  : tStatic(locale, "checkout.shippingMethod")}
              </legend>
              <div class="checkout-choice-group__list">
                {availableRates.value.map((rate) => {
                  const selected = shippingRateId.value === rate.id;
                  const solo = availableRates.value.length === 1;
                  return (
                    <label
                      key={rate.id}
                      class={`checkout-choice${selected ? " checkout-choice--selected" : ""}${solo ? " checkout-choice--solo" : ""}`}
                    >
                      <input
                        type="radio"
                        name="shipping_rate_id"
                        value={rate.id}
                        class="checkout-choice__input"
                        checked={selected}
                        onChange$={() => {
                          shippingRateId.value = rate.id;
                        }}
                      />
                      {!solo ? <span class="checkout-choice__mark" aria-hidden="true" /> : null}
                      <span class="checkout-choice__body">
                        <span class="checkout-choice__title">{rate.title}</span>
                        {rate.eta_label ? (
                          <span class="checkout-choice__meta">{rate.eta_label}</span>
                        ) : null}
                      </span>
                      <span class="checkout-choice__price">
                        {formatPrice(rate.amount, settings.value.currency, locale)}
                      </span>
                    </label>
                  );
                })}
              </div>
            </fieldset>
          ) : (
            <p class="footer-muted">{tStatic(locale, "checkout.shippingRatesHint")}</p>
          )}

          {isDigitalCheckout || isPickup || showLocationPicker ? (
            <div>
              <label for="location_id">
                {isPickup
                  ? tStatic(locale, "checkout.pickupLocation")
                  : tStatic(locale, "checkout.fulfillmentLocation")}
              </label>
              <input type="hidden" name="location_id" value={String(locationId.value)} />
              <SearchableSelect
                id="location_id"
                options={locationOptions}
                value={String(locationId.value || "")}
                placeholder={tStatic(locale, "forms.select")}
                required
                onChange$={(value) => {
                  locationId.value = Number(value);
                  stockWarning.value = null;
                }}
              />
            </div>
          ) : null}

          <div>
            <label for="order_note">{tStatic(locale, "checkout.orderNote")}</label>
            <textarea id="order_note" name="order_note" rows={3} />
          </div>

          {settings.value.cod_enabled || onlinePaymentsEnabled ? (
            <fieldset
              class={`checkout-choice-group${(settings.value.cod_enabled ? 1 : 0) + (onlinePaymentsEnabled ? 1 : 0) === 1 ? " checkout-choice-group--solo" : ""}`}
            >
              <legend>{tStatic(locale, "checkout.paymentMethod")}</legend>
              <div class="checkout-choice-group__list">
                {settings.value.cod_enabled ? (
                  <label
                    class={`checkout-choice${paymentMethod.value === "cod" ? " checkout-choice--selected" : ""}${!onlinePaymentsEnabled ? " checkout-choice--solo" : ""}`}
                  >
                    <input
                      type="radio"
                      name="payment_method"
                      value="cod"
                      class="checkout-choice__input"
                      checked={paymentMethod.value === "cod"}
                      onChange$={() => {
                        paymentMethod.value = "cod";
                      }}
                    />
                    {onlinePaymentsEnabled ? (
                      <span class="checkout-choice__mark" aria-hidden="true" />
                    ) : null}
                    <span class="checkout-choice__body">
                      <span class="checkout-choice__title">
                        {tStatic(locale, "checkout.paymentMethodCod")}
                      </span>
                    </span>
                  </label>
                ) : null}
                {onlinePaymentsEnabled ? (
                  <label
                    class={`checkout-choice${paymentMethod.value === "fawry" ? " checkout-choice--selected" : ""}${!settings.value.cod_enabled ? " checkout-choice--solo" : ""}`}
                  >
                    <input
                      type="radio"
                      name="payment_method"
                      value="fawry"
                      class="checkout-choice__input"
                      checked={paymentMethod.value === "fawry"}
                      onChange$={() => {
                        paymentMethod.value = "fawry";
                      }}
                    />
                    {settings.value.cod_enabled ? (
                      <span class="checkout-choice__mark" aria-hidden="true" />
                    ) : null}
                    <span class="checkout-choice__body">
                      <span class="checkout-choice__title">
                        {tStatic(locale, "checkout.paymentMethodOnline", {
                          provider: settings.value.online_payments.label || "FawryPay",
                        })}
                      </span>
                    </span>
                  </label>
                ) : null}
              </div>
            </fieldset>
          ) : (
            <p class="alert alert-error">{tStatic(locale, "checkout.noPaymentMethods")}</p>
          )}
        </form>

        <aside class="cart-summary checkout-summary">
          <h2 style={{ margin: "0 0 1rem", fontSize: "1.125rem" }}>{tStatic(locale, "checkout.orderSummary")}</h2>
          {promoAtCheckout && auth.token ? (
            <CouponField
              items={cart.items}
              locationId={locationId.value}
              token={auth.token}
              allowStacking={allowCouponStacking}
              currency={settings.value.currency}
              appliedCoupons={appliedCoupons.value}
              couponDiscount={couponDiscount.value}
              onApplied$={onCouponApplied$}
            />
          ) : promoAtCheckout ? (
            <p class="footer-muted" style={{ marginBottom: "1rem" }}>
              <Link href={`${localePath(locale, "/login")}?next=${encodeURIComponent(localePath(locale, "/checkout"))}`}>
                {tStatic(locale, "auth.login")}
              </Link>{" "}
              {tStatic(locale, "coupon.signInRequired")}
            </p>
          ) : null}
          <ul style={{ listStyle: "none", margin: 0, padding: 0 }}>
            {cart.items.map((line) => (
              <li
                key={line.variationId}
                style={{
                  display: "flex",
                  justifyContent: "space-between",
                  marginBottom: "0.5rem",
                  fontSize: "0.875rem",
                }}
              >
                <span>
                  {line.name} × {line.quantity}
                </span>
                <span>{formatPrice(line.price * line.quantity, settings.value.currency)}</span>
              </li>
            ))}
          </ul>
          {validatedShipping.value > 0 || isDigitalCheckout ? (
            <div
              style={{
                display: "flex",
                justifyContent: "space-between",
                marginTop: "0.5rem",
                fontSize: "0.875rem",
              }}
            >
              <span>
                {isDigitalCheckout
                  ? tStatic(locale, "checkout.delivery")
                  : tStatic(locale, "checkout.shipping")}
              </span>
              <span>
                {formatPrice(validatedShipping.value, settings.value.currency, locale)}
              </span>
            </div>
          ) : null}
          {couponDiscount.value > 0 ? (
            <div
              style={{
                display: "flex",
                justifyContent: "space-between",
                marginTop: "0.75rem",
                fontSize: "0.875rem",
                color: "var(--gs-accent)",
              }}
            >
              <span>{tStatic(locale, "coupon.discount")}</span>
              <span>-{formatPrice(couponDiscount.value, settings.value.currency, locale)}</span>
            </div>
          ) : null}
          {redeemAmount.value > 0 ? (
            <div
              style={{
                display: "flex",
                justifyContent: "space-between",
                marginTop: "0.75rem",
                fontSize: "0.875rem",
                color: "var(--gs-accent)",
              }}
            >
              <span>{tStatic(locale, "checkout.rewardPoints")}</span>
              <span>-{formatPrice(redeemAmount.value, settings.value.currency)}</span>
            </div>
          ) : null}
          <div
            style={{
              display: "flex",
              justifyContent: "space-between",
              marginTop: "1rem",
              fontWeight: 700,
            }}
          >
            <span>{tStatic(locale, "checkout.total")}</span>
            <span>{formatPrice(orderTotal, settings.value.currency)}</span>
          </div>

          <div class="checkout-summary__actions">
            {auth.token && rewardBalance.value?.enabled && (rewardBalance.value.max_redeem_points ?? 0) > 0 && stackWithRewardPoints.value ? (
              <RewardPointsRedeem
                token={auth.token}
                balance={rewardBalance.value}
                orderTotal={checkoutTotal}
                currency={settings.value.currency}
                pointsToRedeem={pointsToRedeem.value}
                onPointsChange$={onRewardPointsChange$}
              />
            ) : auth.token && rewardBalance.value?.enabled ? (
              <div class="reward-points-redeem reward-points-redeem--inactive">
                <h3 class="reward-points-redeem__title">
                  {rewardBalance.value.name || tStatic(locale, "rewards.defaultName")}
                </h3>
                <p class="footer-muted">
                  {(rewardBalance.value.available ?? 0) < (rewardBalance.value.min_redeem_points ?? 1)
                    ? (rewardBalance.value.available ?? 0) <= 0
                      ? tStatic(locale, "rewards.noPoints")
                      : tStatic(locale, "rewards.needMinPoints", {
                          min: rewardBalance.value.min_redeem_points ?? 1,
                          available: rewardBalance.value.available ?? 0,
                        })
                    : tStatic(locale, "rewards.cannotApply")}
                </p>
              </div>
            ) : !auth.token && settings.value.reward_points.enabled ? (
              <p class="footer-muted">
                <Link href={`${localePath(locale, "/login")}?next=${encodeURIComponent(localePath(locale, "/checkout"))}`}>
                  {tStatic(locale, "auth.login")}
                </Link>{" "}
                {tStatic(locale, "checkout.signInToRedeem")}
              </p>
            ) : null}

            <button
              type="submit"
              form="checkout-form"
              class="btn btn-primary btn-block"
              disabled={
                submitting.value ||
                validatingStock.value ||
                Boolean(stockWarning.value) ||
                !canCheckout ||
                (pointsToRedeem.value > 0 && !redeemValid.value)
              }
            >
              {submitting.value
                ? tStatic(locale, "checkout.placingOrder")
                : paymentMethod.value === "fawry"
                  ? tStatic(locale, "checkout.continueToPayment")
                  : tStatic(locale, "checkout.placeOrder")}
            </button>
          </div>
        </aside>
      </div>
    </section>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = resolveValue(useLangParam);
  return {
    title: tStatic(lang, "checkout.seoTitle", { businessName: settings.business_name }),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
