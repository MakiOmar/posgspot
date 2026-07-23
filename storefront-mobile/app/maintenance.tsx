import { Text, View } from "react-native";
import { useApp } from "../src/contexts/AppContext";
import { Screen } from "../src/components/ui";

export default function MaintenanceScreen() {
  const { t, accent } = useApp();
  return (
    <Screen>
      <View style={{ flex: 1, justifyContent: "center", gap: 12 }}>
        <Text style={{ fontSize: 28, fontWeight: "800", color: accent }}>
          {t("maintenance.title")}
        </Text>
        <Text style={{ fontSize: 16, lineHeight: 22 }}>
          {t("maintenance.body")}
        </Text>
      </View>
    </Screen>
  );
}
