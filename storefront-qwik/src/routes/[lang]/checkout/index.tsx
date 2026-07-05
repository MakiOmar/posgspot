import { $, component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { Link, routeLoader$, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { RewardPointsRedeem } from "~/components/checkout/reward-points-redeem";
import { CouponField } from "~/components/checkout/coupon-field";
import { PhoneInputWithDialCode } from "~/components/forms/phone-input-with-dial-code";
import { ApiError, checkout, fetchLocations, fetchPhoneCountries, fetchRewardPoints, validateCart } from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { clearCart, clearAppliedCoupon, couponRequestPayload, loadAppliedCoupons, persistAppliedCoupons } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { storeFawryPaymentSession } from "~/lib/fawry-pay";
import { formatPrice } from "~/lib/format";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { usePendingState } from "~/lib/pending-context";
import type { AppliedCouponInfo, CheckoutOrder, RewardPointsBalance } from "~/lib/types";
import { withPendingFeedback } from "~/lib/with-pending";
import { parseFullPhone, validatePhone } from "~/lib/phone-validation";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

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

export default component$(() => {
  const settings = useSiteSettings();
  const { locale } = useI18n();
  const nav = useNavigate();
  const locations = useCheckoutLocations();
  const phoneCountries = useCheckoutPhoneCountries();
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

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => locationId.value);
    track(() => cartItemsKey);
    track(() => couponCodesKey);
    track(() => auth.token);
    track(() => promoAtCheckout);

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
      appliedCoupons.value = [];
      couponDiscount.value = 0;
      return;
    }

    validatingStock.value = true;
    try {
      const couponPayload =
        auth.token && promoAtCheckout
          ? couponRequestPayload(couponCodes.value, allowCouponStacking)
          : {};
      const { data } = await validateCart({
        location_id: locationId.value,
        ...couponPayload,
        items: cart.items.map((line) => ({
          variation_id: line.variationId,
          quantity: line.quantity,
        })),
      });
      validatedSubtotal.value = data.subtotal;
      validatedShipping.value = data.shipping;
      validatedTotal.value = data.total;
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
  });

  const subtotal = validatedTotal.value > 0
    ? validatedSubtotal.value
    : cart.items.reduce((sum, line) => sum + line.price * line.quantity, 0);

  const checkoutTotal = validatedTotal.value > 0 ? validatedTotal.value : subtotal;
  const orderTotal = Math.max(0, checkoutTotal - redeemAmount.value);
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

  const onCouponApplied$ = $((coupons: AppliedCouponInfo[], discount: number) => {
    appliedCoupons.value = coupons;
    couponDiscount.value = discount;
    couponCodes.value = coupons.map((coupon) => coupon.code);
    if (coupons.length === 0) {
      stackWithRewardPoints.value = true;
    }
  });

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
      const items = cart.items.map((line) => ({
        variation_id: line.variationId,
        quantity: line.quantity,
      }));
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
        shipping_address: {
          address_line_1: String(formData.get("address_line_1") || ""),
          city: String(formData.get("city") || ""),
          country: String(formData.get("country") || tStatic(locale, "checkout.defaultCountry")),
        },
        order_note: String(formData.get("order_note") || ""),
      };

      if (pointsToRedeem.value > 0) {
        payload.reward_points = pointsToRedeem.value;
      }

      if (auth.token && promoAtCheckout && couponCodes.value.length > 0) {
        Object.assign(payload, couponRequestPayload(couponCodes.value, allowCouponStacking));
      }

      try {
        await validateCart({
          location_id: locationId.value,
          ...(auth.token && promoAtCheckout
            ? couponRequestPayload(couponCodes.value, allowCouponStacking)
            : {}),
          items,
        });
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

      <div style={{ display: "grid", gap: "2rem" }}>
        <form
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

          <h2 style={{ margin: "0.5rem 0 0", fontSize: "1.125rem" }}>{tStatic(locale, "checkout.shipping")}</h2>
          <div>
            <label for="address_line_1">{tStatic(locale, "forms.address")}</label>
            <input id="address_line_1" name="address_line_1" required defaultValue={auth.contact?.address_line_1 || ""} />
          </div>
          <div class="two-col">
            <div>
              <label for="city">{tStatic(locale, "forms.city")}</label>
              <input id="city" name="city" required defaultValue={auth.contact?.city || tStatic(locale, "checkout.defaultCity")} />
            </div>
            <div>
              <label for="country">{tStatic(locale, "forms.country")}</label>
              <input id="country" name="country" defaultValue={auth.contact?.country || tStatic(locale, "checkout.defaultCountry")} />
            </div>
          </div>

          {showLocationPicker ? (
            <div>
              <label for="location_id">{tStatic(locale, "checkout.fulfillmentLocation")}</label>
              <select
                id="location_id"
                name="location_id"
                required
                onChange$={(event) => {
                  locationId.value = Number((event.target as HTMLSelectElement).value);
                  stockWarning.value = null;
                }}
              >
                {sellingLocations.map((loc) => (
                  <option key={loc.id} value={loc.id} selected={loc.id === locationId.value}>
                    {loc.name}
                  </option>
                ))}
              </select>
            </div>
          ) : null}

          <div>
            <label for="order_note">{tStatic(locale, "checkout.orderNote")}</label>
            <textarea id="order_note" name="order_note" rows={3} />
          </div>

          {settings.value.cod_enabled || onlinePaymentsEnabled ? (
            <fieldset class="checkout-payment-methods">
              <legend>{tStatic(locale, "checkout.paymentMethod")}</legend>
              {settings.value.cod_enabled ? (
                <label class="checkout-payment-methods__option">
                  <input
                    type="radio"
                    name="payment_method"
                    value="cod"
                    checked={paymentMethod.value === "cod"}
                    onChange$={() => {
                      paymentMethod.value = "cod";
                    }}
                  />
                  <span>{tStatic(locale, "checkout.paymentMethodCod")}</span>
                </label>
              ) : null}
              {onlinePaymentsEnabled ? (
                <label class="checkout-payment-methods__option">
                  <input
                    type="radio"
                    name="payment_method"
                    value="fawry"
                    checked={paymentMethod.value === "fawry"}
                    onChange$={() => {
                      paymentMethod.value = "fawry";
                    }}
                  />
                  <span>
                    {tStatic(locale, "checkout.paymentMethodOnline", {
                      provider: settings.value.online_payments.label || "FawryPay",
                    })}
                  </span>
                </label>
              ) : null}
            </fieldset>
          ) : (
            <p class="alert alert-error">{tStatic(locale, "checkout.noPaymentMethods")}</p>
          )}

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
              <Link href={`${localePath(locale, "/login")}?next=${encodeURIComponent(localePath(locale, "/checkout"))}`}>{tStatic(locale, "auth.login")}</Link> {tStatic(locale, "checkout.signInToRedeem")}
            </p>
          ) : null}

          <button
            type="submit"
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
        </form>

        <aside class="cart-summary">
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
          {validatedShipping.value > 0 ? (
            <div
              style={{
                display: "flex",
                justifyContent: "space-between",
                marginTop: "0.5rem",
                fontSize: "0.875rem",
              }}
            >
              <span>{tStatic(locale, "checkout.shipping")}</span>
              <span>{formatPrice(validatedShipping.value, settings.value.currency)}</span>
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
