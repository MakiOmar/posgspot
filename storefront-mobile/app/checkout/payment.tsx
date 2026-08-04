import { useLocalSearchParams, useRouter } from "expo-router";
import { useEffect, useState } from "react";
import { StyleSheet, Text, View } from "react-native";
import {
  confirmPaymentReturn,
  fetchOrder,
  fetchPaymentSession,
} from "../../src/lib/api";
import {
  isFawrySdkAvailable,
  sessionToLaunchModel,
  startFawryPayment,
} from "../../src/lib/fawry";
import { useApp } from "../../src/contexts/AppContext";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../src/components/ui";

export default function PaymentScreen() {
  const { storefrontOrderId, orderId } = useLocalSearchParams<{
    storefrontOrderId: string;
    orderId: string;
  }>();
  const { t, locale, token, contact } = useApp();
  const router = useRouter();
  const [status, setStatus] = useState<string>("Preparing Fawry…");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const launch = async () => {
    if (!storefrontOrderId) {
      setError("Missing order");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      if (!isFawrySdkAvailable()) {
        setError(
          "Fawry native SDK is not linked. Build with Expo Dev Client after installing @fawry_pay/rn-fawry-pay-sdk.",
        );
        setBusy(false);
        return;
      }
      const { data: session } = await fetchPaymentSession(
        "fawry",
        storefrontOrderId,
        locale,
        token,
      );
      const model = sessionToLaunchModel(
        session,
        {
          customerName: contact?.name,
          customerMobile: contact?.mobile,
          customerEmail: contact?.email,
          customerProfileId: contact?.id ? String(contact.id) : undefined,
        },
        [
          {
            itemId: storefrontOrderId,
            description: `Order ${storefrontOrderId}`,
            quantity: "1",
            price: "0.00",
          },
        ],
      );
      setStatus("Opening Fawry…");
      const result = await startFawryPayment(model);
      if (!result.ok) {
        setError(result.reason);
        setBusy(false);
        return;
      }
      try {
        await confirmPaymentReturn(
          "fawry",
          {
            storefront_order_id: storefrontOrderId,
            payload: result.payload,
          },
          token,
        );
      } catch {
        // Webhook may already have confirmed.
      }
      if (token && orderId) {
        await fetchOrder(token, Number(orderId));
        router.replace(`/account/orders/${orderId}`);
        return;
      }
      setStatus(t("checkout.success"));
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setBusy(false);
    }
  };

  useEffect(() => {
    void launch();
  }, []);

  return (
    <Screen>
      {error ? (
        <ErrorBlock message={error} onRetry={() => void launch()} />
      ) : busy ? (
        <LoadingBlock />
      ) : (
        <View style={styles.box}>
          <Text style={styles.status}>{status}</Text>
          <PrimaryButton label="Retry payment" onPress={() => void launch()} />
        </View>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  box: { gap: 16 },
  status: { fontSize: 16 },
});
