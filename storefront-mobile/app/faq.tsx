import { ScrollView, StyleSheet, Text, View } from "react-native";
import { useApp } from "../src/contexts/AppContext";
import { getFaqEntries } from "../src/lib/content/faq-content";
import { Screen } from "../src/components/ui";

export default function FaqScreen() {
  const { locale } = useApp();
  const items = getFaqEntries(locale);

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={styles.pad}>
        {items.map((item) => (
          <View key={item.question} style={styles.item}>
            <Text style={styles.q}>{item.question}</Text>
            <Text style={styles.a}>{item.answer}</Text>
          </View>
        ))}
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pad: { padding: 16 },
  item: {
    backgroundColor: "#fff",
    borderRadius: 10,
    padding: 14,
    marginBottom: 10,
  },
  q: { fontWeight: "800", marginBottom: 6 },
  a: { lineHeight: 20, color: "#333" },
});
