import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  StyleSheet,
  Text,
} from "react-native";
import { Redirect, useRouter } from "expo-router";
import { fetchOrders } from "../../../src/lib/api";
import type { AccountOrder } from "../../../src/lib/types";
import { useApp } from "../../../src/contexts/AppContext";
import { OrderListCard } from "../../../src/components/account/OrderListCard";
import {
  ErrorBlock,
  LoadingBlock,
  Screen,
} from "../../../src/components/ui";
import { openInvoice } from "../../../src/lib/invoice";
import { toast } from "../../../src/lib/toast";

const PER_PAGE = 20;

function isPaid(status: string | undefined): boolean {
  return (status ?? "").trim().toLowerCase() === "paid";
}

export default function OrdersScreen() {
  const { token, t, accent } = useApp();
  const router = useRouter();
  const [orders, setOrders] = useState<AccountOrder[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(
    async (nextPage: number, append: boolean) => {
      if (!token) {
        setLoading(false);
        return;
      }
      if (append) setLoadingMore(true);
      else setLoading(true);
      try {
        const { data, meta } = await fetchOrders(token, {
          page: nextPage,
          perPage: PER_PAGE,
        });
        setOrders((prev) => (append ? [...prev, ...(data || [])] : data || []));
        setPage(Number(meta.current_page ?? nextPage));
        setLastPage(Number(meta.last_page ?? 1));
        setError(null);
      } catch (e) {
        setError(e instanceof Error ? e.message : t("common.error"));
      } finally {
        setLoading(false);
        setLoadingMore(false);
      }
    },
    [token, t],
  );

  useEffect(() => {
    void load(1, false);
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
      {error ? (
        <ErrorBlock message={error} onRetry={() => void load(1, false)} />
      ) : null}
      <FlatList
        style={{ flex: 1 }}
        data={orders}
        keyExtractor={(item) => String(item.id)}
        onEndReached={() => {
          if (!loadingMore && page < lastPage) {
            void load(page + 1, true);
          }
        }}
        onEndReachedThreshold={0.4}
        ListFooterComponent={
          loadingMore ? <ActivityIndicator style={{ margin: 16 }} /> : null
        }
        renderItem={({ item }) => (
          <OrderListCard
            order={item}
            footer={
              isPaid(item.payment_status) && item.invoice_print_url ? (
                <Pressable
                  style={[styles.invoiceBtn, { borderColor: accent }]}
                  onPress={() => {
                    if (!openInvoice(router, item.invoice_print_url!)) {
                      toast.error(t("account.invoiceUnavailable"));
                    }
                  }}
                >
                  <Text style={{ color: accent, fontWeight: "700" }}>
                    {t("account.invoice")}
                  </Text>
                </Pressable>
              ) : null
            }
          />
        )}
        ListEmptyComponent={
          <Text style={styles.empty}>{t("account.noOrders")}</Text>
        }
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  invoiceBtn: {
    marginTop: 10,
    alignSelf: "flex-start",
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderWidth: 1,
    borderRadius: 8,
  },
  empty: { textAlign: "center", color: "#666", marginTop: 24 },
});
