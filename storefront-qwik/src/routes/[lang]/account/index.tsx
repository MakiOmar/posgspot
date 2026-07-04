import { component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import { CustomerQrCode } from "~/components/account/customer-qr-code";
import { RewardPointsSummary } from "~/components/account/reward-points-summary";
import { fetchRewardPoints } from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";
import type { RewardPointsBalance } from "~/lib/types";

export default component$(() => {
  const auth = useAuth();
  const settings = useSiteSettings();
  const { locale } = useI18n();
  const c = auth.contact;
  const rewardBalance = useSignal<RewardPointsBalance | null>(null);

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => auth.token);
    track(() => settings.value.reward_points.enabled);

    if (!auth.token || !settings.value.reward_points.enabled) {
      rewardBalance.value = null;
      return;
    }

    try {
      const { data } = await fetchRewardPoints(auth.token);
      rewardBalance.value = data;
    } catch {
      rewardBalance.value = null;
    }
  });

  return (
    <div>
      <h1 class="page-title">{tStatic(locale, "account.dashboard")}</h1>
      <p class="footer-muted" style={{ marginBottom: "1.5rem" }}>
        {tStatic(locale, "account.intro")}
      </p>

      {rewardBalance.value?.enabled ? (
        <div style={{ marginBottom: "1.5rem" }}>
          <RewardPointsSummary balance={rewardBalance.value} currency={settings.value.currency} />
        </div>
      ) : null}

      <div class="account-cards">
        <Link href={localePath(locale, "/account/orders")} class="account-card">
          <strong>{tStatic(locale, "account.orders")}</strong>
          <span class="footer-muted">{tStatic(locale, "account.ordersCardDesc")}</span>
        </Link>
        <Link href={localePath(locale, "/account/profile")} class="account-card">
          <strong>{tStatic(locale, "account.profileAddress")}</strong>
          <span class="footer-muted">{tStatic(locale, "account.profileCardDesc")}</span>
        </Link>
      </div>

      {c ? (
        <div class="account-overview-grid">
          <div class="account-summary">
            <h2>{tStatic(locale, "account.yourDetails")}</h2>
            <dl class="account-detail-list">
              <div>
                <dt>{tStatic(locale, "forms.name")}</dt>
                <dd>{c.name || "—"}</dd>
              </div>
              <div>
                <dt>{tStatic(locale, "forms.email")}</dt>
                <dd>{c.email || "—"}</dd>
              </div>
              <div>
                <dt>{tStatic(locale, "forms.mobile")}</dt>
                <dd>{c.mobile || "—"}</dd>
              </div>
            </dl>
          </div>
          <CustomerQrCode name={c.name} email={c.email} mobile={c.mobile} />
        </div>
      ) : null}
    </div>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const lang = resolveValue(useLangParam);
  return {
    title: tStatic(lang, "account.dashboard"),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
