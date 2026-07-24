import { useState } from "react";
import { FlatList, StyleSheet, TextInput } from "react-native";
import { searchProducts } from "../src/lib/api";
import type { ProductSummary } from "../src/lib/types";
import { useApp } from "../src/contexts/AppContext";
import { ErrorBlock, ProductCard, Screen } from "../src/components/ui";

export default function SearchScreen() {
  const { locale, t } = useApp();
  const [q, setQ] = useState("");
  const [products, setProducts] = useState<ProductSummary[]>([]);
  const [error, setError] = useState<string | null>(null);

  const run = async (query: string) => {
    setQ(query);
    if (query.trim().length < 2) {
      setProducts([]);
      return;
    }
    try {
      const { data } = await searchProducts(query.trim(), locale);
      setProducts(data || []);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    }
  };

  return (
    <Screen>
      <TextInput
        style={styles.input}
        placeholder={t("common.search")}
        value={q}
        onChangeText={(value) => void run(value)}
        autoFocus
        autoCapitalize="none"
      />
      {error ? <ErrorBlock message={error} /> : null}
      <FlatList
        style={styles.list}
        data={products}
        keyExtractor={(item) => String(item.id)}
        numColumns={2}
        columnWrapperStyle={{ justifyContent: "space-between" }}
        renderItem={({ item }) => <ProductCard product={item} />}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  input: {
    backgroundColor: "#fff",
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "#e5e5e5",
    paddingHorizontal: 12,
    paddingVertical: 12,
    marginBottom: 12,
  },
  list: { flex: 1 },
});
