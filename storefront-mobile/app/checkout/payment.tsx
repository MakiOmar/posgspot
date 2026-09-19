import { useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useEffect, useRef, useState } from "react";
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
import {
  clearPendingPayment,
  loadPendingPayment,
  savePendingPayment,
} from "../../src/lib/pending-payment";
import { useApp } from "../../src/contexts/AppContext";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../src/components/ui";
import type {
  FawryPaymentSession,
  GeideaPaymentSession,
  PaymentSession,
} from "../../src/lib/types";

export default function PaymentScreen() {
  const { storefrontOrderId, orderId, resume, orderAccessToken: accessParam } = useLocalSearchParams<{
    storefrontOrderId: string;
    orderId: string;
    resume?: string;
    orderAccessToken?: string;
  }>();
  const { t, locale, token, contact, settings } = useApp();
  const router = useRouter();
  const provider = settings?.online_payments?.provider || "fawry";
  const [status, setStatus] = useState<string>(t("payment.preparing"));
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [hostedHtml, setHostedHtml] = useState<string | null>(null);
  const [awaitingContinue, setAwaitingContinue] = useState(false);
  const [orderAccessToken, setOrderAccessToken] = useState<string>(
    typeof accessParam === "string" ? accessParam : "",
  );
  const resumeHandled = useRef(false);

  const finishPaid = useCallback(async () => {
    await clearPendingPayment();
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
        await confirmPaymentReturn(
          provider,
          {
            ...payload,
            order_access_token: orderAccessToken,
            access: orderAccessToken,
          },
          token,
        );
      } catch {
        // Webhook may already have confirmed.
      }
    },
    [orderAccessToken, provider, token],
  );

  const markPending = useCallback(async () => {
    if (!storefrontOrderId || !orderId) {
      return;
    }
    await savePendingPayment({
      storefrontOrderId,
      orderId: String(orderId),
      provider,
      ...(orderAccessToken ? { orderAccessToken } : {}),
    });
  }, [orderAccessToken, orderId, provider, storefrontOrderId]);

  const launch = useCallback(async () => {
    if (!storefrontOrderId) {
      setError("Missing order");
      return;
    }
    if (!orderAccessToken) {
      setError(t("payment.loadFailed"));
      return;
    }
    setBusy(true);
    setError(null);
    setHostedHtml(null);
    setAwaitingContinue(false);
    try {
      await markPending();
      const { data } = await fetchPaymentSession(
        provider,
        storefrontOrderId,
        locale,
        token,
        orderAccessToken,
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
          await clearPendingPayment();
          setError(launched.reason);
          setBusy(false);
          return;
        }
        await confirmReturn({
          storefront_order_id: storefrontOrderId,
          merchantRefNumber: storefrontOrderId,
          merchantReferenceId: storefrontOrderId,
          ...(launched.orderId ? { orderId: launched.orderId } : {}),
          payload: launched.payload,
        });
        await finishPaid();
        return;
      }

      if (!isFawrySdkAvailable()) {
        await clearPendingPayment();
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
        await clearPendingPayment();
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
    markPending,
    orderAccessToken,
    provider,
    storefrontOrderId,
    t,
    token,
  ]);

  /** After a remount: reconcile with Laravel before offering Continue. */
  const reconcileResume = useCallback(async () => {
    if (!storefrontOrderId) {
      setError("Missing order");
      return;
    }
    let access = orderAccessToken;
    if (!access) {
      const pending = await loadPendingPayment();
      access = pending?.orderAccessToken ?? "";
      if (access) {
        setOrderAccessToken(access);
      }
    }
    if (!access) {
      setError(t("payment.loadFailed"));
      setAwaitingContinue(true);
      return;
    }
    setBusy(true);
    setError(null);
    setStatus(t("payment.checking"));
    try {
      await markPending();
      const { data } = await fetchPaymentSession(
        provider,
        storefrontOrderId,
        locale,
        token,
        access,
      );
      if ("already_paid" in data && data.already_paid) {
        await finishPaid();
        return;
      }
      // Optional recovery if Geidea already captured but webhook lagged.
      await confirmReturn({
        storefront_order_id: storefrontOrderId,
        merchantRefNumber: storefrontOrderId,
        merchantReferenceId: storefrontOrderId,
      });
      const again = await fetchPaymentSession(
        provider,
        storefrontOrderId,
        locale,
        token,
        access,
      );
      if ("already_paid" in again.data && again.data.already_paid) {
        await finishPaid();
        return;
      }
      setStatus(t("payment.interrupted"));
      setAwaitingContinue(true);
      setBusy(false);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
      setAwaitingContinue(true);
      setBusy(false);
    }
  }, [
    confirmReturn,
    finishPaid,
    locale,
    markPending,
    orderAccessToken,
    provider,
    storefrontOrderId,
    t,
    token,
  ]);

  useEffect(() => {
    if (resume === "1") {
      if (resumeHandled.current) {
        return;
      }
      resumeHandled.current = true;
      void reconcileResume();
      return;
    }
    void launch();
    // Launch / resume once on mount; webhook remains the fulfilment source of truth.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const onHostedMessage = async (raw: string) => {
    const parsed = parseGeideaWebViewMessage(raw);
    if (!parsed) {
      return;
    }
    setHostedHtml(null);
    if (!parsed.ok) {
      await clearPendingPayment();
      setError(parsed.reason);
      setBusy(false);
      return;
    }
    await confirmReturn({
      storefront_order_id: storefrontOrderId,
      merchantRefNumber: storefrontOrderId,
      merchantReferenceId: storefrontOrderId,
      payload: parsed.payload,
    });
    await finishPaid();
  };

  if (hostedHtml) {
    return (
      <Screen padded={false} avoidKeyboard={false}>
        <WebView
          originWhitelist={["https://*.geidea.net", "https://www.merchant.geidea.net", "https://merchant.geidea.net"]}
          source={{ html: hostedHtml, baseUrl: "https://www.merchant.geidea.net" }}
          javaScriptEnabled
          setSupportMultipleWindows={false}
          onShouldStartLoadWithRequest={(req) => {
            const url = req.url || "";
            if (url === "about:blank" || url.startsWith("data:")) {
              return true;
            }
            try {
              const host = new URL(url).hostname.toLowerCase();
              return host === "geidea.net" || host.endsWith(".geidea.net");
            } catch {
              return false;
            }
          }}
          onMessage={(event) => {
            void onHostedMessage(event.nativeEvent.data);
          }}
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
          <PrimaryButton
            label={
              awaitingContinue ? t("payment.continue") : t("payment.retry")
            }
            onPress={() => void launch()}
          />
          {orderId ? (
            <PrimaryButton
              label={t("payment.viewOrder")}
              onPress={() => {
                void clearPendingPayment();
                router.replace(`/account/orders/${orderId}`);
              }}
            />
          ) : null}
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
