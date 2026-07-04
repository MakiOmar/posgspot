import { $, component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { Link, routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import { RewardPointsRedeem } from "~/components/checkout/reward-points-redeem";
import { PhoneInputWithDialCode } from "~/components/forms/phone-input-with-dial-code";
import { ApiError, checkout, fetchLocations, fetchPhoneCountries, fetchRewardPoints, validateCart } from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { clearCart } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { formatPrice } from "~/lib/format";
import { useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { usePendingState } from "~/lib/pending-context";
import type { CheckoutOrder, RewardPointsBalance } from "~/lib/types";
import { withPendingFeedback } from "~/lib/with-pending";
import { parseFullPhone, validatePhone } from "~/lib/phone-validation";
import { useSiteSettings } from "~/routes/[lang]/layout";

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

    if (cart.items.length === 0 || !locationId.value) {
      stockWarning.value = null;
      validatedSubtotal.value = 0;
      validatedShipping.value = 0;
      validatedTotal.value = 0;
      return;
    }

    validatingStock.value = true;
    try {
      const { data } = await validateCart({
        location_id: locationId.value,
        items: cart.items.map((line) => ({
          variation_id: line.variationId,
          quantity: line.quantity,
        })),
      });
      validatedSubtotal.value = data.subtotal;
      validatedShipping.value = data.shipping;
      validatedTotal.value = data.total;
      stockWarning.value = null;
    } catch (err) {
      if (err instanceof ApiError) {
        const messages = Object.values(err.errors).flat();
        stockWarning.value = messages.length ? messages.join(" ") : err.message;
      } else {
        stockWarning.value =
          err instanceof Error ? err.message : "Some items are no longer available.";
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

  const submitOrder$ = $(async (form: HTMLFormElement) => {
    if (cart.items.length === 0 || !locationId.value) {
      error.value = "Cart is empty or no store location selected.";
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

    if (pointsToRedeem.value > 0 && !redeemValid.value) {
      error.value = "Please fix reward points before placing your order.";
      return;
    }

    await withPendingFeedback(pending, submitting, async () => {
      error.value = null;

      const formData = new FormData(form);
      const items = cart.items.map((line) => ({
        variation_id: line.variationId,
        quantity: line.quantity,
      }));
      const payload: Record<string, unknown> = {
        idempotency_key: `web-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`,
        location_id: locationId.value,
        payment_method: "cod",
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
          country: String(formData.get("country") || "Egypt"),
        },
        order_note: String(formData.get("order_note") || ""),
      };

      if (pointsToRedeem.value > 0) {
        payload.reward_points = pointsToRedeem.value;
      }

      try {
        await validateCart({
          location_id: locationId.value,
          items,
        });
        const { data } = await checkout(payload, auth.token ?? undefined);
        order.value = data;
        clearCart(cart);
      } catch (err) {
        if (err instanceof ApiError) {
          const messages = Object.values(err.errors).flat();
          error.value = messages.length ? messages.join(" ") : err.message;
        } else {
          error.value = err instanceof Error ? err.message : "Checkout failed.";
        }
      }
    });
  });

  if (cart.items.length === 0 && !order.value) {
    return (
      <section>
        <h1 class="page-title">Checkout</h1>
        <div class="empty-state">
          <p>Your cart is empty.</p>
          <Link href={localePath(locale, "/products")} class="btn btn-primary">
            Continue shopping
          </Link>
        </div>
      </section>
    );
  }

  if (order.value) {
    return (
      <section>
        <h1 class="page-title">Order confirmed</h1>
        <div class="alert alert-success">
          Thank you! Order <strong>#{order.value.invoice_no}</strong> has been placed.
          Payment: {order.value.payment_status}.
        </div>
        <Link href={localePath(locale, "/products")} class="btn btn-primary">
          Continue shopping
        </Link>
      </section>
    );
  }

  return (
    <section>
      <h1 class="page-title">Checkout</h1>

      {error.value ? <div class="alert alert-error">{error.value}</div> : null}
      {stockWarning.value && !error.value ? (
        <div class="alert alert-error">{stockWarning.value}</div>
      ) : null}
      {validatingStock.value ? (
        <p class="footer-muted">Checking stock availability…</p>
      ) : null}

      <div style={{ display: "grid", gap: "2rem" }}>
        <form
          preventdefault:submit
          onSubmit$={(event) => submitOrder$(event.target as HTMLFormElement)}
          class="form-grid"
        >
          <h2 style={{ margin: 0, fontSize: "1.125rem" }}>Contact</h2>
          <div class="two-col">
            <div>
              <label for="first_name">First name</label>
              <input id="first_name" name="first_name" required defaultValue={auth.contact?.first_name || ""} />
            </div>
            <div>
              <label for="last_name">Last name</label>
              <input id="last_name" name="last_name" defaultValue={auth.contact?.last_name || ""} />
            </div>
          </div>
          <div class="two-col">
            <div>
              <label for="email">Email</label>
              <input id="email" name="email" type="email" required defaultValue={auth.contact?.email || ""} />
            </div>
            <div>
              <label for="checkout-mobile">Mobile</label>
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

          <h2 style={{ margin: "0.5rem 0 0", fontSize: "1.125rem" }}>Shipping</h2>
          <div>
            <label for="address_line_1">Address</label>
            <input id="address_line_1" name="address_line_1" required defaultValue={auth.contact?.address_line_1 || ""} />
          </div>
          <div class="two-col">
            <div>
              <label for="city">City</label>
              <input id="city" name="city" required defaultValue={auth.contact?.city || "Cairo"} />
            </div>
            <div>
              <label for="country">Country</label>
              <input id="country" name="country" defaultValue={auth.contact?.country || "Egypt"} />
            </div>
          </div>

          {showLocationPicker ? (
            <div>
              <label for="location_id">Fulfillment location</label>
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
            <label for="order_note">Order note (optional)</label>
            <textarea id="order_note" name="order_note" rows={3} />
          </div>

          {settings.value.cod_enabled ? (
            <p class="footer-muted">Payment method: Cash on delivery (COD)</p>
          ) : (
            <p class="alert alert-error">COD is not available right now.</p>
          )}

          {auth.token && rewardBalance.value?.enabled && (rewardBalance.value.max_redeem_points ?? 0) > 0 ? (
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
                {rewardBalance.value.name || "Reward Points"}
              </h3>
              <p class="footer-muted">
                {(rewardBalance.value.available ?? 0) < (rewardBalance.value.min_redeem_points ?? 1)
                  ? (rewardBalance.value.available ?? 0) <= 0
                    ? "You have no reward points available to redeem on this order."
                    : `You need at least ${rewardBalance.value.min_redeem_points ?? 1} points to redeem (you have ${rewardBalance.value.available}).`
                  : "Reward points cannot be applied to this order right now."}
              </p>
            </div>
          ) : !auth.token && settings.value.reward_points.enabled ? (
            <p class="footer-muted">
              <Link href={`${localePath(locale, "/login")}?next=${encodeURIComponent(localePath(locale, "/checkout"))}`}>Sign in</Link> to redeem reward points at checkout.
            </p>
          ) : null}

          <button
            type="submit"
            class="btn btn-primary btn-block"
            disabled={
              submitting.value ||
              validatingStock.value ||
              Boolean(stockWarning.value) ||
              !settings.value.cod_enabled ||
              (pointsToRedeem.value > 0 && !redeemValid.value)
            }
          >
            {submitting.value ? "Placing order…" : "Place order"}
          </button>
        </form>

        <aside class="cart-summary">
          <h2 style={{ margin: "0 0 1rem", fontSize: "1.125rem" }}>Order summary</h2>
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
              <span>Shipping</span>
              <span>{formatPrice(validatedShipping.value, settings.value.currency)}</span>
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
              <span>Reward points</span>
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
            <span>Total</span>
            <span>{formatPrice(orderTotal, settings.value.currency)}</span>
          </div>
        </aside>
      </div>
    </section>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const settings = resolveValue(useSiteSettings);
  return {
    title: `Checkout — ${settings.business_name}`,
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
