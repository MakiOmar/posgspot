import { StyleSheet, Text, View } from "react-native";
import { Link } from "expo-router";
import { useApp } from "../src/contexts/AppContext";
import { FormScrollView, Screen } from "../src/components/ui";

/** Coming-soon placeholder for on-site repair truck booking. */
export default function RepairTruckRequestScreen() {
  const { t, accent } = useApp();

  return (
    <Screen padded={false} avoidKeyboard={false}>
      <FormScrollView contentContainerStyle={{ padding: 16 }} bottomInset={64}>
        <Text style={[styles.badge, { color: accent }]}>{t("repairTruck.badge")}</Text>
        <Text style={styles.title}>{t("repairTruck.title")}</Text>
        <Text style={styles.lead}>{t("repairTruck.lead")}</Text>
        <Text style={styles.hint}>{t("repairTruck.hint")}</Text>
        <View style={styles.actions}>
          <Link href="/repair-status" style={[styles.link, { color: accent }]}>
            {t("nav.trackRepairs")}
          </Link>
          <Link href="/contact" style={[styles.link, { color: accent }]}>
            {t("contact.leaveMessage")}
          </Link>
        </View>
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
    marginBottom: 8,
  },
  title: { fontSize: 24, fontWeight: "800", color: "#111", marginBottom: 12 },
  lead: { fontSize: 16, color: "#444", lineHeight: 24, marginBottom: 12 },
  hint: { fontSize: 14, color: "#777", lineHeight: 20, marginBottom: 20 },
  actions: { gap: 12 },
  link: { fontSize: 16, fontWeight: "700" },
});
