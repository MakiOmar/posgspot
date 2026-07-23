import { useLocalSearchParams } from "expo-router";
import { ScrollView, Text } from "react-native";
import { useApp } from "../../src/contexts/AppContext";
import { Screen } from "../../src/components/ui";
import { STOREFRONT_WEB_URL } from "../../src/lib/config";

export default function LegalScreen() {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const { locale } = useApp();
  const title =
    slug === "privacy"
      ? locale === "ar"
        ? "الخصوصية"
        : "Privacy"
      : slug === "return"
        ? locale === "ar"
          ? "الاسترجاع"
          : "Returns"
        : locale === "ar"
          ? "الشروط"
          : "Terms";

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={{ padding: 16 }}>
        <Text style={{ fontSize: 22, fontWeight: "800", marginBottom: 12 }}>
          {title}
        </Text>
        <Text style={{ lineHeight: 22, marginBottom: 12 }}>
          {locale === "ar"
            ? `للاطلاع على النص الكامل زر موقع المتجر: ${STOREFRONT_WEB_URL}`
            : `Full legal copy is published on the web storefront: ${STOREFRONT_WEB_URL}`}
        </Text>
      </ScrollView>
    </Screen>
  );
}
