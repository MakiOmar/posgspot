import { useEffect, useMemo, useRef, useState } from "react";
import { StyleSheet, Text, View } from "react-native";
import { useLocalSearchParams, useRouter } from "expo-router";
import { useApp } from "../../src/contexts/AppContext";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../src/components/ui";
import { downloadInvoicePdf, invoiceViewUrl } from "../../src/lib/invoice";
import { toast } from "../../src/lib/toast";

/**
 * Invoice download screen — generates a PDF and opens the system share/save sheet.
 * Kept as a route for deep links / older navigation; order screens call download directly.
 */
export default function InvoiceScreen() {
  const { t } = useApp();
  const router = useRouter();
  const params = useLocalSearchParams<{
    url?: string | string[];
    name?: string | string[];
  }>();
  const raw = Array.isArray(params.url) ? params.url[0] : params.url;
  const name = Array.isArray(params.name) ? params.name[0] : params.name;
  const viewUrl = useMemo(() => (raw ? invoiceViewUrl(raw) : null), [raw]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const started = useRef(false);

  const runDownload = async () => {
    if (!raw) {
      setError(t("account.invoiceUnavailable"));
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const result = await downloadInvoicePdf(raw, name);
      if (result === "invalid") {
        setError(t("account.invoiceUnavailable"));
        return;
      }
      if (result === "unavailable") {
        toast.error(t("account.invoiceShareUnavailable"));
        return;
      }
      toast.success(t("account.invoiceDownloaded"));
      if (router.canGoBack()) {
        router.back();
      }
    } catch {
      setError(t("common.error"));
    } finally {
      setBusy(false);
    }
  };

  useEffect(() => {
    if (!viewUrl || started.current) {
      return;
    }
    started.current = true;
    void runDownload();
    // Auto-start once when a valid invoice URL is present.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [viewUrl]);

  if (!viewUrl) {
    return (
      <Screen>
        <ErrorBlock message={t("account.invoiceUnavailable")} />
      </Screen>
    );
  }

  return (
    <Screen>
      {error ? (
        <ErrorBlock message={error} onRetry={() => void runDownload()} />
      ) : busy ? (
        <LoadingBlock />
      ) : (
        <View style={styles.box}>
          <Text style={styles.hint}>{t("account.invoiceDownloadHint")}</Text>
          <PrimaryButton
            label={t("account.downloadInvoice")}
            onPress={() => void runDownload()}
          />
        </View>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  box: { gap: 16, paddingTop: 8 },
  hint: { fontSize: 15, color: "#444", lineHeight: 22 },
});
