import { StyleSheet, Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { useApp } from "../../contexts/AppContext";
import { useRtl } from "../../lib/rtl";
import type { RewardPointsBalance } from "../../lib/types";

type Props = {
  data: RewardPointsBalance | null;
};

/**
 * Shared Reward Points layout (hero + points/~EGP cards + how-to copy).
 * Used by the standalone rewards screen and Credits & Coupons.
 */
export function RewardPointsPanel({ data }: Props) {
  const { t, accent } = useApp();
  const { textAlign, writingDirection, row } = useRtl();
  const points = Number(data?.available ?? data?.balance ?? data?.points ?? 0);
  const value = Number(data?.value ?? 0);

  return (
    <>
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
    </>
  );
}

const styles = StyleSheet.create({
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
