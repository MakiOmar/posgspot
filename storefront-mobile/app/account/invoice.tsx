import { useMemo, useState } from "react";
import { StyleSheet, View } from "react-native";
import { useLocalSearchParams } from "expo-router";
import { WebView } from "react-native-webview";
import * as Print from "expo-print";
import { useApp } from "../../src/contexts/AppContext";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../src/components/ui";
import { htmlForPrint, invoiceViewUrl } from "../../src/lib/invoice";
import { toast } from "../../src/lib/toast";

const HIDE_POS_PRINT_CHROME = `
  (function () {
    var style = document.createElement("style");
    style.textContent = ".no-print,#print_invoice{display:none!important}";
    document.head.appendChild(style);
    true;
  })();
`;

/**
 * In-app POS invoice: WebView preview + native print sheet.
 */
export default function InvoiceScreen() {
  const { t } = useApp();
  const params = useLocalSearchParams<{ url?: string | string[] }>();
  const raw = Array.isArray(params.url) ? params.url[0] : params.url;
  const viewUrl = useMemo(() => (raw ? invoiceViewUrl(raw) : null), [raw]);
  const [loading, setLoading] = useState(true);
  const [printing, setPrinting] = useState(false);
  const [pageError, setPageError] = useState(false);

  if (!viewUrl) {
    return (
      <Screen>
        <ErrorBlock message={t("account.invoiceUnavailable")} />
      </Screen>
    );
  }

  return (
    <Screen padded={false} avoidKeyboard={false}>
      <View style={styles.webWrap}>
        {pageError ? (
          <ErrorBlock
            message={t("account.invoiceUnavailable")}
            onRetry={() => {
              setPageError(false);
              setLoading(true);
            }}
          />
        ) : (
          <WebView
            source={{ uri: viewUrl }}
            onLoadEnd={() => setLoading(false)}
            onError={() => {
              setLoading(false);
              setPageError(true);
            }}
            injectedJavaScript={HIDE_POS_PRINT_CHROME}
            setSupportMultipleWindows={false}
            startInLoadingState
            renderLoading={() => (
              <View style={styles.overlay}>
                <LoadingBlock />
              </View>
            )}
          />
        )}
        {loading && !pageError ? (
          <View style={styles.overlay}>
            <LoadingBlock />
          </View>
        ) : null}
      </View>
      <View style={styles.bar}>
        <PrimaryButton
          label={printing ? t("common.loading") : t("account.printInvoice")}
          disabled={printing || pageError || loading}
          onPress={() => {
            void (async () => {
              setPrinting(true);
              try {
                const response = await fetch(viewUrl);
                if (!response.ok) {
                  throw new Error("invoice fetch failed");
                }
                const html = htmlForPrint(await response.text(), viewUrl);
                await Print.printAsync({ html });
              } catch {
                toast.error(t("common.error"));
              } finally {
                setPrinting(false);
              }
            })();
          }}
        />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  webWrap: { flex: 1, backgroundColor: "#fff" },
  overlay: {
    ...StyleSheet.absoluteFill,
    backgroundColor: "#F7F7F5",
    justifyContent: "center",
  },
  bar: {
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: "#F7F7F5",
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: "#ddd",
  },
});
