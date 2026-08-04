import { useCallback, useEffect, useState } from "react";
import { ScrollView, StyleSheet, Text, View } from "react-native";
import { Redirect } from "expo-router";
import { Ionicons } from "@expo/vector-icons";
import { fetchRewardPoints } from "../../src/lib/api";
import type { RewardPointsBalance } from "../../src/lib/types";
import { useApp } from "../../src/contexts/AppContext";
import {
  ErrorBlock,
  LoadingBlock,
  Screen,
} from "../../src/components/ui";
import { useRtl } from "../../src/lib/rtl";

export default function RewardsScreen() {
  const { token, t, accent } = useApp();
  const { textAlign, writingDirection, row } = useRtl();
  const [data, setData] = useState<RewardPointsBalance | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!token) {
      setLoading(false);
      return;
    }
    setLoading(true);
    try {
      const res = await fetchRewardPoints(token);
      setData(res.data);
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

  const points = Number(data?.available ?? data?.balance ?? data?.points ?? 0);
  const value = Number(data?.value ?? 0);

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={styles.pad}>
        {error ? (
          <ErrorBlock message={error} onRetry={() => void load()} />
        ) : null}

        {/* Simple branded hero (no competitor artwork) */}
        <View style={[styles.hero, { backgroundColor: `${accent}22` }]}>
          <Ionicons name="trophy" size={56} color={accent} />
        </View>

        <View style={[styles.cards, { flexDirection: row }]}>
          <View style={styles.card}>
            <Text style={styles.cardValue}>{points.toFixed(1)}</Text>
            <Text style={styles.cardLabel}>{t("rewards.points")}</Text>
          </View>
          <Text style={styles.tilde}>~</Text>
          <View style={styles.card}>
            <Text style={styles.cardValue}>{value.toFixed(1)}</Text>
            <Text style={styles.cardLabel}>{t("rewards.egp")}</Text>
          </View>
        </View>

        <Text style={[styles.summary, { textAlign, writingDirection }]}>
          {t("rewards.summary", {
            points: points.toFixed(1),
            value: value.toFixed(1),
          })}
        </Text>

        <View style={styles.infoCard}>
          <Text style={[styles.infoTitle, { textAlign, writingDirection }]}>
            {t("rewards.howToUse")}
          </Text>
          <Text style={[styles.infoBody, { textAlign, writingDirection }]}>
            {t("rewards.howToUseBody")}
          </Text>
          <Text
            style={[
              styles.infoTitle,
              { textAlign, writingDirection, marginTop: 18 },
            ]}
          >
            {t("rewards.howToEarn")}
          </Text>
          <Text style={[styles.infoBody, { textAlign, writingDirection }]}>
            {t("rewards.howToEarnBody")}
          </Text>
        </View>
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pad: { padding: 16, paddingBottom: 40 },
  hero: {
    height: 140,
    borderRadius: 16,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 20,
  },
  cards: {
    alignItems: "center",
    justifyContent: "center",
    gap: 10,
    marginBottom: 12,
  },
  card: {
    flex: 1,
    backgroundColor: "#fff",
    borderRadius: 14,
    paddingVertical: 20,
    alignItems: "center",
    borderWidth: 1,
    borderColor: "#eee",
  },
  cardValue: { fontSize: 28, fontWeight: "800", color: "#111" },
  cardLabel: { marginTop: 4, color: "#666", fontWeight: "600" },
  tilde: { fontSize: 22, color: "#999", fontWeight: "700" },
  summary: { color: "#555", marginBottom: 20, lineHeight: 20 },
  infoCard: {
    backgroundColor: "#fff",
    borderRadius: 14,
    padding: 18,
    borderWidth: 1,
    borderColor: "#eee",
  },
  infoTitle: { fontWeight: "800", fontSize: 16, marginBottom: 8 },
  infoBody: { color: "#555", lineHeight: 20 },
});
