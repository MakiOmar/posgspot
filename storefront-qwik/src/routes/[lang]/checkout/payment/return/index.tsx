import { component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link, useLocation, type DocumentHead } from "@builder.io/qwik-city";
import { confirmPaymentReturn } from "~/lib/api";
import { clearFawryPaymentSession } from "~/lib/fawry-pay";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { PaymentReturnResult } from "~/lib/types";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

export default component$(() => {
  const loc = useLocation();
  const { locale } = useI18n();
  const loading = useSignal(true);
  const error = useSignal<string | null>(null);
  const result = useSignal<PaymentReturnResult | null>(null);

  const orderId = loc.url.searchParams.get("order") || "";
  const alreadyPaid = loc.url.searchParams.get("paid") === "1";

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async () => {
    if (!orderId) {
      error.value = tStatic(locale, "payment.missingOrder");
      loading.value = false;
      return;
    }

    if (alreadyPaid) {
      clearFawryPaymentSession();
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

    const payload: Record<string, unknown> = { merchantRefNumber: orderId };
    loc.url.searchParams.forEach((value, key) => {
      payload[key] = value;
    });

    try {
      const { data } = await confirmPaymentReturn("fawry", payload);
      result.value = data;
      clearFawryPaymentSession();
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
        <Link href={localePath(locale, `/checkout/payment/?order=${encodeURIComponent(orderId)}`)} class="btn btn-primary">
          {tStatic(locale, "payment.tryAgain")}
        </Link>
      </section>
    );
  }

  const data = result.value;
  const paid = data?.payment_status === "paid";
  const pending = data?.payment_status === "pending";

  return (
    <section class="payment-page">
      <h1 class="page-title">
        {paid
          ? tStatic(locale, "payment.successTitle")
          : pending
            ? tStatic(locale, "payment.pendingTitle")
            : tStatic(locale, "payment.failedTitle")}
      </h1>

      {data?.message ? <p class="footer-muted">{data.message}</p> : null}

      {pending && data.reference_number ? (
        <div class="payment-reference card-surface">
          <p>{tStatic(locale, "payment.referenceLabel")}</p>
          <p class="payment-reference__code" dir="ltr">
            {data.reference_number}
          </p>
          {data.expiration_time ? (
            <p class="footer-muted">{tStatic(locale, "payment.expiresAt", { date: data.expiration_time })}</p>
          ) : null}
        </div>
      ) : null}

      {data?.order?.invoice_no ? (
        <p class="footer-muted">
          {tStatic(locale, "checkout.orderThanks", {
            invoiceNo: data.order.invoice_no,
            paymentStatus: data.order.payment_status,
          })}
        </p>
      ) : null}

      <div class="payment-page__actions">
        <Link href={localePath(locale, "/account/orders")} class="btn btn-primary">
          {tStatic(locale, "payment.viewOrders")}
        </Link>
        <Link href={localePath(locale, "/products")} class="btn btn-ghost">
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
