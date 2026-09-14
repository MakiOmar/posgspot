import { component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { useLocation, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { fetchPaymentSession } from "~/lib/api";
import { startFawryCheckout } from "~/lib/fawry-pay";
import { startGeideaCheckout, stopGeideaExpiryWatcher } from "~/lib/geidea-checkout";
import {
  clearPaymentSession,
  readPaymentSession,
  sessionMatchesOrder,
  storePaymentSession,
} from "~/lib/payment-session";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { PaymentSession } from "~/lib/types";
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
  const provider = settings.value.online_payments.provider || "";

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ cleanup }) => {
    cleanup(() => stopGeideaExpiryWatcher());

    if (!orderId) {
      error.value = tStatic(locale, "payment.missingOrder");
      loading.value = false;
      return;
    }

    if (!settings.value.online_payments.enabled || !provider) {
      error.value = tStatic(locale, "payment.unavailable");
      loading.value = false;
      return;
    }

    const returnPath = localePath(locale, `/checkout/payment/return/?order=${encodeURIComponent(orderId)}`);

    const launch = async (session: PaymentSession) => {
      loading.value = false;
      launching.value = true;
      if (session.provider === "geidea") {
        await startGeideaCheckout(session, {
          onSuccess: () => {
            void nav(returnPath);
          },
          onError: (message) => {
            error.value = message || tStatic(locale, "payment.launchFailed");
            launching.value = false;
          },
          onCancel: () => {
            void nav(returnPath);
          },
          onExpired: () => {
            clearPaymentSession();
            void (async () => {
              try {
                const { data } = await fetchPaymentSession(provider, orderId, locale);
                if ("already_paid" in data && data.already_paid) {
                  await nav(`${returnPath}&paid=1`);
                  return;
                }
                const fresh = data as PaymentSession;
                storePaymentSession(fresh);
                await launch(fresh);
              } catch (err) {
                error.value = err instanceof Error ? err.message : tStatic(locale, "payment.loadFailed");
                launching.value = false;
              }
            })();
          },
        });
        return;
      }
      if (session.provider === "fawry") {
        await startFawryCheckout(session);
        return;
      }
      error.value = tStatic(locale, "payment.unavailable");
      launching.value = false;
    };

    let session: PaymentSession | null = readPaymentSession();
    if (!sessionMatchesOrder(session, orderId) || session?.provider !== provider) {
      try {
        const { data } = await fetchPaymentSession(provider, orderId, locale);
        if ("already_paid" in data && data.already_paid) {
          clearPaymentSession();
          await nav(`${returnPath}&paid=1`);
          return;
        }
        session = data as PaymentSession;
        storePaymentSession(session);
      } catch (err) {
        error.value = err instanceof Error ? err.message : tStatic(locale, "payment.loadFailed");
        loading.value = false;
        return;
      }
    }

    if (!session) {
      error.value = tStatic(locale, "payment.loadFailed");
      loading.value = false;
      return;
    }

    try {
      await launch(session);
    } catch (err) {
      error.value = err instanceof Error ? err.message : tStatic(locale, "payment.launchFailed");
      launching.value = false;
      loading.value = false;
    }
  });

  return (
    <section class="payment-page">
      <h1 class="page-title">{tStatic(locale, "payment.title")}</h1>
      {/* Drop-in mount — GeideaCheckout.startPayment third argument. */}
      <div id="geidea-dropin-container" />
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
