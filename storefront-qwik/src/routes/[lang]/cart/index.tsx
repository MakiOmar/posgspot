import { $, component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { TrashIcon } from "~/components/icons";
import { CouponField } from "~/components/checkout/coupon-field";
import { PageTitleBar } from "~/components/layout/page-title-bar";
import { QuantityStepper } from "~/components/ui/quantity-stepper";
import { ApiError, inspectCart } from "~/lib/api";
import {
  cartSubtotal,
  clearAppliedCoupon,
  couponCodesKey,
  couponRequestPayload,
  formatMaxCartQuantity,
  loadAppliedCoupons,
  persistAppliedCoupons,
  removeCartItem,
  sameCouponCodes,
  setCartQuantity,
  syncCartFromInspection,
  cartItemsFingerprint,
  cartLineKey,
  toCartApiItem,
} from "~/lib/cart-actions";
import { useAuth } from "~/lib/auth-context";
import { needsEmailVerification } from "~/lib/verification";
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

  // Inspect prices/stock and auto-remove fully OOS lines whenever the cart changes.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track, cleanup }) => {
    // Track store contents directly — a render-time string is not a signal.
    const itemsKey = track(() => cartItemsFingerprint(cart.items));
    track(() => cart.hydrated);
    track(() => couponCodesKey(couponCodes.value));
    track(() => auth.token);
    track(() => promoAtCheckout);

    if (!auth.token || !promoAtCheckout) {
      if (couponCodes.value.length > 0) {
        couponCodes.value = [];
      }
      appliedCoupons.value = [];
      couponDiscount.value = 0;
      clearAppliedCoupon();
    }

    checkoutQuantityIssues.value = [];

    if (!cart.hydrated || cart.items.length === 0 || !itemsKey) {
      removedNotice.value = null;
      errorNotice.value = null;
      pricesUpdated.value = false;
      validatedSubtotal.value = null;
      validatedShipping.value = 0;
      validatedTotal.value = null;
      appliedCoupons.value = [];
      couponDiscount.value = 0;
      validating.value = false;
      return;
    }

    // Optimistic totals from local lines so remove/qty changes update immediately.
    const localSubtotalBefore = cartSubtotal(cart);
    validatedSubtotal.value = localSubtotalBefore;
    validatedTotal.value = Math.max(
      0,
      localSubtotalBefore - couponDiscount.value + validatedShipping.value,
    );

    const timer = setTimeout(async () => {
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
            items: cart.items.map(toCartApiItem),
          },
          auth.token ?? undefined,
        );
        const { removedCount, pricesChanged } = syncCartFromInspection(cart, data);
        pricesUpdated.value = pricesChanged;
        const inspectMissesDigital = cart.items.some(
          (item) => item.digital && !data.lines.some((line) => line.variation_id === item.variationId),
        );
        const localSubtotal = cartSubtotal(cart);
        validatedSubtotal.value = inspectMissesDigital ? localSubtotal : data.subtotal;
        validatedShipping.value = data.shipping;
        validatedTotal.value = inspectMissesDigital
          ? localSubtotal + data.shipping
          : data.total;
        appliedCoupons.value = data.coupons?.length
          ? data.coupons
          : data.coupon
            ? [data.coupon]
            : [];
        couponDiscount.value = data.coupon_discount ?? 0;
        const nextCodes = appliedCoupons.value.map((coupon) => coupon.code);
        if (!sameCouponCodes(couponCodes.value, nextCodes)) {
          couponCodes.value = nextCodes;
        }
        if (nextCodes.length === 0) {
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
        // Keep optimistic local totals on inspect failure.
        const localSubtotal = cartSubtotal(cart);
        validatedSubtotal.value = localSubtotal;
        validatedTotal.value = Math.max(
          0,
          localSubtotal - couponDiscount.value + validatedShipping.value,
        );
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
    }, 350);

    cleanup(() => clearTimeout(timer));
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
          items: cart.items.map(toCartApiItem),
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
      if (needsEmailVerification(auth.contact)) {
        await nav(
          localePath(
            locale,
            `/verify-email?email=${encodeURIComponent(auth.contact?.email || "")}&next=/checkout`,
          ),
        );
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

  if (!cart.hydrated) {
    return (
      <section>
        <PageTitleBar
          title={tStatic(locale, "cart.title")}
          crumbs={[{ label: tStatic(locale, "cart.title") }]}
        />
        <p class="footer-muted cart-status" role="status">
          {tStatic(locale, "cart.refreshing")}
        </p>
      </section>
    );
  }

  if (cart.items.length === 0) {
    return (
      <section>
        <PageTitleBar
          title={tStatic(locale, "cart.title")}
          crumbs={[{ label: tStatic(locale, "cart.title") }]}
        />
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
        <div class="empty-state">
          <p>{tStatic(locale, "cart.empty")}</p>
          <Link href={localePath(locale, "/products")} class="btn btn-primary">
            {tStatic(locale, "cart.continueShopping")}
          </Link>
        </div>
      </section>
    );
  }

  // Always prefer live line math for merchandise subtotal so Remove/qty feel instant.
  // Server inspect still refreshes prices, shipping, and coupons via the signals above.
  const liveSubtotal = cartSubtotal(cart);
  const subtotal = liveSubtotal;
  const orderTotal =
    validatedTotal.value !== null
      ? Math.max(0, liveSubtotal - couponDiscount.value + validatedShipping.value)
      : liveSubtotal;

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
      <PageTitleBar
        title={tStatic(locale, "cart.title")}
        crumbs={[{ label: tStatic(locale, "cart.title") }]}
      />

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
                  <span>{line.quantity}</span>
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
