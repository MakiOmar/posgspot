import { component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import { fetchOrders } from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { formatPrice } from "~/lib/format";
import { useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError } from "~/lib/notify";
import type { AccountOrder } from "~/lib/types";
import { useSiteSettings } from "~/routes/layout";

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
      await toastError("Could not load your orders. Please try again.");
    } finally {
      loading.value = false;
    }
  });

  return (
    <div>
      <h1 class="page-title">Orders</h1>

      {loading.value ? <p class="footer-muted">Loading orders…</p> : null}

      {!loading.value && state.orders.length === 0 ? (
        <div class="empty-state">
          You have no orders yet. <Link href={localePath(locale, "/products")}>Start shopping</Link>.
        </div>
      ) : null}

      {state.orders.length > 0 ? (
        <div class="table-wrap">
          <table class="account-table">
            <thead>
              <tr>
                <th>Order</th>
                <th>Date</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Total</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {state.orders.map((order) => (
                <tr key={order.id}>
                  <td data-label="Order">{order.invoice_no || order.storefront_order_id}</td>
                  <td data-label="Date">{formatDate(order.transaction_date)}</td>
                  <td data-label="Status">
                    <span class="status-pill">{order.shipping_status || order.status}</span>
                  </td>
                  <td data-label="Payment">{order.payment_status}</td>
                  <td data-label="Total">{formatPrice(order.final_total, settings.value.currency)}</td>
                  <td>
                    <Link href={`/account/orders/${order.id}`} class="link-accent">
                      View
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

function formatDate(value: string): string {
  const d = new Date(value);
  return Number.isNaN(d.getTime()) ? value : d.toLocaleDateString();
}

export const head: DocumentHead = {
  title: "My orders",
  meta: [{ name: "robots", content: "noindex, nofollow" }],
};
