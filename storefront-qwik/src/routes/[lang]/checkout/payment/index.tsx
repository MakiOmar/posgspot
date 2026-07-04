import { component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { useLocation, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { fetchPaymentSession } from "~/lib/api";
import {
  clearFawryPaymentSession,
  readFawryPaymentSession,
  startFawryCheckout,
  storeFawryPaymentSession,
} from "~/lib/fawry-pay";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { FawryPaymentSession } from "~/lib/types";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

export default component$(() => {
  const loc = useLocation();
  const nav = useNavigate();
  const { locale } = useI18n();
  const settings = useSiteSettings();
  const loading = useSignal(true);
  const error = useSignal<string | null>(null);
  const launching = useSignal(false);

  const orderId = loc.url.searchParams.get("order") || "";

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async () => {
    if (!orderId) {
      error.value = tStatic(locale, "payment.missingOrder");
      loading.value = false;
      return;
    }

    if (!settings.value.online_payments.enabled || settings.value.online_payments.provider !== "fawry") {
      error.value = tStatic(locale, "payment.unavailable");
      loading.value = false;
      return;
    }

    let session: FawryPaymentSession | null = readFawryPaymentSession();
    if (!session || session.charge.merchantRefNum !== orderId) {
      try {
        const { data } = await fetchPaymentSession("fawry", orderId, locale);
        if ("already_paid" in data && data.already_paid) {
          clearFawryPaymentSession();
          await nav(localePath(locale, `/checkout/payment/return/?order=${encodeURIComponent(orderId)}&paid=1`));
          return;
        }
        session = data as FawryPaymentSession;
        storeFawryPaymentSession(session);
      } catch (err) {
        error.value = err instanceof Error ? err.message : tStatic(locale, "payment.loadFailed");
        loading.value = false;
        return;
      }
    }

    loading.value = false;
    launching.value = true;

    try {
      await startFawryCheckout(session);
    } catch (err) {
      error.value = err instanceof Error ? err.message : tStatic(locale, "payment.launchFailed");
      launching.value = false;
    }
  });

  return (
    <section class="payment-page">
      <h1 class="page-title">{tStatic(locale, "payment.title")}</h1>
      {loading.value || launching.value ? (
        <div class="payment-page__status">
          <p>{loading.value ? tStatic(locale, "payment.preparing") : tStatic(locale, "payment.redirecting")}</p>
        </div>
      ) : null}
      {error.value ? (
        <div class="alert alert-error">
          <p>{error.value}</p>
          <a href={localePath(locale, "/checkout")} class="btn btn-primary">
            {tStatic(locale, "payment.backToCheckout")}
          </a>
        </div>
      ) : null}
    </section>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = resolveValue(useLangParam);
  return {
    title: tStatic(lang, "payment.seoTitle", { businessName: settings.business_name }),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
