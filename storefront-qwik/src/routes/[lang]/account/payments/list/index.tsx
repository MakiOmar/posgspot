import { component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import { fetchOrders } from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { formatPrice } from "~/lib/format";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError } from "~/lib/notify";
import type { AccountOrder } from "~/lib/types";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

type PayFilter = "all" | "due" | "paid" | "pending" | "failed";

export default component$(() => {
  const auth = useAuth();
  const settings = useSiteSettings();
  const { locale } = useI18n();
  const payFilter = useSignal<PayFilter>("all");
  const ordersLoading = useSignal(false);
  const orders = useStore<{ items: AccountOrder[] }>({ items: [] });

  const payTabs: { id: PayFilter; label: string }[] = [
    { id: "all", label: tStatic(locale, "account.payAll") },
    { id: "due", label: tStatic(locale, "account.payDue") },
    { id: "paid", label: tStatic(locale, "account.payPaid") },
    { id: "pending", label: tStatic(locale, "account.payPending") },
    { id: "failed", label: tStatic(locale, "account.payFailed") },
  ];

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => auth.token);
    track(() => payFilter.value);
    if (!auth.token) return;
    ordersLoading.value = true;
    try {
      const { data } = await fetchOrders(auth.token, {
        perPage: 50,
        paymentStatus: payFilter.value === "all" ? undefined : payFilter.value,
      });
      orders.items = data || [];
    } catch {
      await toastError(tStatic(locale, "account.loadOrdersFailed"));
    } finally {
      ordersLoading.value = false;
    }
  });

  return (
    <div>
      <p class="footer-muted" style={{ marginBottom: "1rem" }}>
        <Link href={localePath(locale, "/account/payments")} class="link-accent">
          {tStatic(locale, "account.paymentsPayouts")}
        </Link>
      </p>
      <h1 class="page-title">{tStatic(locale, "account.paymentsTab")}</h1>

      <div class="account-hub-tabs" role="tablist">
        {payTabs.map((tab) => (
          <button
            key={tab.id}
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
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const lang = resolveValue(useLangParam);
  return {
    title: tStatic(lang, "account.paymentsTab"),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
