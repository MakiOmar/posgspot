import { component$, useSignal } from "@builder.io/qwik";
import { useNavigate } from "@builder.io/qwik-city";
import { ApiError, fetchOrderPaymentAccess } from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError } from "~/lib/notify";
import { clearPaymentSession, storeOrderAccessToken } from "~/lib/payment-session";

interface Props {
  orderId: number;
  class?: string;
}

/** Signed-in "Pay now": issues a fresh order access token, then opens the hosted payment page. */
export const PayNowButton = component$<Props>(({ orderId, class: className }) => {
  const auth = useAuth();
  const nav = useNavigate();
  const { locale } = useI18n();
  const starting = useSignal(false);

  return (
    <button
      type="button"
      class={className ?? "btn btn-primary"}
      disabled={starting.value || !auth.token}
      aria-busy={starting.value}
      onClick$={async () => {
        if (!auth.token || starting.value) {
          return;
        }
        starting.value = true;
        try {
          const { data } = await fetchOrderPaymentAccess(auth.token, orderId);
          // A cached gateway session embeds the previous (now rotated) access token in its return URL.
          clearPaymentSession();
          storeOrderAccessToken(data.storefront_order_id, data.order_access_token);
          await nav(
            localePath(
              locale,
              `/checkout/payment/?order=${encodeURIComponent(data.storefront_order_id)}&access=${encodeURIComponent(data.order_access_token)}`,
            ),
          );
        } catch (err) {
          starting.value = false;
          await toastError(
            err instanceof ApiError && err.status === 422
              ? tStatic(locale, "payment.payNowUnavailable")
              : tStatic(locale, "payment.payNowFailed"),
          );
        }
      }}
    >
      {starting.value ? tStatic(locale, "payment.redirecting") : tStatic(locale, "payment.payNow")}
    </button>
  );
});
