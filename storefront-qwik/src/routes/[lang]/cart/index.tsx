import { $, component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { TrashIcon } from "~/components/icons";
import { CouponField } from "~/components/checkout/coupon-field";
import { QuantityStepper } from "~/components/ui/quantity-stepper";
import { ApiError, inspectCart } from "~/lib/api";
import {
  cartSubtotal,
  clearAppliedCoupon,
  formatMaxCartQuantity,
  loadAppliedCoupon,
  removeCartItem,
  setCartQuantity,
  syncCartFromInspection,
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
  const appliedCoupon = useSignal<AppliedCouponInfo | null>(null);
  const couponDiscount = useSignal(0);
  const couponCode = useSignal(loadAppliedCoupon()?.code || "");
  const checkoutQuantityIssues = useSignal<CartLineStatus[]>([]);
  const checkoutChecking = useSignal(false);

  const cartItemsKey = cart.items
    .map((line) => `${line.variationId}:${line.quantity}`)
    .join("|");

  // Inspect prices/stock and auto-remove fully OOS lines whenever the cart changes.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => cartItemsKey);
    track(() => cart.hydrated);
    track(() => couponCode.value);
    track(() => auth.token);

    if (!auth.token) {
      couponCode.value = "";
      appliedCoupon.value = null;
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
      appliedCoupon.value = null;
      couponDiscount.value = 0;
      return;
    }

    validating.value = true;
    errorNotice.value = null;
    try {
      const { data } = await inspectCart({
        coupon_code: auth.token && couponCode.value ? couponCode.value : undefined,
        items: cart.items.map((line) => ({
          variation_id: line.variationId,
          quantity: line.quantity,
        })),
      });
      const { removedCount, pricesChanged } = syncCartFromInspection(cart, data);
      pricesUpdated.value = pricesChanged;
      validatedSubtotal.value = data.subtotal;
      validatedShipping.value = data.shipping;
      validatedTotal.value = data.total;
      appliedCoupon.value = data.coupon ?? null;
      couponDiscount.value = data.coupon_discount ?? 0;
      if (!data.coupon && couponCode.value) {
        couponCode.value = "";
      }
      removedNotice.value =
        removedCount > 0
          ? tStatic(locale, "cart.removedOutOfStock", { count: String(removedCount) })
          : null;
    } catch (err) {
      pricesUpdated.value = false;
      validatedSubtotal.value = null;
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
      const { data } = await inspectCart({
        coupon_code: auth.token && couponCode.value ? couponCode.value : undefined,
        items: cart.items.map((line) => ({
          variation_id: line.variationId,
          quantity: line.quantity,
        })),
      });
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
    validatedTotal.value !== null ? validatedTotal.value : subtotal + validatedShipping.value;

  const onCouponApplied$ = $((coupon: AppliedCouponInfo | null, discount: number) => {
    appliedCoupon.value = coupon;
    couponDiscount.value = discount;
    couponCode.value = coupon?.code || "";
  });

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
            <tr key={line.variationId}>
              <td>
                <strong>{line.name}</strong>
                {line.variationName !== "DUMMY" ? (
                  <div class="footer-muted">{line.variationName}</div>
                ) : null}
              </td>
              <td>{formatPrice(line.price, settings.value.currency, locale)}</td>
              <td>
                <QuantityStepper
                  value={line.quantity}
                  label={tStatic(locale, "a11y.quantityFor", { name: line.name })}
                  onChange$={(next) => setCartQuantity(cart, line.variationId, next)}
                />
              </td>
              <td>{formatPrice(line.price * line.quantity, settings.value.currency, locale)}</td>
              <td>
                <button
                  type="button"
                  class="btn btn-secondary footer-contact"
                  aria-label={tStatic(locale, "a11y.removeItem")}
                  onClick$={() => removeCartItem(cart, line.variationId)}
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
        {auth.token ? (
          <CouponField
            items={cart.items}
            token={auth.token}
            currency={settings.value.currency}
            initialCode={couponCode.value}
            appliedCoupon={appliedCoupon.value}
            couponDiscount={couponDiscount.value}
            onApplied$={onCouponApplied$}
          />
        ) : (
          <p class="footer-muted" style={{ marginBottom: "1rem" }}>
            <Link href={`${localePath(locale, "/login")}?next=${encodeURIComponent(localePath(locale, "/cart"))}`}>
              {tStatic(locale, "auth.login")}
            </Link>{" "}
            {tStatic(locale, "coupon.signInRequired")}
          </p>
        )}
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
        {validatedShipping.value > 0 ? (
          <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "1rem" }}>
            <span>{tStatic(locale, "checkout.shipping")}</span>
            <span>{formatPrice(validatedShipping.value, settings.value.currency, locale)}</span>
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
