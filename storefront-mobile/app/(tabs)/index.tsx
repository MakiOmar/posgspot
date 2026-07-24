import { useCallback, useEffect, useState } from "react";
import { Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { Link, Redirect } from "expo-router";
import { fetchHomepage } from "../../src/lib/api";
import type { HomepageSection } from "../../src/lib/types";
import { useApp } from "../../src/contexts/AppContext";
import { HomepageSections } from "../../src/components/home/HomepageSections";
import { ErrorBlock, LoadingBlock, Screen } from "../../src/components/ui";

export default function HomeScreen() {
  const { t, locale, settings, loading: appLoading, accent } = useApp();
  const [sections, setSections] = useState<HomepageSection[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const home = await fetchHomepage(locale);
      setSections(home.data.sections || []);
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

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={styles.pad}>
        <View style={styles.header}>
          <Text style={styles.brand}>
            {settings?.business_name || "Games Spot"}
          </Text>
          <View style={styles.row}>
            <Link href="/search" asChild>
              <Pressable style={{ ...styles.chip, borderColor: accent }}>
                <Text style={styles.chipText}>{t("common.search")}</Text>
              </Pressable>
            </Link>
            <Link href="/brands" asChild>
              <Pressable style={styles.chip}>
                <Text style={styles.chipText}>{t("common.brands")}</Text>
              </Pressable>
            </Link>
            <Link href="/games" asChild>
              <Pressable style={styles.chip}>
                <Text style={styles.chipText}>{t("common.games")}</Text>
              </Pressable>
            </Link>
          </View>
        </View>

        {loading ? <LoadingBlock /> : null}
        {error ? (
          <ErrorBlock message={error} onRetry={() => void load()} />
        ) : null}

        {!loading && !error ? (
          <HomepageSections sections={sections} locale={locale} />
        ) : null}
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pad: { paddingBottom: 40 },
  header: { paddingHorizontal: 16, paddingTop: 8, marginBottom: 8 },
  brand: { fontSize: 28, fontWeight: "800", marginBottom: 12, color: "#111" },
  row: { flexDirection: "row", flexWrap: "wrap", gap: 8, marginBottom: 8 },
  chip: {
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 8,
    backgroundColor: "#fff",
  },
  chipText: { fontWeight: "600", color: "#222" },
});
