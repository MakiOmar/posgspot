import { useCallback, useEffect, useState } from "react";
import { Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { Redirect, useRouter } from "expo-router";
import {
  fetchAccountCoupons,
  fetchRewardPoints,
  fetchUsedAccountCoupons,
  saveAccountCoupon,
} from "../../../src/lib/api";
import type {
  RewardPointsBalance,
  SavedCoupon,
  UsedCoupon,
} from "../../../src/lib/types";
import { useApp } from "../../../src/contexts/AppContext";
import { UnderlineTabs } from "../../../src/components/account/UnderlineTabs";
import { LabeledInput } from "../../../src/components/LabeledInput";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../../src/components/ui";
import { toast } from "../../../src/lib/toast";

type CouponSub = "add" | "unused" | "used";

export default function CreditsCouponsScreen() {
  const { token, t, settings } = useApp();
  const router = useRouter();
  const [couponSub, setCouponSub] = useState<CouponSub>("add");
  const [code, setCode] = useState("");
  const [saving, setSaving] = useState(false);
  const [rewards, setRewards] = useState<RewardPointsBalance | null>(null);
  const [unused, setUnused] = useState<SavedCoupon[]>([]);
  const [used, setUsed] = useState<UsedCoupon[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!token) return;
    setLoading(true);
    try {
      const [rp, unusedRes, usedRes] = await Promise.all([
        fetchRewardPoints(token).catch(() => ({ data: null })),
        fetchAccountCoupons(token),
        fetchUsedAccountCoupons(token),
      ]);
      setRewards(rp.data);
      setUnused(unusedRes.data || []);
      setUsed(usedRes.data || []);
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

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={styles.pad}>
        {loading ? <LoadingBlock /> : null}
        {error ? <ErrorBlock message={error} onRetry={() => void load()} /> : null}

        {rewards?.enabled !== false ? (
          <View style={styles.card}>
            <Text style={styles.title}>{t("account.rewardPoints")}</Text>
            <Text style={styles.meta}>
              {Number(
                rewards?.available ?? rewards?.balance ?? rewards?.points ?? 0,
              ).toFixed(1)}
              {settings?.currency?.code ? ` · ${settings.currency.code}` : ""}
            </Text>
          </View>
        ) : null}

        <UnderlineTabs
          value={couponSub}
          onChange={setCouponSub}
          items={[
            { id: "add", label: t("account.couponAdd") },
            { id: "unused", label: t("account.couponUnused") },
            { id: "used", label: t("account.couponUsed") },
          ]}
        />

        {couponSub === "add" ? (
          <View style={styles.form}>
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
                    void load();
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
        {couponSub === "unused" && unused.length === 0 && !loading ? (
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
        {couponSub === "used" && used.length === 0 && !loading ? (
          <Text style={styles.empty}>{t("account.couponEmptyUsed")}</Text>
        ) : null}
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pad: { paddingBottom: 48 },
  form: { padding: 16 },
  card: {
    backgroundColor: "#fff",
    padding: 14,
    borderRadius: 10,
    marginHorizontal: 16,
    marginTop: 12,
  },
  title: { fontWeight: "800", marginBottom: 4 },
  meta: { color: "#555" },
  empty: { textAlign: "center", color: "#666", marginTop: 24 },
});
