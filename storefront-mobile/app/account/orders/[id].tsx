import { useCallback, useEffect, useState } from "react";
import {
  Linking,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { Redirect, useLocalSearchParams, useRouter } from "expo-router";
import { fetchOrder, fetchOrderInvoiceUrl } from "../../../src/lib/api";
import type { AccountOrderDetail } from "../../../src/lib/types";
import { useApp } from "../../../src/contexts/AppContext";
import { useCart } from "../../../src/contexts/CartContext";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../../src/components/ui";
import { toast } from "../../../src/lib/toast";

function isPaidOrder(paymentStatus: string | undefined): boolean {
  return (paymentStatus ?? "").trim().toLowerCase() === "paid";
}

export default function OrderDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { token, t } = useApp();
  const { addItem } = useCart();
  const router = useRouter();
  const [order, setOrder] = useState<AccountOrderDetail | null>(null);
  const [invoiceUrl, setInvoiceUrl] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [reordering, setReordering] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!token || !id) {
      return;
    }
    setLoading(true);
    try {
      const { data } = await fetchOrder(token, Number(id));
      setOrder(data);
      setError(null);
      let printUrl = data.invoice_print_url || null;
      if (isPaidOrder(data.payment_status) && !printUrl) {
        try {
          const invoice = await fetchOrderInvoiceUrl(token, Number(id));
          printUrl = invoice.data.invoice_print_url;
        } catch {
          // Invoice endpoint may be unavailable.
        }
      }
      setInvoiceUrl(isPaidOrder(data.payment_status) ? printUrl : null);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setLoading(false);
    }
  }, [token, id, t]);

  useEffect(() => {
    void load();
  }, [load]);

  if (!token) {
    return <Redirect href="/login" />;
  }

  if (loading) {
    return (
      <Screen>
        <LoadingBlock />
      </Screen>
    );
  }

  if (error || !order) {
    return (
      <Screen>
        <ErrorBlock message={error || undefined} onRetry={() => void load()} />
      </Screen>
    );
  }

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={styles.pad}>
        <Text style={styles.title}>
          {order.invoice_no || order.storefront_order_id || `#${order.id}`}
        </Text>
        <Text>
          {t("account.payment")}: {order.payment_status || "—"}
        </Text>
        {order.final_total != null ? (
          <Text>
            {t("cart.total")}: {Number(order.final_total).toFixed(2)} EGP
          </Text>
        ) : null}
        {order.shipping_tracking_number ? (
          <Text>
            {t("account.tracking")}: {order.shipping_tracking_number}
          </Text>
        ) : null}
        {order.shipping_tracking_url ? (
          <PrimaryButton
            label={t("account.openTracking")}
            onPress={() => void Linking.openURL(order.shipping_tracking_url!)}
          />
        ) : null}
        {invoiceUrl ? (
          <PrimaryButton
            label={t("account.invoice")}
            onPress={() => void Linking.openURL(invoiceUrl)}
          />
        ) : null}

        <Text style={styles.section}>{t("account.lines")}</Text>
        {(order.lines || []).map((line, index) => (
          <View
            key={`${line.variation_id}-${index}`}
            style={styles.card}
          >
            <Text style={styles.cardTitle}>
              {line.product_name || line.name || `Product #${line.product_id}`}
            </Text>
            {line.variation_name ? (
              <Text style={styles.meta}>{line.variation_name}</Text>
            ) : null}
            <Text style={styles.meta}>
              Qty {line.quantity} ·{" "}
              {Number(line.unit_price_inc_tax || 0).toFixed(2)} EGP
            </Text>
          </View>
        ))}

        {(order.digital_deliveries || []).length > 0 ? (
          <>
            <Text style={styles.section}>{t("account.digitalDeliveries")}</Text>
            {order.digital_deliveries!.map((d, i) => (
              <View key={i} style={styles.card}>
                <Text style={styles.cardTitle}>{d.title}</Text>
                {d.account_email ? (
                  <Text>Email: {d.account_email}</Text>
                ) : null}
                {d.account_password ? (
                  <Text>Password: {d.account_password}</Text>
                ) : null}
                {d.code ? <Text>Code: {d.code}</Text> : null}
              </View>
            ))}
          </>
        ) : null}

        <PrimaryButton
          label={reordering ? t("common.loading") : t("account.reorder")}
          disabled={reordering || !(order.lines || []).length}
          onPress={() => {
            void (async () => {
              setReordering(true);
              try {
                for (const line of order.lines || []) {
                  if (!line.variation_id || !line.quantity) {
                    continue;
                  }
                  await addItem({
                    variationId: line.variation_id,
                    productId: line.product_id || line.variation_id,
                    name:
                      line.product_name ||
                      line.name ||
                      `Product #${line.product_id}`,
                    slug: line.slug || undefined,
                    imageUrl: line.image_url,
                    unitPrice: Number(line.unit_price_inc_tax || 0),
                    quantity: line.quantity,
                  });
                }
                toast.success(t("account.reorderSuccess"));
                router.push("/(tabs)/cart");
              } finally {
                setReordering(false);
              }
            })();
          }}
        />
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pad: { padding: 16, gap: 10 },
  title: { fontSize: 20, fontWeight: "800" },
  section: { fontWeight: "800", marginTop: 8 },
  card: {
    backgroundColor: "#fff",
    padding: 12,
    borderRadius: 10,
  },
  cardTitle: { fontWeight: "700" },
  meta: { color: "#666", marginTop: 2 },
});
