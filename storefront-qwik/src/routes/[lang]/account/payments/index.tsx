import { $, component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import { RewardPointsSummary } from "~/components/account/reward-points-summary";
import {
  ApiError,
  fetchAccountCoupons,
  fetchOrders,
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
import type {
  AccountOrder,
  RewardPointsBalance,
  SavedCoupon,
  UsedCoupon,
} from "~/lib/types";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

type HubTab = "methods" | "payments" | "credits";
type PayFilter = "" | "due" | "paid" | "pending" | "failed";
type CouponSub = "add" | "unused" | "used";

export default component$(() => {
  const auth = useAuth();
  const settings = useSiteSettings();
  const { locale } = useI18n();
  const pending = usePendingState();
  const hub = useSignal<HubTab>("methods");
  const payFilter = useSignal<PayFilter>("");
  const couponSub = useSignal<CouponSub>("add");
  const code = useSignal("");
  const saving = useSignal(false);
  const ordersLoading = useSignal(false);
  const creditsLoading = useSignal(false);
  const orders = useStore<{ items: AccountOrder[] }>({ items: [] });
  const unused = useStore<{ items: SavedCoupon[] }>({ items: [] });
  const used = useStore<{ items: UsedCoupon[] }>({ items: [] });
  const rewardBalance = useSignal<RewardPointsBalance | null>(null);

  const loadOrders$ = $(async () => {
    if (!auth.token) return;
    ordersLoading.value = true;
    try {
      const { data } = await fetchOrders(auth.token, {
        perPage: 50,
        paymentStatus: payFilter.value || undefined,
      });
      orders.items = data || [];
    } catch {
      await toastError(tStatic(locale, "account.loadOrdersFailed"));
    } finally {
      ordersLoading.value = false;
    }
  });

  const loadCredits$ = $(async () => {
    if (!auth.token) return;
    creditsLoading.value = true;
    try {
      const [rp, unusedRes, usedRes] = await Promise.all([
        fetchRewardPoints(auth.token).catch(() => ({ data: null as RewardPointsBalance | null })),
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
    track(() => hub.value);
    track(() => payFilter.value);
    if (!auth.token) return;
    if (hub.value === "payments") {
      await loadOrders$();
    }
    if (hub.value === "credits") {
      await loadCredits$();
    }
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

  const hubTabs: { id: HubTab; label: string }[] = [
    { id: "methods", label: tStatic(locale, "account.paymentMethods") },
    { id: "payments", label: tStatic(locale, "account.paymentsTab") },
    { id: "credits", label: tStatic(locale, "account.creditsCoupons") },
  ];
  const payTabs: { id: PayFilter; label: string }[] = [
    { id: "", label: tStatic(locale, "account.payAll") },
    { id: "due", label: tStatic(locale, "account.payDue") },
    { id: "paid", label: tStatic(locale, "account.payPaid") },
    { id: "pending", label: tStatic(locale, "account.payPending") },
    { id: "failed", label: tStatic(locale, "account.payFailed") },
  ];
  const couponTabs: { id: CouponSub; label: string }[] = [
    { id: "add", label: tStatic(locale, "account.couponAdd") },
    { id: "unused", label: tStatic(locale, "account.couponUnused") },
    { id: "used", label: tStatic(locale, "account.couponUsed") },
  ];

  return (
    <div>
      <h1 class="page-title">{tStatic(locale, "account.paymentsPayouts")}</h1>

      <div class="account-hub-tabs" role="tablist">
        {hubTabs.map((tab) => (
          <button
            key={tab.id}
            type="button"
            class={hub.value === tab.id ? "account-hub-tab active" : "account-hub-tab"}
            onClick$={() => (hub.value = tab.id)}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {hub.value === "methods" ? (
        <div>
          <button
            type="button"
            class="btn btn-primary"
            onClick$={() => toastSuccess(tStatic(locale, "account.paymentMethodsSoon"))}
          >
            {tStatic(locale, "account.addPaymentMethod")}
          </button>
          <p class="empty-state">{tStatic(locale, "account.paymentMethodsEmpty")}</p>
        </div>
      ) : null}

      {hub.value === "payments" ? (
        <div>
          <div class="account-hub-tabs account-hub-tabs--sub">
            {payTabs.map((tab) => (
              <button
                key={tab.id || "all"}
                type="button"
                class={payFilter.value === tab.id ? "account-hub-tab active" : "account-hub-tab"}
                onClick$={() => (payFilter.value = tab.id)}
              >
                {tab.label}
              </button>
            ))}
          </div>
          {ordersLoading.value ? (
            <p class="footer-muted">{tStatic(locale, "account.loadingOrders")}</p>
          ) : null}
          {!ordersLoading.value && orders.items.length === 0 ? (
            <p class="empty-state">{tStatic(locale, "account.noOrders")}</p>
          ) : null}
          {orders.items.length > 0 ? (
            <div class="table-wrap">
              <table class="account-table">
                <thead>
                  <tr>
                    <th>{tStatic(locale, "account.order")}</th>
                    <th>{tStatic(locale, "account.payment")}</th>
                    <th>{tStatic(locale, "account.total")}</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {orders.items.map((order) => (
                    <tr key={order.id}>
                      <td>{order.invoice_no || order.storefront_order_id}</td>
                      <td>{order.payment_status}</td>
                      <td>{formatPrice(order.final_total, settings.value.currency, locale)}</td>
                      <td>
                        <Link
                          href={localePath(locale, `/account/orders/${order.id}`)}
                          class="link-accent"
                        >
                          {tStatic(locale, "account.view")}
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : null}
        </div>
      ) : null}

      {hub.value === "credits" ? (
        <div>
          {rewardBalance.value?.enabled ? (
            <div style={{ marginBottom: "1.5rem" }}>
              <RewardPointsSummary
                balance={rewardBalance.value}
                currency={settings.value.currency}
              />
            </div>
          ) : null}

          <div class="account-hub-tabs account-hub-tabs--sub">
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
      ) : null}
    </div>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const lang = resolveValue(useLangParam);
  return {
    title: tStatic(lang, "account.paymentsPayouts"),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
