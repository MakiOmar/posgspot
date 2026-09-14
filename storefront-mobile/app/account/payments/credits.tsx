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
import { RewardPointsPanel } from "../../../src/components/account/RewardPointsPanel";
import { UnderlineTabs } from "../../../src/components/account/UnderlineTabs";
import { LabeledInput } from "../../../src/components/LabeledInput";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../../src/components/ui";
import { useRtl } from "../../../src/lib/rtl";
import { toast } from "../../../src/lib/toast";

type CouponSub = "add" | "unused" | "used";

export default function CreditsCouponsScreen() {
  const { token, t } = useApp();
  const { textAlign, writingDirection } = useRtl();
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

  if (loading) {
    return (
      <Screen>
        <LoadingBlock />
      </Screen>
    );
  }

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={styles.pad}>
        {error ? <ErrorBlock message={error} onRetry={() => void load()} /> : null}

        <RewardPointsPanel data={rewards} />

        <Text style={[styles.sectionTitle, { textAlign, writingDirection }]}>
          {t("account.creditsCoupons")}
        </Text>

        <View style={styles.infoCard}>
          <View style={styles.tabsBleed}>
          <UnderlineTabs
            value={couponSub}
            onChange={setCouponSub}
            items={[
              { id: "add", label: t("account.couponAdd") },
              { id: "unused", label: t("account.couponUnused") },
              { id: "used", label: t("account.couponUsed") },
            ]}
          />
          </View>

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
                <View key={item.id} style={styles.couponRow}>
                  <Text style={[styles.couponCode, { textAlign, writingDirection }]}>
                    {item.code}
                  </Text>
                  {item.label || item.name ? (
                    <Text style={[styles.couponMeta, { textAlign, writingDirection }]}>
                      {item.label || item.name}
                    </Text>
                  ) : null}
                </View>
              ))
            : null}
          {couponSub === "unused" && unused.length === 0 ? (
            <Text style={[styles.empty, { textAlign }]}>
              {t("account.couponEmptyUnused")}
            </Text>
          ) : null}

          {couponSub === "used"
            ? used.map((item) => (
                <Pressable
                  key={item.id}
                  style={styles.couponRow}
                  onPress={() => {
                    if (item.order_id) {
                      router.push(`/account/orders/${item.order_id}`);
                    }
                  }}
                >
                  <Text style={[styles.couponCode, { textAlign, writingDirection }]}>
                    {item.code || "—"}
                  </Text>
                  <Text style={[styles.couponMeta, { textAlign, writingDirection }]}>
                    #{item.order_id}
                    {item.discount_amount != null
                      ? ` · ${t("account.couponSavedAmount")} ${Number(item.discount_amount).toFixed(2)}`
                      : ""}
                  </Text>
                </Pressable>
              ))
            : null}
          {couponSub === "used" && used.length === 0 ? (
            <Text style={[styles.empty, { textAlign }]}>
              {t("account.couponEmptyUsed")}
            </Text>
          ) : null}
        </View>
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pad: { padding: 16, paddingBottom: 40 },
  sectionTitle: {
    fontWeight: "800",
    fontSize: 16,
    marginTop: 28,
    marginBottom: 12,
    color: "#111",
  },
  infoCard: {
    backgroundColor: "#fff",
    borderRadius: 14,
    padding: 18,
    borderWidth: 1,
    borderColor: "#eee",
    overflow: "hidden",
  },
  tabsBleed: { marginHorizontal: -18, marginTop: -6 },
  form: { marginTop: 8, gap: 8 },
  couponRow: {
    paddingVertical: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "#eee",
  },
  couponCode: { fontWeight: "800", fontSize: 16, color: "#111" },
  couponMeta: { color: "#555", marginTop: 4, lineHeight: 20 },
  empty: { color: "#555", marginTop: 16, lineHeight: 20 },
});
