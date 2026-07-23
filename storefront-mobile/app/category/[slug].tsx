import { useLocalSearchParams } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import { FlatList, Text } from "react-native";
import { fetchCategory, fetchProducts } from "../../src/lib/api";
import type { ProductSummary } from "../../src/lib/types";
import { useApp } from "../../src/contexts/AppContext";
import {
  ErrorBlock,
  LoadingBlock,
  ProductCard,
  Screen,
} from "../../src/components/ui";

export default function CategoryScreen() {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const { locale, t } = useApp();
  const [title, setTitle] = useState(slug || "Category");
  const [products, setProducts] = useState<ProductSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!slug) {
      return;
    }
    setLoading(true);
    try {
      const [cat, list] = await Promise.all([
        fetchCategory(slug, locale),
        fetchProducts({ category_slug: slug, per_page: 24 }, locale),
      ]);
      setTitle(cat.data.name || slug);
      setProducts(list.data || []);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setLoading(false);
    }
  }, [slug, locale, t]);

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
      <Text style={{ fontSize: 20, fontWeight: "800", marginBottom: 12 }}>
        {title}
      </Text>
      {error ? <ErrorBlock message={error} onRetry={() => void load()} /> : null}
      <FlatList
        data={products}
        keyExtractor={(item) => String(item.id)}
        numColumns={2}
        columnWrapperStyle={{ justifyContent: "space-between" }}
        renderItem={({ item }) => <ProductCard product={item} />}
      />
    </Screen>
  );
}
