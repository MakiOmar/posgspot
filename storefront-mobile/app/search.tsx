import { useState } from "react";
import { ActivityIndicator, FlatList, StyleSheet, TextInput } from "react-native";
import { useApp } from "../src/contexts/AppContext";
import { ProductListToolbar } from "../src/components/catalog/ProductListToolbar";
import { ErrorBlock, ProductCard, Screen } from "../src/components/ui";
import { useProductList } from "../src/lib/use-product-list";

export default function SearchScreen() {
  const { locale, t } = useApp();
  const [q, setQ] = useState("");
  const list = useProductList({ locale, mode: "search", searchQ: q });

  return (
    <Screen>
      <TextInput
        style={styles.input}
        placeholder={t("common.search")}
        value={q}
        onChangeText={setQ}
        autoFocus
        autoCapitalize="none"
      />
      <ProductListToolbar
        sort={list.sort}
        inStockOnly={list.inStockOnly}
        onSortChange={list.setSort}
        onInStockChange={list.setInStockOnly}
      />
      {list.error ? <ErrorBlock message={t("common.error")} onRetry={list.reload} /> : null}
      <FlatList
        style={styles.list}
        data={list.products}
        keyExtractor={(item) => String(item.id)}
        numColumns={2}
        columnWrapperStyle={styles.grid}
        renderItem={({ item }) => <ProductCard product={item} />}
        ListFooterComponent={
          list.loading || list.loadingMore ? (
            <ActivityIndicator style={{ margin: 12 }} />
          ) : null
        }
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
  grid: { justifyContent: "space-between" },
});
