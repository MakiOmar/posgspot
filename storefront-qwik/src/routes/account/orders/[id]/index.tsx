import { component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { Link, useLocation, type DocumentHead } from "@builder.io/qwik-city";
import { ApiError, fetchOrder } from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { formatPrice } from "~/lib/format";
import type { AccountOrderDetail } from "~/lib/types";
import { useSiteSettings } from "~/routes/layout";

export default component$(() => {
  const auth = useAuth();
  const loc = useLocation();
  const settings = useSiteSettings();
  const state = useStore<{ order: AccountOrderDetail | null }>({ order: null });
  const loading = useSignal(true);
  const error = useSignal<string | null>(null);

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => auth.token);
    if (!auth.token) {
      return;
    }
    const orderId = Number(loc.params.id);
    if (!Number.isFinite(orderId)) {
      error.value = "Invalid order.";
      loading.value = false;
      return;
    }

    loading.value = true;
    error.value = null;
    try {
      const { data } = await fetchOrder(auth.token, orderId);
      state.order = data;
    } catch (e) {
      error.value =
        e instanceof ApiError && e.status === 404
          ? "Order not found."
          : "Could not load this order. Please try again.";
    } finally {
      loading.value = false;
    }
  });

  const order = state.order;

  return (
    <div>
      <p style={{ marginBottom: "1rem" }}>
        <Link href="/account/orders" class="link-accent">
          ← Back to orders
        </Link>
      </p>

      {loading.value ? <p class="footer-muted">Loading order…</p> : null}
      {error.value ? <p class="alert alert-error">{error.value}</p> : null}

      {order ? (
        <>
          <h1 class="page-title">Order {order.invoice_no || order.storefront_order_id}</h1>
          <div class="order-meta">
            <span>
              <strong>Status:</strong> {order.shipping_status || order.status}
            </span>
            <span>
              <strong>Payment:</strong> {order.payment_status}
            </span>
            <span>
              <strong>Total:</strong> {formatPrice(order.final_total, settings.value.currency)}
            </span>
            {order.fulfillment_location ? (
              <span>
                <strong>Fulfilled from:</strong> {order.fulfillment_location}
              </span>
            ) : null}
          </div>

          {order.shipping_address ? (
            <div class="account-summary" style={{ marginTop: "1.5rem" }}>
              <h2>Ship to</h2>
              <p class="footer-muted" style={{ margin: 0 }}>
                {order.shipping_address.formatted ||
                  [
                    order.shipping_address.address_line_1,
                    order.shipping_address.address_line_2,
                    order.shipping_address.city,
                    order.shipping_address.state,
                    order.shipping_address.country,
                    order.shipping_address.zip_code,
                  ]
                    .filter(Boolean)
                    .join(", ")}
              </p>
            </div>
          ) : null}

          <div class="table-wrap" style={{ marginTop: "1.5rem" }}>
            <table class="account-table">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Qty</th>
                  <th>Unit price</th>
                  <th>Line total</th>
                </tr>
              </thead>
              <tbody>
                {order.lines.map((line, idx) => (
                  <tr key={idx}>
                    <td data-label="Item">
                      {line.product_name || `Product #${line.product_id}`}
                      {line.variation_name ? ` — ${line.variation_name}` : ""}
                    </td>
                    <td data-label="Qty">{line.quantity}</td>
                    <td data-label="Unit price">
                      {formatPrice(line.unit_price_inc_tax, settings.value.currency)}
                    </td>
                    <td data-label="Line total">
                      {formatPrice(line.line_total, settings.value.currency)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      ) : null}
    </div>
  );
});

export const head: DocumentHead = {
  title: "Order details",
  meta: [{ name: "robots", content: "noindex, nofollow" }],
};
