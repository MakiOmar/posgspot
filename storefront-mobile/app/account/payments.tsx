import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { Link, Redirect, useRouter } from "expo-router";
import {
  fetchAccountCoupons,
  fetchOrders,
  fetchRewardPoints,
  fetchUsedAccountCoupons,
  saveAccountCoupon,
} from "../../src/lib/api";
import type {
  AccountOrder,
  RewardPointsBalance,
  SavedCoupon,
  UsedCoupon,
} from "../../src/lib/types";
import { useApp } from "../../src/contexts/AppContext";
import { LabeledInput } from "../../src/components/LabeledInput";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../src/components/ui";
import { toast } from "../../src/lib/toast";
import { useRtl } from "../../src/lib/rtl";

type HubTab = "methods" | "payments" | "credits";
type PayFilter = "" | "due" | "paid" | "pending" | "failed";
type CouponSub = "add" | "unused" | "used";

const PER_PAGE = 20;

export default function PaymentsHubScreen() {
  const { token, t, accent, settings } = useApp();
  const router = useRouter();
  const { row, textAlign, writingDirection } = useRtl();
  const [hub, setHub] = useState<HubTab>("methods");
  const [payFilter, setPayFilter] = useState<PayFilter>("");
  const [couponSub, setCouponSub] = useState<CouponSub>("add");
  const [code, setCode] = useState("");
  const [saving, setSaving] = useState(false);

  const [orders, setOrders] = useState<AccountOrder[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [ordersLoading, setOrdersLoading] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [ordersError, setOrdersError] = useState<string | null>(null);

  const [rewards, setRewards] = useState<RewardPointsBalance | null>(null);
  const [unused, setUnused] = useState<SavedCoupon[]>([]);
  const [used, setUsed] = useState<UsedCoupon[]>([]);
  const [creditsLoading, setCreditsLoading] = useState(false);
  const [creditsError, setCreditsError] = useState<string | null>(null);

  const loadOrders = useCallback(
    async (nextPage: number, append: boolean, status: PayFilter) => {
      if (!token) return;
      if (append) setLoadingMore(true);
      else setOrdersLoading(true);
      try {
        const { data, meta } = await fetchOrders(token, {
          page: nextPage,
          perPage: PER_PAGE,
          paymentStatus: status || undefined,
        });
        setOrders((prev) => (append ? [...prev, ...(data || [])] : data || []));
        setPage(Number(meta.current_page ?? nextPage));
        setLastPage(Number(meta.last_page ?? 1));
        setOrdersError(null);
      } catch (e) {
        setOrdersError(e instanceof Error ? e.message : t("common.error"));
      } finally {
        setOrdersLoading(false);
        setLoadingMore(false);
      }
    },
    [token, t],
  );

  const loadCredits = useCallback(async () => {
    if (!token) return;
    setCreditsLoading(true);
    try {
      const [rp, unusedRes, usedRes] = await Promise.all([
        fetchRewardPoints(token).catch(() => ({ data: null })),
        fetchAccountCoupons(token),
        fetchUsedAccountCoupons(token),
      ]);
      setRewards(rp.data);
      setUnused(unusedRes.data || []);
      setUsed(usedRes.data || []);
      setCreditsError(null);
    } catch (e) {
      setCreditsError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setCreditsLoading(false);
    }
  }, [token, t]);

  useEffect(() => {
    if (hub === "payments") {
      void loadOrders(1, false, payFilter);
    }
  }, [hub, payFilter, loadOrders]);

  useEffect(() => {
    if (hub === "credits") {
      void loadCredits();
    }
  }, [hub, loadCredits]);

  if (!token) {
    return <Redirect href="/login" />;
  }

  const hubTabs: { id: HubTab; label: string }[] = [
    { id: "methods", label: t("account.paymentMethods") },
    { id: "payments", label: t("account.paymentsTab") },
    { id: "credits", label: t("account.creditsCoupons") },
  ];

  const payTabs: { id: PayFilter; label: string }[] = [
    { id: "", label: t("account.payAll") },
    { id: "due", label: t("account.payDue") },
    { id: "paid", label: t("account.payPaid") },
    { id: "pending", label: t("account.payPending") },
    { id: "failed", label: t("account.payFailed") },
  ];

  const couponTabs: { id: CouponSub; label: string }[] = [
    { id: "add", label: t("account.couponAdd") },
    { id: "unused", label: t("account.couponUnused") },
    { id: "used", label: t("account.couponUsed") },
  ];

  return (
    <Screen padded={false}>
      <View style={[styles.tabRow, { flexDirection: row }]}>
        {hubTabs.map((tab) => {
          const active = hub === tab.id;
          return (
            <Pressable
              key={tab.id}
              onPress={() => setHub(tab.id)}
              style={[
                styles.tab,
                active && { borderBottomColor: accent, borderBottomWidth: 2 },
              ]}
            >
              <Text
                style={[
                  styles.tabText,
                  { textAlign, writingDirection },
                  active && { color: accent, fontWeight: "800" },
                ]}
              >
                {tab.label}
              </Text>
            </Pressable>
          );
        })}
      </View>

      {hub === "methods" ? (
        <ScrollView contentContainerStyle={styles.pad}>
          <PrimaryButton
            label={t("account.addPaymentMethod")}
            onPress={() => toast.info(t("account.paymentMethodsSoon"))}
          />
          <Text style={[styles.empty, { textAlign, writingDirection }]}>
            {t("account.paymentMethodsEmpty")}
          </Text>
        </ScrollView>
      ) : null}

      {hub === "payments" ? (
        <>
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={[styles.filterRow, { flexDirection: row }]}
          >
            {payTabs.map((tab) => {
              const active = payFilter === tab.id;
              return (
                <Pressable
                  key={tab.id || "all"}
                  onPress={() => setPayFilter(tab.id)}
                  style={[
                    styles.chip,
                    active && { backgroundColor: accent, borderColor: accent },
                  ]}
                >
                  <Text style={{ color: active ? "#fff" : "#333", fontWeight: "700" }}>
                    {tab.label}
                  </Text>
                </Pressable>
              );
            })}
          </ScrollView>
          {ordersLoading ? (
            <LoadingBlock />
          ) : (
            <FlatList
              style={{ flex: 1 }}
              data={orders}
              keyExtractor={(item) => String(item.id)}
              onEndReached={() => {
                if (!loadingMore && page < lastPage) {
                  void loadOrders(page + 1, true, payFilter);
                }
              }}
              onEndReachedThreshold={0.4}
              ListFooterComponent={
                loadingMore ? <ActivityIndicator style={{ margin: 16 }} /> : null
              }
              ListHeaderComponent={
                ordersError ? (
                  <ErrorBlock
                    message={ordersError}
                    onRetry={() => void loadOrders(1, false, payFilter)}
                  />
                ) : null
              }
              renderItem={({ item }) => (
                <Link href={`/account/orders/${item.id}`} asChild>
                  <Pressable style={styles.card}>
                    <Text style={styles.title}>
                      {item.invoice_no || item.storefront_order_id || `#${item.id}`}
                    </Text>
                    <Text style={styles.meta}>
                      {item.payment_status || "—"} ·{" "}
                      {item.final_total != null
                        ? `${Number(item.final_total).toFixed(2)} EGP`
                        : ""}
                    </Text>
                  </Pressable>
                </Link>
              )}
              ListEmptyComponent={
                <Text style={styles.empty}>{t("account.noOrders")}</Text>
              }
            />
          )}
        </>
      ) : null}

      {hub === "credits" ? (
        <ScrollView contentContainerStyle={styles.pad}>
          {creditsLoading ? <LoadingBlock /> : null}
          {creditsError ? (
            <ErrorBlock message={creditsError} onRetry={() => void loadCredits()} />
          ) : null}
          {rewards?.enabled !== false ? (
            <View style={styles.card}>
              <Text style={styles.title}>{t("account.rewardPoints")}</Text>
              <Text style={styles.meta}>
                {Number(rewards?.available ?? rewards?.balance ?? rewards?.points ?? 0).toFixed(1)}
                {settings?.currency?.code ? ` · ${settings.currency.code}` : ""}
              </Text>
            </View>
          ) : null}

          <View style={[styles.tabRow, { flexDirection: row }]}>
            {couponTabs.map((tab) => {
              const active = couponSub === tab.id;
              return (
                <Pressable
                  key={tab.id}
                  onPress={() => setCouponSub(tab.id)}
                  style={[
                    styles.tab,
                    active && { borderBottomColor: accent, borderBottomWidth: 2 },
                  ]}
                >
                  <Text
                    style={[
                      styles.tabText,
                      active && { color: accent, fontWeight: "800" },
                    ]}
                  >
                    {tab.label}
                  </Text>
                </Pressable>
              );
            })}
          </View>

          {couponSub === "add" ? (
            <View>
              <LabeledInput
                label={t("account.couponCode")}
                value={code}
                onChangeText={setCode}
                autoCapitalize="characters"
              />
              <PrimaryButton
                label={saving ? t("common.loading") : t("account.couponSave")}
                disabled={saving}
                onPress={() => {
                  const trimmed = code.trim();
                  if (!trimmed) return;
                  setSaving(true);
                  void saveAccountCoupon(token, trimmed)
                    .then(() => {
                      toast.success(t("account.couponSaved"));
                      setCode("");
                      setCouponSub("unused");
                      void loadCredits();
                    })
                    .catch((e) =>
                      toast.error(
                        e instanceof Error ? e.message : t("common.error"),
                      ),
                    )
                    .finally(() => setSaving(false));
                }}
              />
            </View>
          ) : null}

          {couponSub === "unused"
            ? unused.map((item) => (
                <View key={item.id} style={styles.card}>
                  <Text style={styles.title}>{item.code}</Text>
                  <Text style={styles.meta}>{item.label || item.name || ""}</Text>
                </View>
              ))
            : null}
          {couponSub === "unused" && unused.length === 0 && !creditsLoading ? (
            <Text style={styles.empty}>{t("account.couponEmptyUnused")}</Text>
          ) : null}

          {couponSub === "used"
            ? used.map((item) => (
                <Pressable
                  key={item.id}
                  style={styles.card}
                  onPress={() => {
                    if (item.order_id) {
                      router.push(`/account/orders/${item.order_id}`);
                    }
                  }}
                >
                  <Text style={styles.title}>{item.code || "—"}</Text>
                  <Text style={styles.meta}>
                    #{item.order_id}
                    {item.discount_amount != null
                      ? ` · ${t("account.couponSavedAmount")} ${Number(item.discount_amount).toFixed(2)}`
                      : ""}
                  </Text>
                </Pressable>
              ))
            : null}
          {couponSub === "used" && used.length === 0 && !creditsLoading ? (
            <Text style={styles.empty}>{t("account.couponEmptyUsed")}</Text>
          ) : null}
        </ScrollView>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  tabRow: { paddingHorizontal: 8, borderBottomWidth: 1, borderBottomColor: "#eee" },
  tab: { flex: 1, paddingVertical: 12, paddingHorizontal: 6, alignItems: "center" },
  tabText: { fontSize: 13, color: "#555" },
  filterRow: { paddingHorizontal: 12, paddingVertical: 10, gap: 8 },
  chip: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: "#ddd",
    marginRight: 8,
  },
  pad: { padding: 16, paddingBottom: 48 },
  card: {
    backgroundColor: "#fff",
    padding: 14,
    borderRadius: 10,
    marginBottom: 8,
  },
  title: { fontWeight: "800", marginBottom: 4 },
  meta: { color: "#555" },
  empty: { textAlign: "center", color: "#666", marginTop: 24 },
});
