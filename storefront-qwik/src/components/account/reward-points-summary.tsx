import { component$ } from "@builder.io/qwik";
import { formatNumber, formatPrice } from "~/lib/format";
import type { RewardPointsBalance, StoreSettings } from "~/lib/types";

interface Props {
  balance: RewardPointsBalance;
  currency: StoreSettings["currency"];
}

/** Account overview: available / used / expired points and monetary value. */
export const RewardPointsSummary = component$<Props>(({ balance, currency }) => {
  if (!balance.enabled) {
    return null;
  }

  const title = balance.name || "Reward Points";
  const available = balance.available ?? 0;
  const used = balance.used ?? 0;
  const expired = balance.expired ?? 0;
  const value = balance.value ?? 0;
  const negativeBalance = available < 0;

  return (
    <section class="reward-points-card" aria-label={title}>
      <h2 class="reward-points-card__title">{title}</h2>
      {negativeBalance ? (
        <p class="alert alert-error" style={{ marginBottom: "1rem", fontSize: "0.875rem" }}>
          Your balance is negative. Points cannot be redeemed until this is corrected in store.
        </p>
      ) : null}
      <div class="reward-points-card__grid">
        <div class={`reward-points-card__cell${negativeBalance ? " reward-points-card__cell--negative" : ""}`}>
          <span class="reward-points-card__label">Available:</span>{" "}
          <strong>{formatNumber(available)}</strong>
        </div>
        <div class="reward-points-card__cell">
          <span class="reward-points-card__label">Used:</span>{" "}
          <strong>{formatNumber(used)}</strong>
        </div>
        <div class="reward-points-card__cell">
          <span class="reward-points-card__label">Expired:</span>{" "}
          <strong>{formatNumber(expired)}</strong>
        </div>
        <div class={`reward-points-card__cell reward-points-card__cell--value${negativeBalance ? " reward-points-card__cell--negative" : ""}`}>
          <span class="reward-points-card__label">Value:</span>{" "}
          <strong>{formatPrice(value, currency)}</strong>
        </div>
      </div>
    </section>
  );
});
