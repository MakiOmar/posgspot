import { component$ } from "@builder.io/qwik";
import { useI18n } from "~/lib/i18n/context";
import { paymentStatusLabel, paymentStatusTone } from "~/lib/payment-status";

interface Props {
  status: string | null | undefined;
  size?: "sm" | "lg";
}

/** Color-coded payment status (paid / not paid / pending / failed) for order screens. */
export const PaymentStatusBadge = component$<Props>(({ status, size = "sm" }) => {
  const { locale } = useI18n();
  const tone = paymentStatusTone(status);

  return (
    <span class={["payment-badge", `payment-badge--${tone}`, size === "lg" ? "payment-badge--lg" : ""]}>
      {/* Status dot keeps the state readable without relying on color alone (paired with the label). */}
      <span class="payment-badge__dot" aria-hidden="true" />
      {paymentStatusLabel(locale, status)}
    </span>
  );
});
