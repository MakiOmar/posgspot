import { $, component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import { RewardPointsSummary } from "~/components/account/reward-points-summary";
import {
  ApiError,
  fetchAccountCoupons,
  fetchRewardPoints,
  fetchUsedAccountCoupons,
  saveAccountCoupon,
} from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { formatPrice } from "~/lib/format";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError, toastSuccess } from "~/lib/notify";
import { usePendingState } from "~/lib/pending-context";
import { withPendingFeedback } from "~/lib/with-pending";
import type { RewardPointsBalance, SavedCoupon, UsedCoupon } from "~/lib/types";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

type CouponSub = "add" | "unused" | "used";

export default component$(() => {
  const auth = useAuth();
  const settings = useSiteSettings();
  const { locale } = useI18n();
  const pending = usePendingState();
  const couponSub = useSignal<CouponSub>("add");
  const code = useSignal("");
  const saving = useSignal(false);
  const creditsLoading = useSignal(false);
  const unused = useStore<{ items: SavedCoupon[] }>({ items: [] });
  const used = useStore<{ items: UsedCoupon[] }>({ items: [] });
  const rewardBalance = useSignal<RewardPointsBalance | null>(null);

  const couponTabs: { id: CouponSub; label: string }[] = [
    { id: "add", label: tStatic(locale, "account.couponAdd") },
    { id: "unused", label: tStatic(locale, "account.couponUnused") },
    { id: "used", label: tStatic(locale, "account.couponUsed") },
  ];

  const loadCredits$ = $(async () => {
    if (!auth.token) return;
    creditsLoading.value = true;
    try {
      const [rp, unusedRes, usedRes] = await Promise.all([
        fetchRewardPoints(auth.token).catch(() => ({
          data: null as RewardPointsBalance | null,
        })),
        fetchAccountCoupons(auth.token),
        fetchUsedAccountCoupons(auth.token),
      ]);
      rewardBalance.value = rp.data;
      unused.items = unusedRes.data || [];
      used.items = usedRes.data || [];
    } catch {
      await toastError(tStatic(locale, "account.loadOrdersFailed"));
    } finally {
      creditsLoading.value = false;
    }
  });

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => auth.token);
    if (!auth.token) return;
    await loadCredits$();
  });

  const saveCoupon$ = $(async () => {
    const trimmed = code.value.trim();
    if (!trimmed || !auth.token) return;
    await withPendingFeedback(pending, saving, async () => {
      try {
        await saveAccountCoupon(auth.token as string, trimmed);
        code.value = "";
        couponSub.value = "unused";
        await toastSuccess(tStatic(locale, "account.couponSaved"));
        await loadCredits$();
      } catch (e) {
        await toastError(
          e instanceof ApiError ? e.message : tStatic(locale, "account.saveFailed"),
        );
      }
    });
  });

  return (
    <div>
      <p class="footer-muted" style={{ marginBottom: "1rem" }}>
        <Link href={localePath(locale, "/account/payments")} class="link-accent">
          {tStatic(locale, "account.paymentsPayouts")}
        </Link>
      </p>
      <h1 class="page-title">{tStatic(locale, "account.creditsCoupons")}</h1>

      {rewardBalance.value?.enabled ? (
        <div style={{ marginBottom: "1.5rem" }}>
          <RewardPointsSummary
            balance={rewardBalance.value}
            currency={settings.value.currency}
          />
        </div>
      ) : null}

      <div class="account-hub-tabs" role="tablist">
        {couponTabs.map((tab) => (
          <button
            key={tab.id}
            type="button"
            class={couponSub.value === tab.id ? "account-hub-tab active" : "account-hub-tab"}
            onClick$={() => (couponSub.value = tab.id)}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {creditsLoading.value ? (
        <p class="footer-muted">{tStatic(locale, "common.loading")}</p>
      ) : null}

      {couponSub.value === "add" ? (
        <form class="account-form" preventdefault:submit onSubmit$={saveCoupon$}>
          <label for="wallet_code">{tStatic(locale, "account.couponCode")}</label>
          <input
            id="wallet_code"
            value={code.value}
            onInput$={(_, el) => (code.value = el.value)}
            autoCapitalize="characters"
          />
          <button type="submit" class="btn btn-primary" disabled={saving.value}>
            {saving.value
              ? tStatic(locale, "common.loading")
              : tStatic(locale, "account.couponSave")}
          </button>
        </form>
      ) : null}

      {couponSub.value === "unused" && unused.items.length === 0 && !creditsLoading.value ? (
        <p class="empty-state">{tStatic(locale, "account.couponEmptyUnused")}</p>
      ) : null}
      {couponSub.value === "unused"
        ? unused.items.map((item) => (
            <article key={item.id} class="account-card" style={{ marginBottom: "0.75rem" }}>
              <strong>{item.code}</strong>
              <span class="footer-muted">{item.label || item.name || ""}</span>
            </article>
          ))
        : null}

      {couponSub.value === "used" && used.items.length === 0 && !creditsLoading.value ? (
        <p class="empty-state">{tStatic(locale, "account.couponEmptyUsed")}</p>
      ) : null}
      {couponSub.value === "used"
        ? used.items.map((item) => (
            <article key={item.id} class="account-card" style={{ marginBottom: "0.75rem" }}>
              <strong>{item.code || "—"}</strong>
              <span class="footer-muted">
                {tStatic(locale, "account.couponUsedOn")} #{item.order_id}
                {item.discount_amount != null
                  ? ` · ${tStatic(locale, "account.couponSavedAmount")} ${formatPrice(item.discount_amount, settings.value.currency, locale)}`
                  : ""}
              </span>
              {item.order_id ? (
                <Link
                  href={localePath(locale, `/account/orders/${item.order_id}`)}
                  class="link-accent"
                >
                  {tStatic(locale, "account.view")}
                </Link>
              ) : null}
            </article>
          ))
        : null}
    </div>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const lang = resolveValue(useLangParam);
  return {
    title: tStatic(lang, "account.creditsCoupons"),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
