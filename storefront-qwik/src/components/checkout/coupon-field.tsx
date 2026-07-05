import { $, component$, useSignal, type QRL } from "@builder.io/qwik";
import { ApiError, validateCoupon } from "~/lib/api";
import { persistAppliedCoupon } from "~/lib/cart-actions";
import { formatPrice } from "~/lib/format";
import { tStatic, useI18n } from "~/lib/i18n/context";
import type { AppliedCouponInfo, CartItem, StoreSettings } from "~/lib/types";

interface CouponFieldProps {
  items: CartItem[];
  locationId?: number;
  token?: string;
  currency: StoreSettings["currency"];
  initialCode?: string;
  appliedCoupon: AppliedCouponInfo | null;
  couponDiscount: number;
  onApplied$: QRL<(coupon: AppliedCouponInfo | null, discount: number) => void>;
}

/** Cart/checkout promo code apply and remove. */
export const CouponField = component$<CouponFieldProps>((props) => {
  const { locale } = useI18n();
  const inputCode = useSignal(props.initialCode || props.appliedCoupon?.code || "");
  const applying = useSignal(false);
  const error = useSignal<string | null>(null);

  const applyCode$ = $(async () => {
    const code = inputCode.value.trim();
    if (!code || props.items.length === 0) {
      return;
    }

    applying.value = true;
    error.value = null;
    try {
      const { data } = await validateCoupon(
        {
          code,
          location_id: props.locationId,
          items: props.items.map((line) => ({
            variation_id: line.variationId,
            quantity: line.quantity,
          })),
        },
        props.token,
      );

      if (!data.coupon) {
        error.value = tStatic(locale, "coupon.invalid");
        await props.onApplied$(null, 0);
        persistAppliedCoupon(null);
        return;
      }

      persistAppliedCoupon({ code: data.coupon.code, label: data.coupon.label });
      await props.onApplied$(data.coupon, data.coupon_discount ?? 0);
    } catch (err) {
      persistAppliedCoupon(null);
      await props.onApplied$(null, 0);
      if (err instanceof ApiError) {
        const messages = Object.values(err.errors).flat();
        error.value = messages.length ? messages.join(" ") : err.message;
      } else {
        error.value = tStatic(locale, "coupon.applyError");
      }
    } finally {
      applying.value = false;
    }
  });

  const removeCode$ = $(async () => {
    inputCode.value = "";
    error.value = null;
    persistAppliedCoupon(null);
    await props.onApplied$(null, 0);
  });

  return (
    <div class="coupon-field">
      <label for="coupon-code">{tStatic(locale, "coupon.label")}</label>
      <div class="coupon-field__row">
        <input
          id="coupon-code"
          type="text"
          class="coupon-field__input"
          autocomplete="off"
          placeholder={tStatic(locale, "coupon.placeholder")}
          value={inputCode.value}
          onInput$={(_, el) => {
            inputCode.value = el.value;
          }}
          disabled={applying.value || Boolean(props.appliedCoupon)}
        />
        {props.appliedCoupon ? (
          <button type="button" class="btn btn-secondary" onClick$={removeCode$}>
            {tStatic(locale, "coupon.remove")}
          </button>
        ) : (
          <button
            type="button"
            class="btn btn-secondary"
            disabled={applying.value || inputCode.value.trim() === ""}
            onClick$={applyCode$}
          >
            {applying.value ? tStatic(locale, "coupon.applying") : tStatic(locale, "coupon.apply")}
          </button>
        )}
      </div>
      {props.appliedCoupon ? (
        <p class="footer-muted coupon-field__applied" role="status">
          {tStatic(locale, "coupon.applied", {
            code: props.appliedCoupon.code,
            label: props.appliedCoupon.label,
          })}
          {props.couponDiscount > 0
            ? ` (−${formatPrice(props.couponDiscount, props.currency, locale)})`
            : null}
        </p>
      ) : null}
      {error.value ? (
        <p class="alert alert-error coupon-field__error" role="alert">
          {error.value}
        </p>
      ) : null}
    </div>
  );
});
