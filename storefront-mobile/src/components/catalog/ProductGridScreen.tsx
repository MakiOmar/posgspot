import type { ReactNode } from "react";
import {
  ActivityIndicator,
  FlatList,
  StyleSheet,
  Text,
  View,
  type StyleProp,
  type ViewStyle,
} from "react-native";
import { ProductListToolbar } from "./ProductListToolbar";
import { ProductCard } from "./ProductCard";
import { ErrorBlock, LoadingBlock } from "../ui";
import { useProductList } from "../../lib/use-product-list";
import type { ContentLocale } from "../../lib/types";
import { useApp } from "../../contexts/AppContext";

type Props = {
  locale: ContentLocale;
  mode?: "products" | "search";
  searchQ?: string;
  categorySlug?: string;
  brandSlug?: string;
  featured?: boolean;
  /** Optional title above the toolbar */
  title?: string;
  /** Optional field rendered above the toolbar (e.g. search input) */
  header?: ReactNode;
  /** Hide sort / stock toolbar (rare) */
  hideToolbar?: boolean;
  padded?: boolean;
  listStyle?: StyleProp<ViewStyle>;
  contentContainerStyle?: StyleProp<ViewStyle>;
};

/**
 * Shared catalog grid driven by `useProductList` — shop / products / category / brand / search.
 */
export function ProductGridScreen({
  locale,
  mode = "products",
  searchQ,
  categorySlug,
  brandSlug,
  featured,
  title,
  header,
  hideToolbar,
  padded = true,
  listStyle,
  contentContainerStyle,
}: Props) {
  const { t } = useApp();
  const list = useProductList({
    locale,
    mode,
    searchQ,
    categorySlug,
    brandSlug,
    featured,
  });

  return (
    <View style={[styles.body, padded && styles.padded]}>
      {title ? <Text style={styles.title}>{title}</Text> : null}
      {header}
      {!hideToolbar ? (
        <ProductListToolbar
          sort={list.sort}
          inStockOnly={list.inStockOnly}
          onSortChange={list.setSort}
          onInStockChange={list.setInStockOnly}
        />
      ) : null}
      {list.loading && !list.products.length ? (
        <LoadingBlock />
      ) : list.error && !list.products.length ? (
        <ErrorBlock
          message={list.error === "error" ? t("common.error") : list.error}
          onRetry={list.reload}
        />
      ) : (
        <FlatList
          style={[styles.list, listStyle]}
          data={list.products}
          keyExtractor={(item) => String(item.id)}
          numColumns={2}
          columnWrapperStyle={styles.grid}
          contentContainerStyle={[styles.listPad, contentContainerStyle]}
          renderItem={({ item }) => <ProductCard product={item} />}
          onEndReached={list.loadMore}
          onEndReachedThreshold={0.4}
          ListEmptyComponent={
            list.loading ? null : (
              <Text style={styles.empty}>{t("common.empty")}</Text>
            )
          }
          ListFooterComponent={
            list.loadingMore ? (
              <ActivityIndicator style={{ marginVertical: 16 }} />
            ) : null
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  body: { flex: 1 },
  padded: { paddingHorizontal: 16 },
  title: { fontSize: 20, fontWeight: "800", marginBottom: 12, marginTop: 4 },
  list: { flex: 1 },
  listPad: { paddingBottom: 16 },
  grid: { justifyContent: "space-between" },
  empty: { textAlign: "center", color: "#666", marginTop: 24 },
});
