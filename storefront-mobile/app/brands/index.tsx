import { useCallback, useEffect, useState } from "react";
import { FlatList, Pressable, Text } from "react-native";
import { Link } from "expo-router";
import { fetchBrands } from "../../src/lib/api";
import type { Brand } from "../../src/lib/types";
import { useApp } from "../../src/contexts/AppContext";
import { ErrorBlock, LoadingBlock, Screen } from "../../src/components/ui";

export default function BrandsScreen() {
  const { locale, t } = useApp();
  const [brands, setBrands] = useState<Brand[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await fetchBrands(locale);
      setBrands(data || []);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setLoading(false);
    }
  }, [locale, t]);

  useEffect(() => {
    void load();
  }, [load]);

  if (loading) {
    return (
      <Screen>
        <LoadingBlock />
      </Screen>
    );
  }

  return (
    <Screen>
      {error ? <ErrorBlock message={error} onRetry={() => void load()} /> : null}
      <FlatList
        style={{ flex: 1 }}
        data={brands}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <Link href={`/brands/${item.slug}`} asChild>
            <Pressable
              style={{
                backgroundColor: "#fff",
                padding: 14,
                borderRadius: 10,
                marginBottom: 8,
              }}
            >
              <Text style={{ fontWeight: "700" }}>{item.name}</Text>
            </Pressable>
          </Link>
        )}
      />
    </Screen>
  );
}
