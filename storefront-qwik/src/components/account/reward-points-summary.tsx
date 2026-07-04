import { component$ } from "@builder.io/qwik";
import { formatNumber, formatPrice } from "~/lib/format";
import { tStatic, useI18n } from "~/lib/i18n/context";
import type { RewardPointsBalance, StoreSettings } from "~/lib/types";

interface Props {
  balance: RewardPointsBalance;
  currency: StoreSettings["currency"];
}

/** Account overview: available / used / expired points and monetary value. */
export const RewardPointsSummary = component$<Props>(({ balance, currency }) => {
  const { locale } = useI18n();

  if (!balance.enabled) {
    return null;
  }

  const title = balance.name || tStatic(locale, "rewards.defaultName");
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
          {tStatic(locale, "rewards.negativeBalance")}
        </p>
      ) : null}
      <div class="reward-points-card__grid">
        <div class={`reward-points-card__cell${negativeBalance ? " reward-points-card__cell--negative" : ""}`}>
          <span class="reward-points-card__label">{tStatic(locale, "rewards.available")}</span>{" "}
          <strong>{formatNumber(available, locale)}</strong>
        </div>
        <div class="reward-points-card__cell">
          <span class="reward-points-card__label">{tStatic(locale, "rewards.used")}</span>{" "}
          <strong>{formatNumber(used, locale)}</strong>
        </div>
        <div class="reward-points-card__cell">
          <span class="reward-points-card__label">{tStatic(locale, "rewards.expired")}</span>{" "}
          <strong>{formatNumber(expired, locale)}</strong>
        </div>
        <div class={`reward-points-card__cell reward-points-card__cell--value${negativeBalance ? " reward-points-card__cell--negative" : ""}`}>
          <span class="reward-points-card__label">{tStatic(locale, "rewards.value")}</span>{" "}
          <strong>{formatPrice(value, currency, locale)}</strong>
        </div>
      </div>
    </section>
  );
});
