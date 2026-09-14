import { useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import { StyleSheet, Text, View } from "react-native";
import { WebView } from "react-native-webview";
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
import {
  parseGeideaWebViewMessage,
  startGeideaPayment,
} from "../../src/lib/geidea";
import { useApp } from "../../src/contexts/AppContext";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../src/components/ui";
import type { FawryPaymentSession, GeideaPaymentSession, PaymentSession } from "../../src/lib/types";

export default function PaymentScreen() {
  const { storefrontOrderId, orderId } = useLocalSearchParams<{
    storefrontOrderId: string;
    orderId: string;
  }>();
  const { t, locale, token, contact, settings } = useApp();
  const router = useRouter();
  const provider = settings?.online_payments?.provider || "fawry";
  const [status, setStatus] = useState<string>(t("payment.preparing"));
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [hostedHtml, setHostedHtml] = useState<string | null>(null);

  const finishPaid = useCallback(async () => {
    if (token && orderId) {
      try {
        await fetchOrder(token, Number(orderId));
      } catch {
        // Order fetch is best-effort; webhook is the source of truth.
      }
      router.replace(`/account/orders/${orderId}`);
      return;
    }
    setStatus(t("checkout.success"));
    setBusy(false);
  }, [orderId, router, t, token]);

  const confirmReturn = useCallback(
    async (payload: Record<string, unknown>) => {
      try {
        await confirmPaymentReturn(provider, payload, token);
      } catch {
        // Webhook may already have confirmed.
      }
    },
    [provider, token],
  );

  const launch = useCallback(async () => {
    if (!storefrontOrderId) {
      setError("Missing order");
      return;
    }
    setBusy(true);
    setError(null);
    setHostedHtml(null);
    try {
      const { data } = await fetchPaymentSession(
        provider,
        storefrontOrderId,
        locale,
        token,
      );
      if ("already_paid" in data && data.already_paid) {
        await finishPaid();
        return;
      }
      const session = data as PaymentSession;

      if (session.provider === "geidea" || provider === "geidea") {
        const geidea = session as GeideaPaymentSession;
        const launched = await startGeideaPayment(geidea);
        if ("hostedHtml" in launched) {
          setStatus(t("payment.opening"));
          setHostedHtml(launched.hostedHtml);
          return;
        }
        if (!launched.ok) {
          setError(launched.reason);
          setBusy(false);
          return;
        }
        await confirmReturn({
          storefront_order_id: storefrontOrderId,
          merchantRefNumber: storefrontOrderId,
          payload: launched.payload,
        });
        await finishPaid();
        return;
      }

      if (!isFawrySdkAvailable()) {
        setError(t("payment.fawrySdkMissing"));
        setBusy(false);
        return;
      }
      const model = sessionToLaunchModel(
        session as FawryPaymentSession,
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
      setStatus(t("payment.opening"));
      const result = await startFawryPayment(model);
      if (!result.ok) {
        setError(result.reason);
        setBusy(false);
        return;
      }
      await confirmReturn({
        storefront_order_id: storefrontOrderId,
        payload: result.payload,
      });
      await finishPaid();
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
      setBusy(false);
    }
  }, [
    confirmReturn,
    contact,
    finishPaid,
    locale,
    provider,
    storefrontOrderId,
    t,
    token,
  ]);

  useEffect(() => {
    void launch();
    // Launch once on mount; webhook remains the fulfilment source of truth.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const onHostedMessage = async (raw: string) => {
    const parsed = parseGeideaWebViewMessage(raw);
    if (!parsed) {
      return;
    }
    setHostedHtml(null);
    if (!parsed.ok) {
      setError(parsed.reason);
      setBusy(false);
      return;
    }
    await confirmReturn({
      storefront_order_id: storefrontOrderId,
      merchantRefNumber: storefrontOrderId,
      payload: parsed.payload,
    });
    await finishPaid();
  };

  if (hostedHtml) {
    return (
      <Screen padded={false} avoidKeyboard={false}>
        <WebView
          originWhitelist={["*"]}
          source={{ html: hostedHtml, baseUrl: "https://www.merchant.geidea.net" }}
          javaScriptEnabled
          onMessage={(event) => {
            void onHostedMessage(event.nativeEvent.data);
          }}
          setSupportMultipleWindows={false}
          startInLoadingState
          renderLoading={() => (
            <View style={styles.overlay}>
              <LoadingBlock />
            </View>
          )}
        />
      </Screen>
    );
  }

  return (
    <Screen>
      {error ? (
        <ErrorBlock message={error} onRetry={() => void launch()} />
      ) : busy ? (
        <LoadingBlock />
      ) : (
        <View style={styles.box}>
          <Text style={styles.status}>{status}</Text>
          <PrimaryButton label={t("payment.retry")} onPress={() => void launch()} />
        </View>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  box: { gap: 16 },
  status: { fontSize: 16 },
  overlay: {
    ...StyleSheet.absoluteFill,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#fff",
  },
});
