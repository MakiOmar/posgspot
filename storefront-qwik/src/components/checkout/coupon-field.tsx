import { $, component$, useSignal, type QRL } from "@builder.io/qwik";
import { ApiError, validateCoupon } from "~/lib/api";
import { persistAppliedCoupons } from "~/lib/cart-actions";
import { formatPrice } from "~/lib/format";
import { tStatic, useI18n } from "~/lib/i18n/context";
import type { AppliedCouponInfo, CartItem, StoreSettings } from "~/lib/types";

interface CouponFieldProps {
  items: CartItem[];
  locationId?: number;
  token?: string;
  allowStacking: boolean;
  currency: StoreSettings["currency"];
  appliedCoupons: AppliedCouponInfo[];
  couponDiscount: number;
  onApplied$: QRL<(coupons: AppliedCouponInfo[], discount: number) => void>;
}

/** Cart/checkout promo code apply and remove (single or stacked). */
export const CouponField = component$<CouponFieldProps>((props) => {
  const { locale } = useI18n();
  const inputCode = useSignal("");
  const applying = useSignal(false);
  const error = useSignal<string | null>(null);

  const syncApplied$ = $(async (coupons: AppliedCouponInfo[], discount: number) => {
    persistAppliedCoupons(
      coupons.map((coupon) => ({ code: coupon.code, label: coupon.label })),
    );
    await props.onApplied$(coupons, discount);
  });

  const applyCode$ = $(async () => {
    const code = inputCode.value.trim();
    if (!code || props.items.length === 0) {
      return;
    }

    if (!props.allowStacking && props.appliedCoupons.length > 0) {
      error.value = tStatic(locale, "coupon.singleOnly");
      return;
    }

    if (props.appliedCoupons.some((coupon) => coupon.code.toUpperCase() === code.toUpperCase())) {
      error.value = tStatic(locale, "coupon.alreadyApplied");
      return;
    }

    applying.value = true;
    error.value = null;
    try {
      const existingCodes = props.appliedCoupons.map((coupon) => coupon.code);
      const { data } = await validateCoupon(
        {
          code,
          coupon_codes: props.allowStacking && existingCodes.length > 0 ? existingCodes : undefined,
          location_id: props.locationId,
          items: props.items.map((line) => ({
            variation_id: line.variationId,
            quantity: line.quantity,
          })),
        },
        props.token,
      );

      const nextCoupons = props.allowStacking
        ? (data.coupons ?? (data.coupon ? [data.coupon] : []))
        : data.coupon
          ? [data.coupon]
          : [];

      if (nextCoupons.length === 0) {
        error.value = tStatic(locale, "coupon.invalid");
        await syncApplied$(props.allowStacking ? props.appliedCoupons : [], 0);
        return;
      }

      inputCode.value = "";
      await syncApplied$(nextCoupons, data.coupon_discount ?? 0);
    } catch (err) {
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

  const removeCode$ = $(async (codeToRemove?: string) => {
    inputCode.value = "";
    error.value = null;

    if (!codeToRemove || !props.allowStacking) {
      await syncApplied$([], 0);
      return;
    }

    const remaining = props.appliedCoupons.filter((coupon) => coupon.code !== codeToRemove);
    if (remaining.length === 0) {
      await syncApplied$([], 0);
      return;
    }

    applying.value = true;
    try {
      const { data } = await validateCoupon(
        {
          code: remaining[0].code,
          coupon_codes: remaining.length > 1 ? remaining.slice(1).map((coupon) => coupon.code) : undefined,
          location_id: props.locationId,
          items: props.items.map((line) => ({
            variation_id: line.variationId,
            quantity: line.quantity,
          })),
        },
        props.token,
      );
      const nextCoupons = data.coupons ?? (data.coupon ? [data.coupon] : []);
      await syncApplied$(nextCoupons, data.coupon_discount ?? 0);
    } catch (err) {
      await syncApplied$([], 0);
      if (err instanceof ApiError) {
        const messages = Object.values(err.errors).flat();
        error.value = messages.length ? messages.join(" ") : err.message;
      }
    } finally {
      applying.value = false;
    }
  });

  const canAddMore = props.allowStacking || props.appliedCoupons.length === 0;

  return (
    <div class="coupon-field">
      <label for="coupon-code">{tStatic(locale, "coupon.label")}</label>
      {props.appliedCoupons.length > 0 ? (
        <ul class="coupon-field__list" style={{ listStyle: "none", margin: "0 0 0.75rem", padding: 0 }}>
          {props.appliedCoupons.map((coupon) => (
            <li
              key={coupon.code}
              class="coupon-field__applied"
              style={{ display: "flex", justifyContent: "space-between", gap: "0.5rem", marginBottom: "0.35rem" }}
            >
              <span class="footer-muted" role="status">
                {tStatic(locale, "coupon.applied", {
                  code: coupon.code,
                  label: coupon.label,
                })}
              </span>
              <button
                type="button"
                class="btn btn-secondary"
                style={{ padding: "0.25rem 0.5rem", fontSize: "0.75rem" }}
                onClick$={() => removeCode$(coupon.code)}
              >
                {tStatic(locale, "coupon.remove")}
              </button>
            </li>
          ))}
        </ul>
      ) : null}
      {canAddMore ? (
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
            disabled={applying.value}
          />
          <button
            type="button"
            class="btn btn-secondary"
            disabled={applying.value || inputCode.value.trim() === ""}
            onClick$={applyCode$}
          >
            {applying.value ? tStatic(locale, "coupon.applying") : tStatic(locale, "coupon.apply")}
          </button>
        </div>
      ) : null}
      {props.couponDiscount > 0 ? (
        <p class="footer-muted coupon-field__applied" role="status">
          {tStatic(locale, "coupon.totalDiscount")}: −
          {formatPrice(props.couponDiscount, props.currency, locale)}
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
