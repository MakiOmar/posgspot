import { Stack } from "expo-router";
import { ActivityIndicator, FlatList, StyleSheet, TextInput, View } from "react-native";
import { useState } from "react";
import { useApp } from "../../src/contexts/AppContext";
import {
  ProductListToolbar,
} from "../../src/components/catalog/ProductListToolbar";
import {
  ErrorBlock,
  LoadingBlock,
  ProductCard,
  Screen,
} from "../../src/components/ui";
import { useProductList } from "../../src/lib/use-product-list";

export default function ProductsIndexScreen() {
  const { locale, t } = useApp();
  const [q, setQ] = useState("");
  const list = useProductList({ locale });

  return (
    <Screen>
      <Stack.Screen options={{ title: t("nav.shop") }} />
      <TextInput
        value={q}
        onChangeText={setQ}
        placeholder={t("common.search")}
        style={styles.input}
        autoCapitalize="none"
        onSubmitEditing={() => {
          // local filter for quick find within loaded set; toolbar drives API
        }}
      />
      <ProductListToolbar
        sort={list.sort}
        inStockOnly={list.inStockOnly}
        onSortChange={list.setSort}
        onInStockChange={list.setInStockOnly}
      />
      {list.loading ? (
        <LoadingBlock />
      ) : list.error ? (
        <ErrorBlock message={t("common.error")} onRetry={list.reload} />
      ) : (
        <FlatList
          style={styles.list}
          data={
            q.trim()
              ? list.products.filter((p) =>
                  p.name.toLowerCase().includes(q.trim().toLowerCase()),
                )
              : list.products
          }
          keyExtractor={(item) => String(item.id)}
          numColumns={2}
          columnWrapperStyle={styles.grid}
          renderItem={({ item }) => <ProductCard product={item} />}
          onEndReached={list.loadMore}
          onEndReachedThreshold={0.4}
          ListFooterComponent={
            list.loadingMore ? <ActivityIndicator style={{ margin: 12 }} /> : null
          }
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
  list: { flex: 1 },
  grid: { justifyContent: "space-between" },
});
