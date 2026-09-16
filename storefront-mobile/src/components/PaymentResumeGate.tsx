import { useRouter } from "expo-router";
import { useEffect, useRef } from "react";
import { useApp } from "../contexts/AppContext";
import { loadPendingPayment } from "../lib/pending-payment";

/**
 * After a remount mid-checkout, send the shopper back to payment resume
 * once auth has been restored from SecureStore.
 */
export function PaymentResumeGate() {
  const { loading, token } = useApp();
  const router = useRouter();
  const handled = useRef(false);

  useEffect(() => {
    if (loading || handled.current || !token) {
      return;
    }
    let cancelled = false;
    void (async () => {
      const pending = await loadPendingPayment();
      if (cancelled || !pending) {
        return;
      }
      handled.current = true;
      router.replace({
        pathname: "/checkout/payment",
        params: {
          storefrontOrderId: pending.storefrontOrderId,
          orderId: pending.orderId,
          resume: "1",
        },
      });
    })();
    return () => {
      cancelled = true;
    };
  }, [loading, token, router]);

  return null;
}
