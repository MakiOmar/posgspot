import { component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { Link, useLocation, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { ApiError, fetchOrder, fetchOrderInvoiceUrl } from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { addCartItems } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { formatPrice } from "~/lib/format";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { confirmAction, toastError, toastSuccess } from "~/lib/notify";
import type { AccountOrderDetail, CartItem } from "~/lib/types";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

function isPaidOrder(paymentStatus: string | undefined): boolean {
  return (paymentStatus ?? "").trim().toLowerCase() === "paid";
}

function orderLinesToCartItems(order: AccountOrderDetail): CartItem[] {
  return order.lines
    .filter((line) => line.variation_id && line.quantity > 0)
    .map((line) => ({
      productId: line.product_id,
      variationId: line.variation_id,
      slug: line.slug ?? null,
      name: line.product_name || `Product #${line.product_id}`,
      variationName: line.variation_name || "",
      price: line.unit_price_inc_tax,
      quantity: line.quantity,
      imageUrl: line.image_url ?? null,
    }));
}

export default component$(() => {
  const auth = useAuth();
  const cart = useCart();
  const nav = useNavigate();
  const loc = useLocation();
  const settings = useSiteSettings();
  const { locale } = useI18n();
  const state = useStore<{ order: AccountOrderDetail | null }>({ order: null });
  const loading = useSignal(true);
  const printUrl = useSignal<string | null>(null);
  const reordering = useSignal(false);

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => auth.token);
    if (!auth.token) {
      return;
    }
    const orderId = Number(loc.params.id);
    if (!Number.isFinite(orderId)) {
      await toastError(tStatic(locale, "account.invalidOrder"));
      loading.value = false;
      return;
    }

    loading.value = true;
    printUrl.value = null;
    try {
      const { data } = await fetchOrder(auth.token, orderId);
      state.order = data;

      if (isPaidOrder(data.payment_status)) {
        if (data.invoice_print_url) {
          printUrl.value = data.invoice_print_url;
        } else {
          try {
            const { data: invoice } = await fetchOrderInvoiceUrl(auth.token, orderId);
            printUrl.value = invoice.invoice_print_url;
          } catch {
            // Invoice endpoint unavailable or order not eligible.
          }
        }
      }
    } catch (e) {
      await toastError(
        e instanceof ApiError && e.status === 404
          ? tStatic(locale, "account.orderNotFound")
          : tStatic(locale, "account.loadOrderFailed"),
      );
    } finally {
      loading.value = false;
    }
  });

  const order = state.order;

  return (
    <div>
      <p style={{ marginBottom: "1rem" }}>
        <Link href={localePath(locale, "/account/orders")} class="link-accent">
          {tStatic(locale, "account.backToOrders")}
        </Link>
      </p>

      {loading.value ? <p class="footer-muted">{tStatic(locale, "account.loadingOrder")}</p> : null}

      {order ? (
        <>
          <div
            style={{
              display: "flex",
              flexWrap: "wrap",
              alignItems: "center",
              gap: "1rem",
              justifyContent: "space-between",
            }}
          >
            <h1 class="page-title" style={{ margin: 0 }}>
              {tStatic(locale, "account.orderHeading", {
                id: order.invoice_no || order.storefront_order_id,
              })}
            </h1>
            <div style={{ display: "flex", flexWrap: "wrap", gap: "0.75rem" }}>
              <button
                type="button"
                class="btn btn-primary"
                disabled={reordering.value || order.lines.length === 0}
                onClick$={async () => {
                  const items = orderLinesToCartItems(order);
                  if (items.length === 0) {
                    await toastError(tStatic(locale, "account.reorderEmpty"));
                    return;
                  }
                  const ok = await confirmAction({
                    title: tStatic(locale, "account.reorderConfirm"),
                    text: tStatic(locale, "account.reorderConfirmText"),
                    confirmText: tStatic(locale, "account.reorder"),
                    cancelText: tStatic(locale, "common.cancel"),
                    icon: "question",
                    dir: locale === "ar" ? "rtl" : "ltr",
                  });
                  if (!ok) {
                    return;
                  }
                  reordering.value = true;
                  try {
                    await addCartItems(cart, items);
                    await toastSuccess(tStatic(locale, "account.reorderSuccess"));
                    await nav(localePath(locale, "/cart"));
                  } finally {
                    reordering.value = false;
                  }
                }}
              >
                {reordering.value
                  ? tStatic(locale, "account.reordering")
                  : tStatic(locale, "account.reorder")}
              </button>
              {printUrl.value ? (
                <a
                  href={printUrl.value}
                  target="_blank"
                  rel="noopener noreferrer"
                  class="btn btn-secondary"
                >
                  {tStatic(locale, "account.printInvoice")}
                </a>
              ) : null}
            </div>
          </div>
          <div class="order-meta">
            <span>
              <strong>{tStatic(locale, "account.statusLabel")}</strong>{" "}
              {order.shipping_status || order.status}
            </span>
            <span>
              <strong>{tStatic(locale, "account.paymentLabel")}</strong> {order.payment_status}
            </span>
            {order.coupon_code ? (
              <span>
                <strong>{tStatic(locale, "account.couponCodeLabel")}</strong> {order.coupon_code}
              </span>
            ) : null}
            {typeof order.subtotal === "number" && order.subtotal > 0 ? (
              <span>
                <strong>{tStatic(locale, "account.subtotalLabel")}</strong>{" "}
                {formatPrice(order.subtotal, settings.value.currency, locale)}
              </span>
            ) : null}
            {typeof order.discount_amount === "number" && order.discount_amount > 0 ? (
              <span style={{ color: "var(--gs-accent)" }}>
                <strong>{tStatic(locale, "account.discountLabel")}</strong> −
                {formatPrice(order.discount_amount, settings.value.currency, locale)}
              </span>
            ) : null}
            {typeof order.shipping_charges === "number" && order.shipping_charges > 0 ? (
              <span>
                <strong>{tStatic(locale, "account.shippingLabel")}</strong>{" "}
                {formatPrice(order.shipping_charges, settings.value.currency, locale)}
              </span>
            ) : null}
            <span>
              <strong>{tStatic(locale, "account.totalLabel")}</strong>{" "}
              {formatPrice(order.final_total, settings.value.currency, locale)}
            </span>
            {order.fulfillment_location ? (
              <span>
                <strong>{tStatic(locale, "account.fulfilledFrom")}</strong>{" "}
                {order.fulfillment_location}
              </span>
            ) : null}
          </div>

          {order.shipping_address ? (
            <div class="account-summary" style={{ marginTop: "1.5rem" }}>
              <h2>{tStatic(locale, "account.shipTo")}</h2>
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
                  <th>{tStatic(locale, "account.item")}</th>
                  <th>{tStatic(locale, "account.qty")}</th>
                  <th>{tStatic(locale, "account.unitPrice")}</th>
                  <th>{tStatic(locale, "account.lineTotal")}</th>
                </tr>
              </thead>
              <tbody>
                {order.lines.map((line, idx) => (
                  <tr key={idx}>
                    <td data-label={tStatic(locale, "account.item")}>
                      {line.product_name ||
                        tStatic(locale, "account.productFallback", { id: line.product_id })}
                      {line.variation_name ? ` — ${line.variation_name}` : ""}
                    </td>
                    <td data-label={tStatic(locale, "account.qty")}>{line.quantity}</td>
                    <td data-label={tStatic(locale, "account.unitPrice")}>
                      {formatPrice(line.unit_price_inc_tax, settings.value.currency, locale)}
                    </td>
                    <td data-label={tStatic(locale, "account.lineTotal")}>
                      {formatPrice(line.line_total, settings.value.currency, locale)}
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

export const head: DocumentHead = ({ resolveValue }) => {
  const lang = resolveValue(useLangParam);
  return {
    title: tStatic(lang, "account.orderDetailsTitle"),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
