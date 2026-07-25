import { useCallback, useEffect, useState } from "react";
import { ScrollView, StyleSheet } from "react-native";
import { fetchHomepage } from "../../src/lib/api";
import type { HomepageSection } from "../../src/lib/types";
import { useApp } from "../../src/contexts/AppContext";
import { HomepageSections } from "../../src/components/home/HomepageSections";
import { StorefrontHeader } from "../../src/components/StorefrontHeader";
import { ErrorBlock, LoadingBlock, Screen } from "../../src/components/ui";

export default function HomeScreen() {
  const { t, locale } = useApp();
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

  return (
    <Screen padded={false}>
      <StorefrontHeader />
      <ScrollView contentContainerStyle={styles.pad}>
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
});
