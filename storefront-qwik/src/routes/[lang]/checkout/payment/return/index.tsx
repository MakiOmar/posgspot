import { component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link, useLocation, type DocumentHead } from "@builder.io/qwik-city";
import { PaymentStatusBadge } from "~/components/order/payment-status-badge";
import { confirmPaymentReturn } from "~/lib/api";
import { clearPaymentSession } from "~/lib/payment-session";
import { paymentStatusTone } from "~/lib/payment-status";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { PaymentReturnResult } from "~/lib/types";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

export default component$(() => {
  const loc = useLocation();
  const { locale } = useI18n();
  const settings = useSiteSettings();
  const loading = useSignal(true);
  const error = useSignal<string | null>(null);
  const result = useSignal<PaymentReturnResult | null>(null);

  const orderId = loc.url.searchParams.get("order") || "";
  const accessToken = loc.url.searchParams.get("access") || "";
  const alreadyPaid = loc.url.searchParams.get("paid") === "1";
  const provider = settings.value.online_payments.provider || "fawry";
  const payAgainPath = localePath(
    locale,
    `/checkout/payment/?order=${encodeURIComponent(orderId)}${accessToken ? `&access=${encodeURIComponent(accessToken)}` : ""}`,
  );

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async () => {
    if (!orderId) {
      error.value = tStatic(locale, "payment.missingOrder");
      loading.value = false;
      return;
    }

    if (alreadyPaid) {
      clearPaymentSession();
      result.value = {
        payment_status: "paid",
        message: tStatic(locale, "payment.alreadyPaid"),
        order: {
          id: 0,
          storefront_order_id: orderId,
          invoice_no: "",
          status: "final",
          payment_status: "paid",
          final_total: 0,
          transaction_date: "",
          shipping_status: "ordered",
        },
        reference_number: null,
        fawry_ref_number: null,
        payment_method: null,
        expiration_time: null,
      };
      loading.value = false;
      return;
    }

    const payload: Record<string, unknown> = { merchantRefNumber: orderId, order: orderId };
    loc.url.searchParams.forEach((value, key) => {
      payload[key] = value;
    });

    try {
      const { data } = await confirmPaymentReturn(provider, payload);
      result.value = data;
      clearPaymentSession();
    } catch (err) {
      error.value = err instanceof Error ? err.message : tStatic(locale, "payment.confirmFailed");
    } finally {
      loading.value = false;
    }
  });

  if (loading.value) {
    return (
      <section class="payment-page">
        <h1 class="page-title">{tStatic(locale, "payment.confirming")}</h1>
        <p class="footer-muted">{tStatic(locale, "common.loadingPleaseWait")}</p>
      </section>
    );
  }

  if (error.value) {
    return (
      <section class="payment-page">
        <h1 class="page-title">{tStatic(locale, "payment.failedTitle")}</h1>
        <div class="alert alert-error">{error.value}</div>
        <Link href={payAgainPath} class="btn btn-primary">
          {tStatic(locale, "payment.tryAgain")}
        </Link>
      </section>
    );
  }

  const data = result.value;
  const orderStatus = data?.order?.payment_status;
  const paid = data?.payment_status === "paid" || paymentStatusTone(orderStatus) === "paid";
  const pending = !paid && data?.payment_status === "pending";
  const badgeStatus = paid ? "paid" : pending ? "pending" : orderStatus || "due";
  const orderLabel = data?.order?.invoice_no || orderId;
  const reference = data?.provider_ref_number || data?.reference_number || data?.fawry_ref_number;
  const canPayNow =
    !paid &&
    !pending &&
    Boolean(accessToken) &&
    settings.value.online_payments.enabled &&
    data?.order?.can_pay_online !== false;

  return (
    <section class="payment-page">
      {/* Payment outcome card: badge + plain-language status; raw gateway text only for real declines. */}
      <div class={["payment-result", "card-surface", `payment-result--${paymentStatusTone(badgeStatus)}`]}>
        <PaymentStatusBadge status={badgeStatus} size="lg" />
        <h1 class="page-title">
          {paid
            ? tStatic(locale, "payment.successTitle")
            : pending
              ? tStatic(locale, "payment.pendingTitle")
              : tStatic(locale, "payment.failedTitle")}
        </h1>
        <p class="payment-result__lead">
          {paid
            ? tStatic(locale, "payment.paidLead", { order: orderLabel })
            : pending
              ? tStatic(locale, "payment.pendingLead", { order: orderLabel })
              : tStatic(locale, "payment.unpaidLead", { order: orderLabel })}
        </p>
        {data?.payment_status === "failed" && data.message ? (
          <p class="payment-result__details footer-muted">
            {tStatic(locale, "payment.gatewayDetails", { message: data.message })}
          </p>
        ) : null}
      </div>

      {pending && reference ? (
        <div class="payment-reference card-surface">
          <p>{tStatic(locale, "payment.referenceLabel")}</p>
          <p class="payment-reference__code" dir="ltr">
            {reference}
          </p>
          {data?.expiration_time ? (
            <p class="footer-muted">{tStatic(locale, "payment.expiresAt", { date: data.expiration_time })}</p>
          ) : null}
        </div>
      ) : null}

      <div class="payment-page__actions">
        {canPayNow ? (
          <Link href={payAgainPath} class="btn btn-primary">
            {tStatic(locale, "payment.payNow")}
          </Link>
        ) : null}
        <Link
          href={localePath(locale, "/account/orders")}
          class={canPayNow ? "btn btn-secondary" : "btn btn-primary"}
        >
          {tStatic(locale, "payment.viewOrders")}
        </Link>
        <Link href={localePath(locale, "/products")} class="btn btn-secondary">
          {tStatic(locale, "cart.continueShopping")}
        </Link>
      </div>
    </section>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = resolveValue(useLangParam);
  return {
    title: tStatic(lang, "payment.returnSeoTitle", { businessName: settings.business_name }),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
