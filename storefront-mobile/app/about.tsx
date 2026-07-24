import { ScrollView, StyleSheet, Text, View } from "react-native";
import { useApp } from "../src/contexts/AppContext";
import { getAboutContent } from "../src/lib/content/about-content";
import { Screen } from "../src/components/ui";

export default function AboutScreen() {
  const { settings, locale } = useApp();
  const content = getAboutContent(locale);
  const brand = settings?.business_name || "Games Spot";

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={styles.pad}>
        <Text style={styles.kicker}>{content.kicker}</Text>
        <Text style={styles.title}>{brand}</Text>
        <Text style={styles.lead}>{content.lead}</Text>

        <Text style={styles.section}>{content.whoWeAreTitle}</Text>
        <Text style={styles.body}>
          {brand} {content.whoWeAreBody}
        </Text>

        <Text style={styles.section}>{content.visionTitle}</Text>
        {content.visionItems.map((item) => (
          <View key={item.title} style={styles.card}>
            <Text style={styles.cardTitle}>{item.title}</Text>
            <Text style={styles.body}>{item.text}</Text>
          </View>
        ))}

        <Text style={styles.section}>{content.teamTitle}</Text>
        {content.team.map((member) => (
          <Text key={member.name} style={styles.body}>
            {member.name} — {member.role}
          </Text>
        ))}
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pad: { padding: 16, gap: 10 },
  kicker: { color: "#888", fontWeight: "600", textTransform: "uppercase" },
  title: { fontSize: 26, fontWeight: "800" },
  lead: { fontSize: 16, lineHeight: 24, color: "#333" },
  section: { fontSize: 18, fontWeight: "800", marginTop: 12 },
  body: { lineHeight: 22, color: "#333" },
  card: {
    backgroundColor: "#fff",
    borderRadius: 10,
    padding: 12,
    gap: 4,
  },
  cardTitle: { fontWeight: "700" },
});
