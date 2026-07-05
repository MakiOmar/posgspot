import { $, component$, useSignal, useTask$, type QRL } from "@builder.io/qwik";
import { ApiError, fetchAvailableCoupons } from "~/lib/api";
import { formatPrice } from "~/lib/format";
import { tStatic, useI18n } from "~/lib/i18n/context";
import type { AppliedCouponInfo, AvailableCouponInfo, CartItem, StoreSettings } from "~/lib/types";

interface CouponPickerProps {
  items: CartItem[];
  token?: string;
  allowStacking: boolean;
  currency: StoreSettings["currency"];
  appliedCoupons: AppliedCouponInfo[];
  applying: boolean;
  onSelect$: QRL<(code: string) => void>;
}

/** Fancy selectable list of eligible promo codes for the current cart. */
export const CouponPicker = component$<CouponPickerProps>((props) => {
  const { locale } = useI18n();
  const open = useSignal(false);
  const loading = useSignal(false);
  const loadError = useSignal<string | null>(null);
  const available = useSignal<AvailableCouponInfo[]>([]);
  const selectingCode = useSignal<string | null>(null);

  const itemsKey = props.items.map((line) => `${line.variationId}:${line.quantity}`).join("|");
  const appliedKey = props.appliedCoupons.map((coupon) => coupon.code).join("|");

  const loadAvailable$ = $(async () => {
    if (props.items.length === 0 || !props.token) {
      available.value = [];
      return;
    }

    loading.value = true;
    loadError.value = null;
    try {
      const excludeCodes = props.allowStacking ? props.appliedCoupons.map((coupon) => coupon.code) : [];
      const { data } = await fetchAvailableCoupons(
        {
          items: props.items.map((line) => ({
            variation_id: line.variationId,
            quantity: line.quantity,
          })),
          exclude_codes: excludeCodes.length > 0 ? excludeCodes : undefined,
        },
        props.token,
      );
      available.value = data.coupons ?? [];
    } catch (err) {
      available.value = [];
      if (err instanceof ApiError) {
        const messages = Object.values(err.errors).flat();
        loadError.value = messages.length ? messages.join(" ") : err.message;
      } else {
        loadError.value = tStatic(locale, "coupon.pickerLoadError");
      }
    } finally {
      loading.value = false;
    }
  });

  useTask$(({ track }) => {
    track(() => open.value);
    track(() => itemsKey);
    track(() => appliedKey);
    track(() => props.allowStacking);

    if (open.value) {
      loadAvailable$();
    }
  });

  const toggleOpen$ = $(() => {
    open.value = !open.value;
  });

  const selectCoupon$ = $(async (code: string) => {
    if (props.applying || selectingCode.value) {
      return;
    }
    selectingCode.value = code;
    try {
      await props.onSelect$(code);
      if (!props.allowStacking) {
        open.value = false;
      }
    } finally {
      selectingCode.value = null;
    }
  });

  const formatSavings = (coupon: AvailableCouponInfo) => {
    if (coupon.free_shipping && coupon.discount_amount <= 0) {
      return tStatic(locale, "coupon.pickerFreeShipping");
    }
    if (coupon.free_shipping && coupon.discount_amount > 0) {
      return tStatic(locale, "coupon.pickerSavePlusShipping", {
        amount: formatPrice(coupon.total_savings, props.currency, locale),
      });
    }
    return tStatic(locale, "coupon.pickerSave", {
      amount: formatPrice(coupon.total_savings, props.currency, locale),
    });
  };

  const canShowPicker =
    props.items.length > 0 && (props.allowStacking || props.appliedCoupons.length === 0);

  if (!canShowPicker) {
    return null;
  }

  return (
    <div class="coupon-picker">
      <button
        type="button"
        class={`coupon-picker__toggle${open.value ? " coupon-picker__toggle--open" : ""}`}
        aria-expanded={open.value}
        aria-controls="coupon-picker-panel"
        disabled={props.applying}
        onClick$={toggleOpen$}
      >
        <span class="coupon-picker__toggle-icon" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 14l6-6" />
            <circle cx="9.5" cy="8.5" r="1.5" fill="currentColor" stroke="none" />
            <path d="M4 15v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4" />
            <path d="M12 3 20 7v2H4V7z" />
          </svg>
        </span>
        <span class="coupon-picker__toggle-text">
          {open.value ? tStatic(locale, "coupon.pickerHide") : tStatic(locale, "coupon.pickerShow")}
        </span>
        <span class="coupon-picker__toggle-chevron" aria-hidden="true">
          {open.value ? "▴" : "▾"}
        </span>
      </button>

      {open.value ? (
        <div id="coupon-picker-panel" class="coupon-picker__panel" role="region" aria-label={tStatic(locale, "coupon.pickerRegion")}>
          {loading.value ? (
            <p class="coupon-picker__status footer-muted">{tStatic(locale, "coupon.pickerLoading")}</p>
          ) : loadError.value ? (
            <p class="alert alert-error coupon-picker__status" role="alert">
              {loadError.value}
            </p>
          ) : available.value.length === 0 ? (
            <p class="coupon-picker__status footer-muted">{tStatic(locale, "coupon.pickerEmpty")}</p>
          ) : (
            <ul class="coupon-picker__list">
              {available.value.map((coupon) => {
                const isApplied = props.appliedCoupons.some(
                  (applied) => applied.code.toUpperCase() === coupon.code.toUpperCase(),
                );
                const isSelecting = selectingCode.value === coupon.code;

                return (
                  <li key={coupon.id}>
                    <button
                      type="button"
                      class={`coupon-picker__card${isApplied ? " coupon-picker__card--applied" : ""}`}
                      disabled={props.applying || isApplied || isSelecting}
                      onClick$={() => selectCoupon$(coupon.code)}
                    >
                      <span class="coupon-picker__card-code">{coupon.code}</span>
                      <span class="coupon-picker__card-body">
                        <strong class="coupon-picker__card-name">{coupon.name}</strong>
                        <span class="coupon-picker__card-label footer-muted">{coupon.label}</span>
                        {coupon.description ? (
                          <span class="coupon-picker__card-desc footer-muted">{coupon.description}</span>
                        ) : null}
                      </span>
                      <span class="coupon-picker__card-savings">{formatSavings(coupon)}</span>
                      {isApplied ? (
                        <span class="coupon-picker__card-badge">{tStatic(locale, "coupon.pickerApplied")}</span>
                      ) : isSelecting ? (
                        <span class="coupon-picker__card-badge">{tStatic(locale, "coupon.applying")}</span>
                      ) : null}
                    </button>
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      ) : null}
    </div>
  );
});
