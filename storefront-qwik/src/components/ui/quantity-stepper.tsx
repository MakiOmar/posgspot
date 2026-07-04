import { component$, type QRL } from "@builder.io/qwik";
import { MinusIcon, PlusIcon } from "~/components/icons";
import { tStatic, useI18n } from "~/lib/i18n/context";

export interface QuantityStepperProps {
  value: number;
  min?: number;
  max?: number;
  disabled?: boolean;
  /** Accessible name for the stepper (e.g. "Quantity"). */
  label?: string;
  onChange$: QRL<(value: number) => void>;
}

/** Clamp quantity to min/max and strip invalid input. */
function clampQuantity(value: number, min: number, max?: number): number {
  let next = Math.max(min, Math.floor(value) || min);
  if (max != null && max > 0) {
    next = Math.min(max, next);
  }
  return next;
}

/**
 * Fancy +/- quantity control for cart lines and product detail.
 * See storefront-quantity-stepper.mdc.
 */
export const QuantityStepper = component$<QuantityStepperProps>(
  ({ value, min = 1, max, disabled = false, label, onChange$ }) => {
    const { locale } = useI18n();
    const groupLabel = label || tStatic(locale, "a11y.quantity");
    const atMin = value <= min;
    const atMax = max != null && max > 0 && value >= max;

    return (
      <div class="qty-stepper" role="group" aria-label={groupLabel}>
        <button
          type="button"
          class="qty-stepper-btn"
          aria-label={tStatic(locale, "a11y.decreaseQty")}
          disabled={disabled || atMin}
          onClick$={async () => {
            if (disabled || atMin) {
              return;
            }
            await onChange$(clampQuantity(value - 1, min, max));
          }}
        >
          <MinusIcon size={16} />
        </button>
        <input
          type="number"
          class="qty-stepper-input"
          min={min}
          max={max}
          value={value}
          disabled={disabled}
          aria-label={groupLabel}
          inputMode="numeric"
          onInput$={async (event) => {
            const raw = Number((event.target as HTMLInputElement).value);
            await onChange$(clampQuantity(raw, min, max));
          }}
        />
        <button
          type="button"
          class="qty-stepper-btn"
          aria-label={tStatic(locale, "a11y.increaseQty")}
          disabled={disabled || atMax}
          onClick$={async () => {
            if (disabled || atMax) {
              return;
            }
            await onChange$(clampQuantity(value + 1, min, max));
          }}
        >
          <PlusIcon size={16} />
        </button>
      </div>
    );
  },
);
