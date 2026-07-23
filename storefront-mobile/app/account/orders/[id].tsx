import { useLocalSearchParams } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import { Linking, ScrollView, Text, View } from "react-native";
import { Redirect } from "expo-router";
import { fetchOrder } from "../../../src/lib/api";
import type { AccountOrderDetail } from "../../../src/lib/types";
import { useApp } from "../../../src/contexts/AppContext";
import { useCart } from "../../../src/contexts/CartContext";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../../src/components/ui";

export default function OrderDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { token, t } = useApp();
  const { addItem } = useCart();
  const [order, setOrder] = useState<AccountOrderDetail | null>(null);
  const [loading, setLoading] = useState(true);
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
      <ScrollView contentContainerStyle={{ padding: 16, gap: 10 }}>
        <Text style={{ fontSize: 20, fontWeight: "800" }}>
          {order.invoice_no || order.storefront_order_id || `#${order.id}`}
        </Text>
        <Text>Payment: {order.payment_status || "—"}</Text>
        {order.shipping_tracking_number ? (
          <Text>Tracking: {order.shipping_tracking_number}</Text>
        ) : null}
        {order.shipping_tracking_url ? (
          <PrimaryButton
            label="Open tracking"
            onPress={() => void Linking.openURL(order.shipping_tracking_url!)}
          />
        ) : null}
        {order.invoice_print_url ? (
          <PrimaryButton
            label="Invoice"
            onPress={() => void Linking.openURL(order.invoice_print_url!)}
          />
        ) : null}

        <Text style={{ fontWeight: "800", marginTop: 8 }}>Lines</Text>
        {(order.lines || []).map((line, index) => (
          <View
            key={`${line.variation_id}-${index}`}
            style={{
              backgroundColor: "#fff",
              padding: 12,
              borderRadius: 10,
            }}
          >
            <Text style={{ fontWeight: "700" }}>{line.name}</Text>
            <Text>Qty {line.quantity}</Text>
          </View>
        ))}

        {(order.digital_deliveries || []).length > 0 ? (
          <>
            <Text style={{ fontWeight: "800", marginTop: 8 }}>
              Digital deliveries
            </Text>
            {order.digital_deliveries!.map((d, i) => (
              <View
                key={i}
                style={{
                  backgroundColor: "#fff",
                  padding: 12,
                  borderRadius: 10,
                }}
              >
                <Text style={{ fontWeight: "700" }}>{d.title}</Text>
                {d.account_email ? <Text>Email: {d.account_email}</Text> : null}
                {d.account_password ? (
                  <Text>Password: {d.account_password}</Text>
                ) : null}
                {d.code ? <Text>Code: {d.code}</Text> : null}
              </View>
            ))}
          </>
        ) : null}

        <PrimaryButton
          label="Reorder"
          onPress={() => {
            void (async () => {
              for (const line of order.lines || []) {
                if (!line.variation_id) {
                  continue;
                }
                await addItem({
                  variationId: line.variation_id,
                  productId: line.product_id || line.variation_id,
                  name: line.name || "Item",
                  slug: line.slug,
                  imageUrl: line.image_url,
                  unitPrice: 0,
                  quantity: line.quantity || 1,
                });
              }
            })();
          }}
        />
      </ScrollView>
    </Screen>
  );
}
