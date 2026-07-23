import { useCallback, useEffect, useState } from "react";
import { FlatList, StyleSheet, TextInput, View } from "react-native";
import { fetchProducts } from "../../src/lib/api";
import type { ProductSummary } from "../../src/lib/types";
import { useApp } from "../../src/contexts/AppContext";
import {
  ErrorBlock,
  LoadingBlock,
  ProductCard,
  Screen,
} from "../../src/components/ui";

export default function ShopScreen() {
  const { locale, t } = useApp();
  const [products, setProducts] = useState<ProductSummary[]>([]);
  const [q, setQ] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const { data } = await fetchProducts(
        { per_page: 24, q: q || undefined, sort: "newest" },
        locale,
      );
      setProducts(data || []);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setLoading(false);
    }
  }, [locale, q, t]);

  useEffect(() => {
    const timer = setTimeout(() => void load(), 250);
    return () => clearTimeout(timer);
  }, [load]);

  return (
    <Screen>
      <TextInput
        value={q}
        onChangeText={setQ}
        placeholder={t("common.search")}
        style={styles.input}
        autoCapitalize="none"
      />
      {loading ? (
        <LoadingBlock />
      ) : error ? (
        <ErrorBlock message={error} onRetry={() => void load()} />
      ) : (
        <FlatList
          data={products}
          keyExtractor={(item) => String(item.id)}
          numColumns={2}
          columnWrapperStyle={styles.grid}
          renderItem={({ item }) => <ProductCard product={item} />}
        />
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  input: {
    backgroundColor: "#fff",
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 12,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: "#e5e5e5",
  },
  grid: { justifyContent: "space-between" },
});
