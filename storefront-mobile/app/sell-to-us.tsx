import { StyleSheet, Text } from "react-native";
import { Link, useRouter } from "expo-router";
import { useApp } from "../src/contexts/AppContext";
import { FormScrollView, PrimaryButton, Screen } from "../src/components/ui";

/**
 * Sell to us full trade-in form remains web/API-first; mobile shows gated notice + web parity links.
 */
export default function SellToUsScreen() {
  const { t, settings, token, accent } = useApp();
  const router = useRouter();
  const enabled = settings?.sell_to_us?.enabled === true;

  if (!enabled) {
    return (
      <Screen>
        <Text style={styles.muted}>{t("sellToUs.unavailable")}</Text>
        <PrimaryButton label={t("nav.home")} onPress={() => router.replace("/(tabs)")} />
      </Screen>
    );
  }

  return (
    <Screen padded={false} avoidKeyboard={false}>
      <FormScrollView contentContainerStyle={{ padding: 16 }} bottomInset={64}>
        <Text style={styles.badge}>{t("sellToUs.badge")}</Text>
        <Text style={styles.title}>{t("sellToUs.title")}</Text>
        <Text style={styles.lead}>{t("sellToUs.mobileLead")}</Text>
        {!token ? (
          <Text style={styles.muted}>
            {t("sellToUs.loginRequired")}{" "}
            <Link href={{ pathname: "/login", params: { next: "/sell-to-us" } }} style={{ color: accent }}>
              {t("common.login")}
            </Link>
          </Text>
        ) : (
          <Text style={styles.muted}>{t("sellToUs.useWebsite")}</Text>
        )}
        <PrimaryButton
          label={t("contact.leaveMessage")}
          onPress={() => router.push("/contact")}
        />
      </FormScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  badge: {
    fontSize: 12,
    fontWeight: "800",
    letterSpacing: 0.6,
    textTransform: "uppercase",
    color: "#00a88a",
    marginBottom: 8,
  },
  title: { fontSize: 22, fontWeight: "800", marginBottom: 10 },
  lead: { fontSize: 15, color: "#555", lineHeight: 22, marginBottom: 12 },
  muted: { color: "#777", marginBottom: 16, lineHeight: 20 },
});
