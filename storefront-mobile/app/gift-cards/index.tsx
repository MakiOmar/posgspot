import { useCallback, useEffect, useState } from "react";
import { FlatList, Text } from "react-native";
import { useRouter } from "expo-router";
import { fetchCardCategories } from "../../src/lib/api";
import { useApp } from "../../src/contexts/AppContext";
import { useCart } from "../../src/contexts/CartContext";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../src/components/ui";

export default function GiftCardsScreen() {
  const { locale, t } = useApp();
  const { addItem } = useCart();
  const router = useRouter();
  const [cards, setCards] = useState<
    Array<{ id: number; name?: string; title?: string; price?: number }>
  >([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await fetchCardCategories(locale);
      const list = Array.isArray(data)
        ? data
        : ((data as { categories?: unknown[] })?.categories as typeof cards) ||
          [];
      setCards(list as typeof cards);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setLoading(false);
    }
  }, [locale, t]);

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
      {error ? <ErrorBlock message={error} onRetry={() => void load()} /> : null}
      <FlatList
        data={cards}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => {
          const title = item.title || item.name || `Card #${item.id}`;
          const price = Number(item.price || 0);
          return (
            <PrimaryButton
              label={`${title} — ${price.toFixed(2)} EGP`}
              onPress={() => {
                void addItem({
                  variationId: item.id,
                  productId: item.id,
                  name: title,
                  unitPrice: price,
                  quantity: 1,
                  digital: {
                    kind: "card",
                    card_category_id: item.id,
                    line_key: `card|category:${item.id}`,
                    title,
                    price,
                  },
                }).then(() => router.push("/(tabs)/cart"));
              }}
            />
          );
        }}
        ItemSeparatorComponent={() => <Text>{"\n"}</Text>}
        ListEmptyComponent={<Text>No gift cards</Text>}
      />
    </Screen>
  );
}
