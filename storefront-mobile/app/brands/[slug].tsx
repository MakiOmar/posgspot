import { useLocalSearchParams } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import { FlatList, Text } from "react-native";
import { fetchBrand, fetchProducts } from "../../src/lib/api";
import type { ProductSummary } from "../../src/lib/types";
import { useApp } from "../../src/contexts/AppContext";
import {
  ErrorBlock,
  LoadingBlock,
  ProductCard,
  Screen,
} from "../../src/components/ui";

export default function BrandScreen() {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const { locale, t } = useApp();
  const [title, setTitle] = useState(slug || "Brand");
  const [products, setProducts] = useState<ProductSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!slug) {
      return;
    }
    setLoading(true);
    try {
      const [brand, list] = await Promise.all([
        fetchBrand(slug, locale),
        fetchProducts({ brand_slug: slug, per_page: 24 }, locale),
      ]);
      setTitle(brand.data.name || slug);
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
        style={{ flex: 1 }}
        data={products}
        keyExtractor={(item) => String(item.id)}
        numColumns={2}
        columnWrapperStyle={{ justifyContent: "space-between" }}
        renderItem={({ item }) => <ProductCard product={item} />}
      />
    </Screen>
  );
}
