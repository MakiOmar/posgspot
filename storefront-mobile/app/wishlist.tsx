import { useCallback, useEffect, useState } from "react";
import { FlatList, Text } from "react-native";
import { Redirect } from "expo-router";
import {
  fetchWishlist,
} from "../src/lib/api";
import type { ProductSummary } from "../src/lib/types";
import { useApp } from "../src/contexts/AppContext";
import {
  ErrorBlock,
  LoadingBlock,
  ProductCard,
  Screen,
} from "../src/components/ui";

export default function WishlistScreen() {
  const { token, t, locale } = useApp();
  const [items, setItems] = useState<ProductSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!token) {
      return;
    }
    setLoading(true);
    try {
      const { data } = await fetchWishlist(token);
      setItems(data.items || []);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setLoading(false);
    }
  }, [token, t]);

  useEffect(() => {
    void load();
  }, [load]);

  if (!token) {
    return <Redirect href="/login" />;
  }

  if (loading) {
    return (
      <Screen>
        <LoadingBlock />
      </Screen>
    );
  }

  return (
    <Screen>
      {/* locale kept for future guest merge UX */}
      <Text style={{ marginBottom: 8 }}>{items.length} items · {locale}</Text>
      {error ? <ErrorBlock message={error} onRetry={() => void load()} /> : null}
      <FlatList
        data={items}
        keyExtractor={(item) => String(item.id)}
        numColumns={2}
        columnWrapperStyle={{ justifyContent: "space-between" }}
        renderItem={({ item }) => <ProductCard product={item} />}
        ListEmptyComponent={<Text>No wishlist items</Text>}
      />
    </Screen>
  );
}
