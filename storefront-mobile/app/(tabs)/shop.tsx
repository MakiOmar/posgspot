import { ActivityIndicator, FlatList, StyleSheet, View } from "react-native";
import { useApp } from "../../src/contexts/AppContext";
import { StorefrontHeader } from "../../src/components/StorefrontHeader";
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

export default function ShopScreen() {
  const { locale, t } = useApp();
  const list = useProductList({ locale });

  return (
    <Screen padded={false}>
      <StorefrontHeader />
      <View style={styles.body}>
        <ProductListToolbar
          sort={list.sort}
          inStockOnly={list.inStockOnly}
          onSortChange={list.setSort}
          onInStockChange={list.setInStockOnly}
        />
        {list.loading ? (
          <LoadingBlock />
        ) : list.error ? (
          <ErrorBlock message={list.error === "error" ? t("common.error") : list.error} onRetry={list.reload} />
        ) : (
          <FlatList
            style={styles.list}
            data={list.products}
            keyExtractor={(item) => String(item.id)}
            numColumns={2}
            columnWrapperStyle={styles.grid}
            contentContainerStyle={styles.listPad}
            renderItem={({ item }) => <ProductCard product={item} />}
            onEndReached={list.loadMore}
            onEndReachedThreshold={0.4}
            ListFooterComponent={
              list.loadingMore ? <ActivityIndicator style={{ marginVertical: 16 }} /> : null
            }
          />
        )}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  body: { flex: 1, paddingHorizontal: 16 },
  list: { flex: 1 },
  listPad: { paddingBottom: 16 },
  grid: { justifyContent: "space-between" },
});
