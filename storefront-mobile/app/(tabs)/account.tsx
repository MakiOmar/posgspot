import { Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { useRouter, type Href } from "expo-router";
import { Ionicons } from "@expo/vector-icons";
import { useApp } from "../../src/contexts/AppContext";
import { AccountMenuRow } from "../../src/components/account/AccountMenuRow";
import { PrimaryButton, Screen } from "../../src/components/ui";
import { useRtl } from "../../src/lib/rtl";

export default function AccountScreen() {
  const {
    t,
    token,
    displayName,
    contact,
    signOut,
    locale,
    setLocale,
    accent,
    settings,
  } = useApp();
  const router = useRouter();
  const { row, textAlign, writingDirection } = useRtl();
  const rewardsEnabled = settings?.reward_points?.enabled !== false;

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={styles.pad}>
        {!token ? (
          <>
            <View style={styles.avatarWrap}>
              <View style={[styles.avatar, { borderColor: accent }]}>
                <Ionicons name="person" size={48} color={accent} />
              </View>
              <Text style={[styles.name, { textAlign, writingDirection }]}>
                {t("account.guest")}
              </Text>
              <Text style={[styles.hint, { textAlign, writingDirection }]}>
                {t("account.signInHint")}
              </Text>
            </View>
            <PrimaryButton
              label={t("common.login")}
              onPress={() => router.push("/login")}
            />
            <View style={{ height: 10 }} />
            <PrimaryButton
              label={t("common.register")}
              onPress={() => router.push("/register")}
            />
          </>
        ) : (
          <>
            <View style={styles.avatarWrap}>
              <View style={[styles.avatar, { borderColor: accent }]}>
                <Ionicons name="person" size={48} color={accent} />
              </View>
              <Text style={[styles.name, { textAlign, writingDirection }]}>
                {displayName || contact?.email || t("nav.account")}
              </Text>
              {contact?.email_verified === false ? (
                <Pressable
                  onPress={() =>
                    router.push({
                      pathname: "/verify-email",
                      params: { email: contact.email || "" },
                    } as unknown as Href)
                  }
                >
                  <Text style={{ color: accent, marginTop: 6 }}>
                    {t("account.verifyEmail")} · {contact.email}
                  </Text>
                </Pressable>
              ) : null}
            </View>

            <AccountMenuRow
              icon="person-outline"
              label={t("account.personalInfo")}
              onPress={() => router.push("/account/profile")}
            />
            <AccountMenuRow
              icon="location-outline"
              label={t("account.myAddresses")}
              onPress={() => router.push("/account/address" as unknown as Href)}
            />
            <AccountMenuRow
              icon="document-text-outline"
              label={t("account.orders")}
              onPress={() => router.push("/account/orders")}
            />
            <AccountMenuRow
              icon="heart-outline"
              label={t("account.wishlist")}
              onPress={() => router.push("/(tabs)/wishlist")}
            />
            {rewardsEnabled ? (
              <AccountMenuRow
                icon="star-outline"
                label={t("account.rewardPoints")}
                onPress={() => router.push("/account/rewards" as unknown as Href)}
              />
            ) : null}
            <AccountMenuRow
              icon="log-out-outline"
              label={t("account.logout")}
              onPress={() => void signOut()}
              danger
            />
          </>
        )}

        <View style={[styles.langRow, { flexDirection: row }]}>
          <Pressable
            style={{
              ...styles.lang,
              ...(locale === "en" ? { backgroundColor: accent } : {}),
            }}
            onPress={() => setLocale("en")}
          >
            <Text style={locale === "en" ? styles.langActive : undefined}>
              EN
            </Text>
          </Pressable>
          <Pressable
            style={{
              ...styles.lang,
              ...(locale === "ar" ? { backgroundColor: accent } : {}),
            }}
            onPress={() => setLocale("ar")}
          >
            <Text style={locale === "ar" ? styles.langActive : undefined}>
              AR
            </Text>
          </Pressable>
        </View>
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pad: { padding: 20, paddingBottom: 40 },
  avatarWrap: { alignItems: "center", marginBottom: 24, marginTop: 8 },
  avatar: {
    width: 96,
    height: 96,
    borderRadius: 48,
    borderWidth: 2,
    backgroundColor: "#fff",
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 12,
  },
  name: { fontSize: 22, fontWeight: "800", color: "#111" },
  hint: { color: "#888", marginTop: 6, marginBottom: 16 },
  langRow: { gap: 8, marginTop: 28, justifyContent: "center" },
  lang: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 8,
    backgroundColor: "#eee",
  },
  langActive: { color: "#fff", fontWeight: "700" },
});
