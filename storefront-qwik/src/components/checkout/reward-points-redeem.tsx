import { $, component$, useSignal, useVisibleTask$, type QRL } from "@builder.io/qwik";
import { validateRewardPoints } from "~/lib/api";
import { formatNumber, formatPrice } from "~/lib/format";
import { tStatic, useI18n } from "~/lib/i18n/context";
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
  const { locale } = useI18n();
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
      error.value = tStatic(locale, "rewards.validateError");
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

  const title = props.balance.name || tStatic(locale, "rewards.defaultName");

  return (
    <div class="reward-points-redeem">
      <h3 class="reward-points-redeem__title">{title}</h3>
      <p class="footer-muted reward-points-redeem__hint">
        {tStatic(locale, "rewards.hint", {
          available: formatNumber(props.balance.available ?? 0, locale),
        })}
        {maxPoints > 0
          ? tStatic(locale, "rewards.hintMax", { max: formatNumber(maxPoints, locale) })
          : null}
        .
      </p>
      <div class="reward-points-redeem__row">
        <label class="sr-only" for="reward_points">
          {tStatic(locale, "rewards.pointsToRedeem")}
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
          {tStatic(locale, "rewards.useMax")}
        </button>
        {inputPoints.value ? (
          <button type="button" class="btn btn-secondary" onClick$={clear$} disabled={validating.value}>
            {tStatic(locale, "rewards.clear")}
          </button>
        ) : null}
      </div>
      {validating.value ? (
        <p class="footer-muted">{tStatic(locale, "rewards.checking")}</p>
      ) : null}
      {error.value ? <p class="alert alert-error">{error.value}</p> : null}
      {redeemAmount.value > 0 && !error.value ? (
        <p class="reward-points-redeem__savings">
          {tStatic(locale, "rewards.discount")}{" "}
          <strong>{formatPrice(redeemAmount.value, props.currency, locale)}</strong>
        </p>
      ) : null}
    </div>
  );
});
