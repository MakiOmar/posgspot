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

export default component$(() => {
  const auth = useAuth();
  const settings = useSiteSettings();
  const { locale } = useI18n();
  const state = useStore<{ orders: AccountOrder[] }>({ orders: [] });
  const loading = useSignal(true);

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => auth.token);
    if (!auth.token) {
      return;
    }
    loading.value = true;
    try {
      const { data } = await fetchOrders(auth.token);
      state.orders = data;
    } catch {
      await toastError(tStatic(locale, "account.loadOrdersFailed"));
    } finally {
      loading.value = false;
    }
  });

  return (
    <div>
      <h1 class="page-title">{tStatic(locale, "account.orders")}</h1>

      {loading.value ? <p class="footer-muted">{tStatic(locale, "account.loadingOrders")}</p> : null}

      {!loading.value && state.orders.length === 0 ? (
        <div class="empty-state">
          {tStatic(locale, "account.noOrders")}{" "}
          <Link href={localePath(locale, "/products")}>{tStatic(locale, "account.startShopping")}</Link>.
        </div>
      ) : null}

      {state.orders.length > 0 ? (
        <div class="table-wrap">
          <table class="account-table">
            <thead>
              <tr>
                <th>{tStatic(locale, "account.order")}</th>
                <th>{tStatic(locale, "account.date")}</th>
                <th>{tStatic(locale, "account.status")}</th>
                <th>{tStatic(locale, "account.payment")}</th>
                <th>{tStatic(locale, "account.total")}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {state.orders.map((order) => (
                <tr key={order.id}>
                  <td data-label={tStatic(locale, "account.order")}>
                    {order.invoice_no || order.storefront_order_id}
                  </td>
                  <td data-label={tStatic(locale, "account.date")}>
                    {formatDate(order.transaction_date, locale)}
                  </td>
                  <td data-label={tStatic(locale, "account.status")}>
                    <span class="status-pill">{order.shipping_status || order.status}</span>
                  </td>
                  <td data-label={tStatic(locale, "account.payment")}>{order.payment_status}</td>
                  <td data-label={tStatic(locale, "account.total")}>
                    {formatPrice(order.final_total, settings.value.currency, locale)}
                  </td>
                  <td>
                    <Link href={localePath(locale, `/account/orders/${order.id}`)} class="link-accent">
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

function formatDate(value: string, locale: string): string {
  const d = new Date(value);
  return Number.isNaN(d.getTime()) ? value : d.toLocaleDateString(locale === "ar" ? "ar-EG" : "en-EG");
}

export const head: DocumentHead = ({ resolveValue }) => {
  const lang = resolveValue(useLangParam);
  return {
    title: tStatic(lang, "account.myOrdersTitle"),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
