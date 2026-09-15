import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  StyleSheet,
  Text,
} from "react-native";
import { Redirect } from "expo-router";
import { fetchOrders } from "../../../src/lib/api";
import type { AccountOrder } from "../../../src/lib/types";
import { useApp } from "../../../src/contexts/AppContext";
import { UnderlineTabs } from "../../../src/components/account/UnderlineTabs";
import { OrderListCard } from "../../../src/components/account/OrderListCard";
import { ErrorBlock, LoadingBlock, Screen } from "../../../src/components/ui";

type PayFilter = "all" | "due" | "paid" | "pending" | "failed";

const PER_PAGE = 20;

export default function PaymentsListScreen() {
  const { token, t } = useApp();
  const [payFilter, setPayFilter] = useState<PayFilter>("all");
  const [orders, setOrders] = useState<AccountOrder[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(
    async (nextPage: number, append: boolean, status: PayFilter) => {
      if (!token) return;
      if (append) setLoadingMore(true);
      else setLoading(true);
      try {
        const { data, meta } = await fetchOrders(token, {
          page: nextPage,
          perPage: PER_PAGE,
          paymentStatus: status === "all" ? undefined : status,
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
    void load(1, false, payFilter);
  }, [load, payFilter]);

  if (!token) {
    return <Redirect href="/login" />;
  }

  return (
    <Screen padded={false}>
      <UnderlineTabs
        value={payFilter}
        onChange={setPayFilter}
        items={[
          { id: "all", label: t("account.payAll") },
          { id: "due", label: t("account.payDue") },
          { id: "paid", label: t("account.payPaid") },
          { id: "pending", label: t("account.payPending") },
          { id: "failed", label: t("account.payFailed") },
        ]}
      />
      {loading ? (
        <LoadingBlock />
      ) : (
        <FlatList
          style={{ flex: 1 }}
          contentContainerStyle={styles.list}
          data={orders}
          keyExtractor={(item) => String(item.id)}
          onEndReached={() => {
            if (!loadingMore && page < lastPage) {
              void load(page + 1, true, payFilter);
            }
          }}
          onEndReachedThreshold={0.4}
          ListFooterComponent={
            loadingMore ? <ActivityIndicator style={{ margin: 16 }} /> : null
          }
          ListHeaderComponent={
            error ? (
              <ErrorBlock
                message={error}
                onRetry={() => void load(1, false, payFilter)}
              />
            ) : null
          }
          renderItem={({ item }) => <OrderListCard order={item} />}
          ListEmptyComponent={
            <Text style={styles.empty}>{t("account.noOrders")}</Text>
          }
        />
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  list: { padding: 16, paddingBottom: 48 },
  empty: { textAlign: "center", color: "#666", marginTop: 24 },
});
