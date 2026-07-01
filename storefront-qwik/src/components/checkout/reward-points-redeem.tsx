import { $, component$, useSignal, useVisibleTask$, type QRL } from "@builder.io/qwik";
import { validateRewardPoints } from "~/lib/api";
import { formatNumber, formatPrice } from "~/lib/format";
import type { RewardPointsBalance, StoreSettings } from "~/lib/types";

interface RewardPointsRedeemProps {
  token: string;
  balance: RewardPointsBalance;
  orderTotal: number;
  currency: StoreSettings["currency"];
  pointsToRedeem: number;
  onPointsChange$: QRL<(points: number, redeemAmount: number, isValid: boolean) => void>;
}

/** Checkout: redeem reward points against the order total. */
export const RewardPointsRedeem = component$<RewardPointsRedeemProps>((props) => {
  const inputPoints = useSignal(props.pointsToRedeem > 0 ? String(props.pointsToRedeem) : "");
  const validating = useSignal(false);
  const error = useSignal<string | null>(null);
  const redeemAmount = useSignal(0);
  const maxPoints = props.balance.max_redeem_points ?? 0;

  const runValidation$ = $(async (points: number) => {
    validating.value = true;
    error.value = null;
    try {
      const { data } = await validateRewardPoints(props.token, {
        requested_points: points,
        order_total: props.orderTotal,
      });
      redeemAmount.value = data.redeem_amount;
      if (!data.is_valid && data.message) {
        error.value = data.message;
      }
      await props.onPointsChange$(data.requested_points, data.redeem_amount, data.is_valid);
    } catch {
      error.value = "Could not validate reward points. Please try again.";
      redeemAmount.value = 0;
      await props.onPointsChange$(0, 0, false);
    } finally {
      validating.value = false;
    }
  });

  // Re-validate when cart total changes.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => props.orderTotal);
    const points = parseInt(inputPoints.value, 10) || 0;
    void runValidation$(points);
  });

  const onInput$ = $(async (_: Event, el: HTMLInputElement) => {
    inputPoints.value = el.value.replace(/\D/g, "");
    const points = parseInt(inputPoints.value, 10) || 0;
    await runValidation$(points);
  });

  const useMax$ = $(async () => {
    inputPoints.value = String(maxPoints);
    await runValidation$(maxPoints);
  });

  const clear$ = $(async () => {
    inputPoints.value = "";
    await runValidation$(0);
  });

  if (!props.balance.enabled || maxPoints <= 0) {
    return null;
  }

  const title = props.balance.name || "Reward Points";

  return (
    <div class="reward-points-redeem">
      <h3 class="reward-points-redeem__title">{title}</h3>
      <p class="footer-muted reward-points-redeem__hint">
        You have <strong>{formatNumber(props.balance.available ?? 0)}</strong> points available
        {maxPoints > 0 ? (
          <>
            {" "}
            (up to <strong>{formatNumber(maxPoints)}</strong> redeemable on this order)
          </>
        ) : null}
        .
      </p>
      <div class="reward-points-redeem__row">
        <label class="sr-only" for="reward_points">
          Points to redeem
        </label>
        <input
          id="reward_points"
          type="text"
          inputMode="numeric"
          pattern="[0-9]*"
          placeholder="0"
          value={inputPoints.value}
          onInput$={onInput$}
          disabled={validating.value}
        />
        <button type="button" class="btn btn-secondary" onClick$={useMax$} disabled={validating.value}>
          Use max
        </button>
        {inputPoints.value ? (
          <button type="button" class="btn btn-secondary" onClick$={clear$} disabled={validating.value}>
            Clear
          </button>
        ) : null}
      </div>
      {validating.value ? (
        <p class="footer-muted">Checking points…</p>
      ) : null}
      {error.value ? <p class="alert alert-error">{error.value}</p> : null}
      {redeemAmount.value > 0 && !error.value ? (
        <p class="reward-points-redeem__savings">
          Discount: <strong>{formatPrice(redeemAmount.value, props.currency)}</strong>
        </p>
      ) : null}
    </div>
  );
});
