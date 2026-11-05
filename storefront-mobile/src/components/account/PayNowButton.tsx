import { useState } from "react";
import { Pressable, StyleSheet, Text } from "react-native";
import { useRouter } from "expo-router";
import { fetchOrderPaymentAccess } from "../../lib/api";
import { canPayOrderOnline } from "../../lib/order-status";
import { toast } from "../../lib/toast";
import { useApp } from "../../contexts/AppContext";
import { PrimaryButton } from "../ui";

type PayableOrder = {
  id: number;
  storefront_order_id?: string | null;
  payment_status?: string | null;
  can_pay_online?: boolean;
};

/**
 * Requests a fresh order access token, then opens the gateway payment screen.
 * Renders nothing when online payments are off or the order cannot be paid online.
 */
export function PayNowButton({
  order,
  variant = "primary",
}: {
  order: PayableOrder;
  variant?: "primary" | "compact";
}) {
  const { token, t, settings, accent } = useApp();
  const router = useRouter();
  const [busy, setBusy] = useState(false);

  const onlineEnabled =
    !!settings?.online_payments?.enabled && !!settings?.online_payments?.provider;
  if (!token || !onlineEnabled || !canPayOrderOnline(order)) {
    return null;
  }

  const onPress = () => {
    void (async () => {
      setBusy(true);
      try {
        const { data } = await fetchOrderPaymentAccess(token, order.id);
        router.push({
          pathname: "/checkout/payment",
          params: {
            storefrontOrderId: data.storefront_order_id,
            orderId: String(order.id),
            orderAccessToken: data.order_access_token,
          },
        });
      } catch (e) {
        toast.error(e instanceof Error ? e.message : t("common.error"));
      } finally {
        setBusy(false);
      }
    })();
  };

  const label = busy ? t("common.loading") : t("account.payNow");

  if (variant === "compact") {
    return (
      <Pressable
        style={[styles.compact, { borderColor: accent }]}
        disabled={busy}
        onPress={onPress}
        accessibilityRole="button"
      >
        <Text style={{ color: accent, fontWeight: "700" }}>{label}</Text>
      </Pressable>
    );
  }

  return <PrimaryButton label={label} disabled={busy} onPress={onPress} />;
}

const styles = StyleSheet.create({
  compact: {
    marginTop: 10,
    alignSelf: "flex-start",
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderWidth: 1,
    borderRadius: 8,
  },
});
