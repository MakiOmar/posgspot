import { useCallback, useEffect, useState } from "react";
import { FlatList, Pressable, Text } from "react-native";
import { Link, Redirect } from "expo-router";
import { fetchOrders } from "../../../src/lib/api";
import type { AccountOrder } from "../../../src/lib/types";
import { useApp } from "../../../src/contexts/AppContext";
import {
  ErrorBlock,
  LoadingBlock,
  Screen,
} from "../../../src/components/ui";

export default function OrdersScreen() {
  const { token, t } = useApp();
  const [orders, setOrders] = useState<AccountOrder[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!token) {
      return;
    }
    setLoading(true);
    try {
      const { data } = await fetchOrders(token);
      setOrders(data || []);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setLoading(false);
    }
  }, [token, t]);

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

  return (
    <Screen>
      {error ? <ErrorBlock message={error} onRetry={() => void load()} /> : null}
      <FlatList
        style={{ flex: 1 }}
        data={orders}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <Link href={`/account/orders/${item.id}`} asChild>
            <Pressable
              style={{
                backgroundColor: "#fff",
                padding: 14,
                borderRadius: 10,
                marginBottom: 8,
              }}
            >
              <Text style={{ fontWeight: "800" }}>
                {item.invoice_no || item.storefront_order_id || `#${item.id}`}
              </Text>
              <Text>
                {item.payment_status || "—"} ·{" "}
                {item.final_total != null
                  ? `${Number(item.final_total).toFixed(2)} EGP`
                  : ""}
              </Text>
            </Pressable>
          </Link>
        )}
        ListEmptyComponent={<Text>No orders yet</Text>}
      />
    </Screen>
  );
}
