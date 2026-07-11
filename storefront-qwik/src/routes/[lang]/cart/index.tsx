import { $, component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { TrashIcon } from "~/components/icons";
import { CouponField } from "~/components/checkout/coupon-field";
import { QuantityStepper } from "~/components/ui/quantity-stepper";
import { ApiError, inspectCart } from "~/lib/api";
import {
  cartSubtotal,
  clearAppliedCoupon,
  couponRequestPayload,
  formatMaxCartQuantity,
  loadAppliedCoupons,
  persistAppliedCoupons,
  removeCartItem,
  setCartQuantity,
  syncCartFromInspection,
  cartLineKey,
} from "~/lib/cart-actions";
import { useAuth } from "~/lib/auth-context";
import { useCart } from "~/lib/cart-context";
import { formatPrice } from "~/lib/format";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { AppliedCouponInfo, CartLineStatus } from "~/lib/types";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

export default component$(() => {
  const settings = useSiteSettings();
  const cart = useCart();
  const auth = useAuth();
  const nav = useNavigate();
  const { locale } = useI18n();
  const validating = useSignal(false);
  const removedNotice = useSignal<string | null>(null);
  const errorNotice = useSignal<string | null>(null);
  const pricesUpdated = useSignal(false);
  const validatedSubtotal = useSignal<number | null>(null);
  const validatedShipping = useSignal(0);
  const validatedTotal = useSignal<number | null>(null);
  const appliedCoupons = useSignal<AppliedCouponInfo[]>([]);
  const couponDiscount = useSignal(0);
  const couponCodes = useSignal<string[]>(loadAppliedCoupons().map((coupon) => coupon.code));
  const checkoutQuantityIssues = useSignal<CartLineStatus[]>([]);
  const checkoutChecking = useSignal(false);

  const promoAtCheckout = settings.value.promo_codes?.enabled_at_checkout ?? true;
  const allowCouponStacking = settings.value.promo_codes?.allow_stacking ?? false;
  const couponCodesKey = couponCodes.value.join("|");

  const cartItemsKey = cart.items
    .map((line) => `${line.variationId}:${line.quantity}`)
    .join("|");

  // Inspect prices/stock and auto-remove fully OOS lines whenever the cart changes.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => cartItemsKey);
    track(() => cart.hydrated);
    track(() => couponCodesKey);
    track(() => auth.token);
    track(() => promoAtCheckout);

    if (!auth.token || !promoAtCheckout) {
      couponCodes.value = [];
      appliedCoupons.value = [];
      couponDiscount.value = 0;
      clearAppliedCoupon();
    }

    checkoutQuantityIssues.value = [];

    if (!cart.hydrated || cart.items.length === 0) {
      removedNotice.value = null;
      errorNotice.value = null;
      pricesUpdated.value = false;
      validatedSubtotal.value = null;
      validatedShipping.value = 0;
      validatedTotal.value = null;
      appliedCoupons.value = [];
      couponDiscount.value = 0;
      return;
    }

    validating.value = true;
    errorNotice.value = null;
    try {
      const couponPayload =
        auth.token && promoAtCheckout
          ? couponRequestPayload(couponCodes.value, allowCouponStacking)
          : {};
      const { data } = await inspectCart(
        {
          ...couponPayload,
          items: cart.items.map((line) => ({
            variation_id: line.variationId,
            quantity: line.quantity,
            ...(line.digital ? { digital: line.digital } : {}),
          })),
        },
        auth.token ?? undefined,
      );
      const { removedCount, pricesChanged } = syncCartFromInspection(cart, data);
      pricesUpdated.value = pricesChanged;
      validatedSubtotal.value = data.subtotal;
      validatedShipping.value = data.shipping;
      validatedTotal.value = data.total;
      appliedCoupons.value = data.coupons?.length
        ? data.coupons
        : data.coupon
          ? [data.coupon]
          : [];
      couponDiscount.value = data.coupon_discount ?? 0;
      couponCodes.value = appliedCoupons.value.map((coupon) => coupon.code);
      if (appliedCoupons.value.length === 0) {
        clearAppliedCoupon();
      } else {
        persistAppliedCoupons(
          appliedCoupons.value.map((coupon) => ({ code: coupon.code, label: coupon.label })),
        );
      }
      removedNotice.value =
        removedCount > 0
          ? tStatic(locale, "cart.removedOutOfStock", { count: String(removedCount) })
          : null;
    } catch (err) {
      pricesUpdated.value = false;
      validatedSubtotal.value = null;
      validatedTotal.value = null;
      removedNotice.value = null;
      if (err instanceof ApiError) {
        const messages = Object.values(err.errors).flat();
        errorNotice.value = messages.length ? messages.join(" ") : err.message;
      } else {
        errorNotice.value =
          err instanceof Error ? err.message : tStatic(locale, "cart.stockIssue");
      }
    } finally {
      validating.value = false;
    }
  });

  const goToCheckout$ = $(async () => {
    if (cart.items.length === 0) {
      return;
    }

    checkoutChecking.value = true;
    checkoutQuantityIssues.value = [];
    errorNotice.value = null;
    try {
      const couponPayload =
        auth.token && promoAtCheckout
          ? couponRequestPayload(couponCodes.value, allowCouponStacking)
          : {};
      const { data } = await inspectCart(
        {
          ...couponPayload,
          items: cart.items.map((line) => ({
            variation_id: line.variationId,
            quantity: line.quantity,
            ...(line.digital ? { digital: line.digital } : {}),
          })),
        },
        auth.token ?? undefined,
      );
      const { removedCount, partialIssues } = syncCartFromInspection(cart, data);
      if (removedCount > 0) {
        removedNotice.value = tStatic(locale, "cart.removedOutOfStock", { count: String(removedCount) });
      }
      if (partialIssues.length > 0) {
        checkoutQuantityIssues.value = partialIssues;
        return;
      }
      await nav(localePath(locale, "/checkout"));
    } catch (err) {
      if (err instanceof ApiError) {
        const messages = Object.values(err.errors).flat();
        errorNotice.value = messages.length ? messages.join(" ") : err.message;
      } else {
        errorNotice.value =
          err instanceof Error ? err.message : tStatic(locale, "cart.stockIssue");
      }
    } finally {
      checkoutChecking.value = false;
    }
  });

  if (cart.items.length === 0) {
    return (
      <section>
        <h1 class="page-title">{tStatic(locale, "cart.title")}</h1>
        <div class="empty-state">
          <p>{tStatic(locale, "cart.empty")}</p>
          <Link href={localePath(locale, "/products")} class="btn btn-primary">
            {tStatic(locale, "cart.continueShopping")}
          </Link>
        </div>
      </section>
    );
  }

  const subtotal =
    validatedSubtotal.value !== null ? validatedSubtotal.value : cartSubtotal(cart);
  const orderTotal =
    validatedTotal.value !== null
      ? validatedTotal.value
      : Math.max(0, subtotal - couponDiscount.value + validatedShipping.value);

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
      } else if (coupons.length === 0 && validatedSubtotal.value !== null) {
        validatedTotal.value = validatedSubtotal.value + validatedShipping.value;
      }
    },
  );

  return (
    <section>
      <h1 class="page-title">{tStatic(locale, "cart.title")}</h1>

      {validating.value ? (
        <p class="footer-muted cart-status" role="status">
          {tStatic(locale, "cart.refreshing")}
        </p>
      ) : null}

      {removedNotice.value ? (
        <p class="alert alert-success" role="status">
          {removedNotice.value}
        </p>
      ) : null}

      {errorNotice.value ? (
        <p class="alert alert-error" role="alert">
          {errorNotice.value}
        </p>
      ) : null}

      {checkoutQuantityIssues.value.length > 0 ? (
        <div class="alert alert-error" role="alert">
          <p style={{ margin: "0 0 0.5rem" }}>{tStatic(locale, "cart.stockQuantityIssuesTitle")}</p>
          <ul class="cart-stock-issues">
            {checkoutQuantityIssues.value.map((line) => (
              <li key={line.variation_id}>
                {tStatic(locale, "cart.stockQuantityIssue", {
                  name: line.name,
                  max: formatMaxCartQuantity(line.max_quantity ?? 0),
                  requested: String(line.requested_quantity),
                })}
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      {!validating.value &&
      pricesUpdated.value &&
      !removedNotice.value &&
      !errorNotice.value &&
      checkoutQuantityIssues.value.length === 0 ? (
        <p class="alert alert-success" role="status">
          {tStatic(locale, "cart.pricesUpdated")}
        </p>
      ) : null}

      <table class="cart-table">
        <thead>
          <tr>
            <th>{tStatic(locale, "cart.product")}</th>
            <th>{tStatic(locale, "cart.price")}</th>
            <th>{tStatic(locale, "cart.qty")}</th>
            <th>{tStatic(locale, "cart.total")}</th>
            <th />
          </tr>
        </thead>
        <tbody>
          {cart.items.map((line) => (
            <tr key={cartLineKey(line)}>
              <td>
                <strong>{line.name}</strong>
                {line.variationName !== "DUMMY" ? (
                  <div class="footer-muted">{line.variationName}</div>
                ) : null}
              </td>
              <td>{formatPrice(line.price, settings.value.currency, locale)}</td>
              <td>
                {line.digital ? (
                  <span>1</span>
                ) : (
                  <QuantityStepper
                    value={line.quantity}
                    label={tStatic(locale, "a11y.quantityFor", { name: line.name })}
                    onChange$={(next) => setCartQuantity(cart, cartLineKey(line), next)}
                  />
                )}
              </td>
              <td>{formatPrice(line.price * line.quantity, settings.value.currency, locale)}</td>
              <td>
                <button
                  type="button"
                  class="btn btn-secondary footer-contact"
                  aria-label={tStatic(locale, "a11y.removeItem")}
                  onClick$={() => removeCartItem(cart, cartLineKey(line))}
                >
                  <TrashIcon size={16} />
                  {tStatic(locale, "cart.remove")}
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      <div class="cart-summary">
        {promoAtCheckout && auth.token ? (
          <CouponField
            items={cart.items}
            token={auth.token}
            allowStacking={allowCouponStacking}
            currency={settings.value.currency}
            appliedCoupons={appliedCoupons.value}
            couponDiscount={couponDiscount.value}
            onApplied$={onCouponApplied$}
          />
        ) : promoAtCheckout ? (
          <p class="footer-muted" style={{ marginBottom: "1rem" }}>
            <Link href={`${localePath(locale, "/login")}?next=${encodeURIComponent(localePath(locale, "/cart"))}`}>
              {tStatic(locale, "auth.login")}
            </Link>{" "}
            {tStatic(locale, "coupon.signInRequired")}
          </p>
        ) : null}
        <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "0.5rem" }}>
          <span>{tStatic(locale, "cart.subtotal")}</span>
          <strong>{formatPrice(subtotal, settings.value.currency, locale)}</strong>
        </div>
        {couponDiscount.value > 0 ? (
          <div
            style={{
              display: "flex",
              justifyContent: "space-between",
              marginBottom: "0.5rem",
              color: "var(--gs-accent)",
            }}
          >
            <span>{tStatic(locale, "coupon.discount")}</span>
            <span>-{formatPrice(couponDiscount.value, settings.value.currency, locale)}</span>
          </div>
        ) : null}
        {validatedTotal.value !== null ? (
          <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "0.5rem" }}>
            <span>{tStatic(locale, "cart.shippingEstimate")}</span>
            <span>
              {validatedShipping.value > 0
                ? formatPrice(validatedShipping.value, settings.value.currency, locale)
                : tStatic(locale, "cart.shippingAtCheckout")}
            </span>
          </div>
        ) : null}
        <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "1rem", fontWeight: 700 }}>
          <span>{tStatic(locale, "checkout.total")}</span>
          <strong>{formatPrice(orderTotal, settings.value.currency, locale)}</strong>
        </div>
        <button
          type="button"
          class="btn btn-primary btn-block"
          disabled={validating.value || checkoutChecking.value}
          onClick$={goToCheckout$}
        >
          {checkoutChecking.value ? tStatic(locale, "cart.checkingCheckout") : tStatic(locale, "cart.checkout")}
        </button>
      </div>
    </section>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = resolveValue(useLangParam);
  return {
    title: tStatic(lang, "cart.seoTitle", { businessName: settings.business_name }),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
