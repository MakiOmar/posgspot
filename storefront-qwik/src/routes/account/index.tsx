import { component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import { CustomerQrCode } from "~/components/account/customer-qr-code";
import { RewardPointsSummary } from "~/components/account/reward-points-summary";
import { fetchRewardPoints } from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { useSiteSettings } from "~/routes/layout";
import type { RewardPointsBalance } from "~/lib/types";

export default component$(() => {
  const auth = useAuth();
  const settings = useSiteSettings();
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
      <h1 class="page-title">My account</h1>
      <p class="footer-muted" style={{ marginBottom: "1.5rem" }}>
        Manage your orders, profile and delivery address.
      </p>

      {rewardBalance.value?.enabled ? (
        <div style={{ marginBottom: "1.5rem" }}>
          <RewardPointsSummary balance={rewardBalance.value} currency={settings.value.currency} />
        </div>
      ) : null}

      <div class="account-cards">
        <Link href="/account/orders" class="account-card">
          <strong>Orders</strong>
          <span class="footer-muted">View your order history and status</span>
        </Link>
        <Link href="/account/profile" class="account-card">
          <strong>Profile &amp; address</strong>
          <span class="footer-muted">Update your details and delivery address</span>
        </Link>
      </div>

      {c ? (
        <div class="account-overview-grid">
          <div class="account-summary">
            <h2>Your details</h2>
            <dl class="account-detail-list">
              <div>
                <dt>Name</dt>
                <dd>{c.name || "—"}</dd>
              </div>
              <div>
                <dt>Email</dt>
                <dd>{c.email || "—"}</dd>
              </div>
              <div>
                <dt>Mobile</dt>
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

export const head: DocumentHead = {
  title: "My account",
  meta: [{ name: "robots", content: "noindex, nofollow" }],
};
