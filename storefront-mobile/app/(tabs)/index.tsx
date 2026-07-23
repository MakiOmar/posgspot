import { useCallback, useEffect, useState } from "react";
import {
  FlatList,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { Link, Redirect } from "expo-router";
import { fetchHomepage, fetchProducts } from "../../src/lib/api";
import type { HomepageSection, ProductSummary } from "../../src/lib/types";
import { useApp } from "../../src/contexts/AppContext";
import {
  ErrorBlock,
  LoadingBlock,
  ProductCard,
  Screen,
} from "../../src/components/ui";

export default function HomeScreen() {
  const { t, locale, settings, loading: appLoading, accent } = useApp();
  const [sections, setSections] = useState<HomepageSection[]>([]);
  const [featured, setFeatured] = useState<ProductSummary[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [home, products] = await Promise.all([
        fetchHomepage(locale),
        fetchProducts({ featured: 1, per_page: 8 }, locale),
      ]);
      setSections(home.data.sections || []);
      setFeatured(products.data || []);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setLoading(false);
    }
  }, [locale, t]);

  useEffect(() => {
    void load();
  }, [load]);

  if (!appLoading && settings?.maintenance_mode) {
    return <Redirect href="/maintenance" />;
  }

  if (loading) {
    return (
      <Screen>
        <LoadingBlock />
      </Screen>
    );
  }

  if (error) {
    return (
      <Screen>
        <ErrorBlock message={error} onRetry={() => void load()} />
      </Screen>
    );
  }

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={styles.pad}>
        <Text style={styles.brand}>
          {settings?.business_name || "Games Spot"}
        </Text>
        <View style={styles.row}>
          <Link href="/search" asChild>
            <Pressable style={[styles.chip, { borderColor: accent }]}>
              <Text>{t("common.search")}</Text>
            </Pressable>
          </Link>
          <Link href="/brands" asChild>
            <Pressable style={styles.chip}>
              <Text>{t("common.brands")}</Text>
            </Pressable>
          </Link>
          <Link href="/games" asChild>
            <Pressable style={styles.chip}>
              <Text>{t("common.games")}</Text>
            </Pressable>
          </Link>
        </View>

        {sections.slice(0, 6).map((section) => (
          <View key={String(section.id)} style={styles.section}>
            <Text style={styles.sectionTitle}>{section.type.replace(/_/g, " ")}</Text>
          </View>
        ))}

        <Text style={styles.sectionTitle}>Featured</Text>
        <FlatList
          data={featured}
          keyExtractor={(item) => String(item.id)}
          numColumns={2}
          columnWrapperStyle={styles.grid}
          scrollEnabled={false}
          renderItem={({ item }) => <ProductCard product={item} />}
          ListEmptyComponent={<Text style={styles.muted}>No featured products</Text>}
        />
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pad: { padding: 16, paddingBottom: 40 },
  brand: { fontSize: 28, fontWeight: "800", marginBottom: 12 },
  row: { flexDirection: "row", flexWrap: "wrap", gap: 8, marginBottom: 16 },
  chip: {
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 8,
    backgroundColor: "#fff",
  },
  section: { marginBottom: 8 },
  sectionTitle: {
    fontSize: 18,
    fontWeight: "700",
    marginTop: 8,
    marginBottom: 10,
    textTransform: "capitalize",
  },
  grid: { justifyContent: "space-between" },
  muted: { color: "#777" },
});
