import { useCallback, useEffect, useState } from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { Link, useRouter } from "expo-router";
import { fetchRewardPoints } from "../../src/lib/api";
import { useApp } from "../../src/contexts/AppContext";
import { PrimaryButton, Screen } from "../../src/components/ui";
import { useRtl } from "../../src/lib/rtl";

export default function AccountScreen() {
  const { t, token, displayName, signOut, locale, setLocale, accent, settings } =
    useApp();
  const router = useRouter();
  const { row, textAlign, writingDirection } = useRtl();
  const [points, setPoints] = useState<number | null>(null);

  const loadPoints = useCallback(async () => {
    if (!token || settings?.reward_points?.enabled === false) {
      setPoints(null);
      return;
    }
    try {
      const { data } = await fetchRewardPoints(token);
      const balance = Number(
        data.balance ?? data.points ?? data.available ?? 0,
      );
      setPoints(Number.isFinite(balance) ? balance : 0);
    } catch {
      setPoints(null);
    }
  }, [token, settings]);

  useEffect(() => {
    void loadPoints();
  }, [loadPoints]);

  return (
    <Screen>
      <Text style={[styles.title, { textAlign, writingDirection }]}>
        {token ? displayName || t("nav.account") : t("account.guest")}
      </Text>

      <View style={[styles.langRow, { flexDirection: row }]}>
        <Pressable
          style={{
            ...styles.lang,
            ...(locale === "en" ? { backgroundColor: accent } : {}),
          }}
          onPress={() => setLocale("en")}
        >
          <Text style={locale === "en" ? styles.langActive : undefined}>EN</Text>
        </Pressable>
        <Pressable
          style={{
            ...styles.lang,
            ...(locale === "ar" ? { backgroundColor: accent } : {}),
          }}
          onPress={() => setLocale("ar")}
        >
          <Text style={locale === "ar" ? styles.langActive : undefined}>AR</Text>
        </Pressable>
      </View>

      {!token ? (
        <>
          <PrimaryButton
            label={t("common.login")}
            onPress={() => router.push("/login")}
          />
          <View style={{ height: 10 }} />
          <PrimaryButton
            label={t("common.register")}
            onPress={() => router.push("/register")}
          />
          <View style={{ height: 10 }} />
          <Link
            href="/forgot-password"
            style={[styles.link, { textAlign, writingDirection }]}
          >
            {t("auth.forgotPassword")}
          </Link>
        </>
      ) : (
        <>
          {points != null && settings?.reward_points?.enabled !== false ? (
            <View style={styles.pointsCard}>
              <Text
                style={[styles.pointsLabel, { textAlign, writingDirection }]}
              >
                {t("account.rewardPoints")}
              </Text>
              <Text
                style={[styles.pointsValue, { textAlign, writingDirection }]}
              >
                {points}
              </Text>
            </View>
          ) : null}
          <PrimaryButton
            label={t("account.profile")}
            onPress={() => router.push("/account/profile")}
          />
          <View style={{ height: 10 }} />
          <PrimaryButton
            label={t("common.orders")}
            onPress={() => router.push("/account/orders")}
          />
          <View style={{ height: 10 }} />
          <PrimaryButton
            label={t("common.logout")}
            onPress={() => void signOut()}
          />
        </>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  title: { fontSize: 24, fontWeight: "800", marginBottom: 16 },
  langRow: { gap: 8, marginBottom: 16 },
  lang: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 8,
    backgroundColor: "#e8e8e8",
  },
  langActive: { color: "#fff", fontWeight: "700" },
  pointsCard: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 14,
    marginBottom: 12,
  },
  pointsLabel: { color: "#666", marginBottom: 4 },
  pointsValue: { fontSize: 28, fontWeight: "800" },
  link: { fontSize: 16, color: "#222", paddingVertical: 4 },
});
