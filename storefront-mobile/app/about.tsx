import { Text } from "react-native";
import { useApp } from "../src/contexts/AppContext";
import { Screen } from "../src/components/ui";

export default function AboutScreen() {
  const { settings, locale } = useApp();
  return (
    <Screen>
      <Text style={{ fontSize: 22, fontWeight: "800", marginBottom: 12 }}>
        {settings?.business_name || "Games Spot"}
      </Text>
      <Text style={{ lineHeight: 22 }}>
        {locale === "ar"
          ? "متجر ألعاب وإلكترونيات — تسوق عبر التطبيق بنفس كتالوج وطلبات نقاط البيع."
          : "Games and electronics retail — shop in the app on the same POS catalog and orders."}
      </Text>
    </Screen>
  );
}
