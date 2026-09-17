import { StyleSheet, Text } from "react-native";
import { useRouter } from "expo-router";
import { useApp } from "../src/contexts/AppContext";
import { FormScrollView, PrimaryButton, Screen } from "../src/components/ui";

/**
 * Custom Bundle builder is web-first; mobile shows a short notice until the Expo picker ships.
 */
export default function CustomBundleScreen() {
  const { t, settings } = useApp();
  const router = useRouter();
  const enabled = settings?.custom_bundle?.enabled === true;

  if (!enabled) {
    return (
      <Screen>
        <Text style={styles.muted}>{t("customBundle.unavailable")}</Text>
        <PrimaryButton label={t("nav.home")} onPress={() => router.replace("/(tabs)")} />
      </Screen>
    );
  }

  return (
    <Screen padded={false} avoidKeyboard={false}>
      <FormScrollView contentContainerStyle={{ padding: 16 }} bottomInset={64}>
        <Text style={styles.badge}>{t("customBundle.badge")}</Text>
        <Text style={styles.title}>{t("customBundle.title")}</Text>
        <Text style={styles.lead}>{t("customBundle.lead")}</Text>
        <PrimaryButton
          label={t("nav.shopAll")}
          onPress={() => router.push("/products")}
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
  lead: { fontSize: 15, color: "#555", lineHeight: 22, marginBottom: 20 },
  muted: { color: "#888", marginBottom: 12 },
});
