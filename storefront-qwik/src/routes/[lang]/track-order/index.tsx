import { $, component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import {
  ApiError,
  fetchOrders,
  trackOrderLookup,
} from "~/lib/api";
import { isAuthenticated } from "~/lib/auth-actions";
import { useAuth } from "~/lib/auth-context";
import { formatPrice } from "~/lib/format";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError, toastSuccess } from "~/lib/notify";
import { usePendingState } from "~/lib/pending-context";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type { AccountOrder, TrackedOrder } from "~/lib/types";
import { withPendingFeedback } from "~/lib/with-pending";
import { useSiteSettings } from "~/routes/[lang]/layout";

function formatDate(value: string | null | undefined, locale: string): string {
  if (!value) return "—";
  const d = new Date(value);
  return Number.isNaN(d.getTime())
    ? value
    : d.toLocaleDateString(locale === "ar" ? "ar-EG" : "en-EG");
}

function looksLikeEmail(value: string): boolean {
  return value.includes("@");
}

const TrackedOrderCard = component$<{ order: TrackedOrder }>(({ order }) => {
  const { locale } = useI18n();
  const settings = useSiteSettings();

  return (
    <section class="repair-status-card track-order-card">
      <header class="repair-status-card__head">
        <h2>{order.invoice_no || order.storefront_order_id || `#${order.id}`}</h2>
        {order.shipping_status || order.status ? (
          <span class="status-pill">{order.shipping_status || order.status}</span>
        ) : null}
      </header>

      <dl class="repair-status-meta">
        <dt>{tStatic(locale, "trackOrder.status")}</dt>
        <dd>{order.status || "—"}</dd>
        <dt>{tStatic(locale, "trackOrder.payment")}</dt>
        <dd>{order.payment_status || "—"}</dd>
        <dt>{tStatic(locale, "trackOrder.shipping")}</dt>
        <dd>{order.shipping_status || "—"}</dd>
        {order.shipping_carrier ? (
          <>
            <dt>{tStatic(locale, "trackOrder.carrier")}</dt>
            <dd>{order.shipping_carrier}</dd>
          </>
        ) : null}
        {order.shipping_tracking_number || order.shipping_tracking_url ? (
          <>
            <dt>{tStatic(locale, "trackOrder.tracking")}</dt>
            <dd>
              {order.shipping_tracking_url ? (
                <a href={order.shipping_tracking_url} target="_blank" rel="noopener noreferrer">
                  {order.shipping_tracking_number || order.shipping_tracking_url}
                </a>
              ) : (
                order.shipping_tracking_number
              )}
            </dd>
          </>
        ) : null}
        <dt>{tStatic(locale, "trackOrder.total")}</dt>
        <dd>
          {order.final_total != null
            ? formatPrice(Number(order.final_total), settings.value.currency, locale)
            : "—"}
        </dd>
        <dt>{tStatic(locale, "trackOrder.placed")}</dt>
        <dd>{formatDate(order.transaction_date, locale)}</dd>
      </dl>

      {order.lines?.length ? (
        <ul class="track-order-lines">
          {order.lines.map((line, index) => (
            <li key={`${order.id}-line-${index}`}>
              {line.product_name || "—"} × {line.quantity}
            </li>
          ))}
        </ul>
      ) : null}
    </section>
  );
});

/** Guest invoice lookup + signed-in recent orders. */
export default component$(() => {
  const settings = useSiteSettings();
  const auth = useAuth();
  const { locale } = useI18n();
  const pending = usePendingState();
  const submitting = useSignal(false);
  const tracked = useSignal<TrackedOrder | null>(null);
  const searched = useSignal(false);
  const myOrders = useSignal<AccountOrder[]>([]);
  const myOrdersLoaded = useSignal(false);
  const myOrdersLoading = useSignal(false);
  const signedIn = isAuthenticated(auth);

  const form = useStore({
    invoice_no: "",
    phone_or_email: "",
  });

  // Load signed-in customer's recent orders once auth is ready.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => auth.ready);
    track(() => auth.token);
    if (!auth.ready) {
      return;
    }
    if (!auth.token) {
      myOrders.value = [];
      myOrdersLoaded.value = true;
      myOrdersLoading.value = false;
      return;
    }

    myOrdersLoading.value = true;
    try {
      const { data } = await fetchOrders(auth.token);
      myOrders.value = data.slice(0, 10);
    } catch {
      myOrders.value = [];
    } finally {
      myOrdersLoading.value = false;
      myOrdersLoaded.value = true;
    }
  });

  const submit$ = $(async () => {
    const invoice = form.invoice_no.trim();
    const contact = form.phone_or_email.trim();
    if (!invoice || !contact) {
      await toastError(tStatic(locale, "trackOrder.notFound"));
      return;
    }

    const payload = looksLikeEmail(contact)
      ? { invoice_no: invoice, email: contact }
      : { invoice_no: invoice, phone: contact };

    await withPendingFeedback(pending, submitting, async () => {
      try {
        const { data } = await trackOrderLookup(payload, locale);
        tracked.value = data;
        searched.value = true;
        await toastSuccess(tStatic(locale, "trackOrder.title"));
      } catch (e) {
        tracked.value = null;
        searched.value = true;
        const message =
          e instanceof ApiError
            ? e.message || tStatic(locale, "trackOrder.notFound")
            : tStatic(locale, "trackOrder.notFound");
        await toastError(message);
      }
    });
  });

  return (
    <article class="content-page track-order-page">
      <nav class="content-breadcrumb" aria-label={tStatic(locale, "a11y.breadcrumb")}>
        <Link href={localePath(locale, "/")}>{tStatic(locale, "nav.home")}</Link>
        <span aria-hidden="true">›</span>
        <span>{tStatic(locale, "nav.trackOrder")}</span>
      </nav>

      <p class="coming-soon-badge">{tStatic(locale, "trackOrder.badge")}</p>
      <h1 class="content-title">{tStatic(locale, "trackOrder.title")}</h1>

      {/* Signed-in: recent orders */}
      {signedIn || !auth.ready ? (
        <section class="repair-status-mine" aria-live="polite">
          <h2 class="repair-status-mine__title">{tStatic(locale, "trackOrder.yourOrders")}</h2>
          {!auth.ready || myOrdersLoading.value ? (
            <p class="footer-muted">{tStatic(locale, "account.loadingOrders")}</p>
          ) : myOrdersLoaded.value && myOrders.value.length === 0 ? (
            <p class="footer-muted">{tStatic(locale, "account.noOrders")}</p>
          ) : myOrders.value.length > 0 ? (
            <div class="table-wrap">
              <table class="account-table">
                <thead>
                  <tr>
                    <th>{tStatic(locale, "account.order")}</th>
                    <th>{tStatic(locale, "account.date")}</th>
                    <th>{tStatic(locale, "account.status")}</th>
                    <th>{tStatic(locale, "account.total")}</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {myOrders.value.map((order) => (
                    <tr key={order.id}>
                      <td>{order.invoice_no || order.storefront_order_id}</td>
                      <td>{formatDate(order.transaction_date, locale)}</td>
                      <td>
                        <span class="status-pill">{order.shipping_status || order.status}</span>
                      </td>
                      <td>
                        {formatPrice(order.final_total, settings.value.currency, locale)}
                      </td>
                      <td>
                        <Link
                          href={localePath(locale, `/account/orders/${order.id}`)}
                          class="link-accent"
                        >
                          {tStatic(locale, "trackOrder.viewOrder")}
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : null}
        </section>
      ) : null}

      {/* Guest lookup (also available when signed in for someone else's invoice). */}
      <section class="repair-status-layout">
        <div class="repair-status-layout__form">
          {auth.ready && !signedIn ? (
            <p class="footer-muted">
              {tStatic(locale, "trackOrder.signInHint")}{" "}
              <Link href={localePath(locale, "/login")}>{tStatic(locale, "header.signIn")}</Link>
            </p>
          ) : null}
          {auth.ready && !signedIn ? (
            <p class="content-lead">{tStatic(locale, "trackOrder.lead")}</p>
          ) : (
            <p class="footer-muted">{tStatic(locale, "trackOrder.lead")}</p>
          )}
          <form class="repair-status-form" preventdefault:submit onSubmit$={submit$}>
            <div>
              <label for="track_invoice_no">{tStatic(locale, "trackOrder.invoiceNo")}</label>
              <input
                id="track_invoice_no"
                type="text"
                required
                autocomplete="off"
                value={form.invoice_no}
                onInput$={(_, el) => {
                  form.invoice_no = el.value;
                }}
              />
            </div>
            <div>
              <label for="track_phone_or_email">{tStatic(locale, "trackOrder.phoneOrEmail")}</label>
              <input
                id="track_phone_or_email"
                type="text"
                required
                autocomplete="username"
                value={form.phone_or_email}
                onInput$={(_, el) => {
                  form.phone_or_email = el.value;
                }}
              />
            </div>
            <button type="submit" class="btn btn-primary" disabled={submitting.value}>
              {submitting.value
                ? tStatic(locale, "trackOrder.submitting")
                : tStatic(locale, "trackOrder.submit")}
            </button>
          </form>
        </div>

        <div class="repair-status-layout__results" aria-live="polite">
          {searched.value && !tracked.value ? (
            <p class="footer-muted repair-status-empty">{tStatic(locale, "trackOrder.notFound")}</p>
          ) : null}
          {tracked.value ? <TrackedOrderCard order={tracked.value} /> : null}
        </div>
      </section>
    </article>
  );
});

export const head: DocumentHead = ({ resolveValue, url, params }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = isSupportedLocale(params.lang) ? params.lang : "en";
  const title = `${tStatic(lang, "trackOrder.title")} — ${settings.business_name}`;
  const description = tStatic(lang, "trackOrder.lead");

  return withStorefrontThemeHead(
    {
      title,
      meta: [
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:type", content: "website" },
        { property: "og:url", content: url.href },
        { name: "twitter:card", content: "summary" },
        { name: "twitter:title", content: title },
        { name: "twitter:description", content: description },
      ],
      links: publicSeoLinks(url.origin, "/track-order", lang),
    },
    settings,
  );
};
