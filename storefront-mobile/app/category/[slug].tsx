import { useLocalSearchParams } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import { ActivityIndicator, FlatList, StyleSheet, Text } from "react-native";
import { fetchCategory } from "../../src/lib/api";
import { useApp } from "../../src/contexts/AppContext";
import { ProductListToolbar } from "../../src/components/catalog/ProductListToolbar";
import {
  ErrorBlock,
  LoadingBlock,
  ProductCard,
  Screen,
} from "../../src/components/ui";
import { useProductList } from "../../src/lib/use-product-list";
import { paramString } from "../../src/lib/product-path";

export default function CategoryScreen() {
  const params = useLocalSearchParams<{ slug: string | string[] }>();
  const slug = paramString(params.slug) || "";
  const { locale, t } = useApp();
  const [title, setTitle] = useState(slug);
  const list = useProductList({ locale, categorySlug: slug || undefined });

  const loadTitle = useCallback(async () => {
    if (!slug) return;
    try {
      const cat = await fetchCategory(slug, locale);
      setTitle(cat.data.name || slug);
    } catch {
      setTitle(slug);
    }
  }, [slug, locale]);

  useEffect(() => {
    void loadTitle();
  }, [loadTitle]);

  return (
    <Screen>
      <Text style={styles.title}>{title}</Text>
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
          data={list.products}
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
  title: { fontSize: 20, fontWeight: "800", marginBottom: 12 },
  list: { flex: 1 },
  grid: { justifyContent: "space-between" },
});
