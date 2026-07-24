import { useLocalSearchParams } from "expo-router";
import { ScrollView, StyleSheet, Text, View } from "react-native";
import { useApp } from "../../src/contexts/AppContext";
import {
  getPrivacyPolicy,
  getReturnPolicy,
  getTermsAndConditions,
  type LegalDocument,
} from "../../src/lib/content/legal-content";
import { Screen } from "../../src/components/ui";

function pickDoc(slug: string | undefined, locale: "en" | "ar"): LegalDocument {
  if (slug === "privacy") {
    return getPrivacyPolicy(locale);
  }
  if (slug === "return") {
    return getReturnPolicy(locale);
  }
  return getTermsAndConditions(locale);
}

export default function LegalScreen() {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const { locale } = useApp();
  const doc = pickDoc(slug, locale);

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={styles.pad}>
        <Text style={styles.title}>{doc.title}</Text>
        {doc.lastUpdated ? (
          <Text style={styles.meta}>{doc.lastUpdated}</Text>
        ) : null}
        {doc.intro ? <Text style={styles.body}>{doc.intro}</Text> : null}
        {doc.sections.map((section) => (
          <View key={section.title} style={styles.section}>
            <Text style={styles.sectionTitle}>{section.title}</Text>
            {(section.paragraphs || []).map((p) => (
              <Text key={p.slice(0, 40)} style={styles.body}>
                {p}
              </Text>
            ))}
            {(section.list || []).map((item) => (
              <Text key={item.slice(0, 40)} style={styles.listItem}>
                • {item}
              </Text>
            ))}
          </View>
        ))}
        {doc.footerNote ? (
          <Text style={styles.meta}>{doc.footerNote}</Text>
        ) : null}
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pad: { padding: 16, gap: 10 },
  title: { fontSize: 22, fontWeight: "800" },
  meta: { color: "#888", marginBottom: 4 },
  body: { lineHeight: 22, color: "#333", marginBottom: 6 },
  section: { marginTop: 8 },
  sectionTitle: { fontWeight: "800", marginBottom: 6, fontSize: 16 },
  listItem: { lineHeight: 22, color: "#333", marginBottom: 4 },
});
