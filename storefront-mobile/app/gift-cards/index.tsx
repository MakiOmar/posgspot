import { useCallback, useEffect, useState } from "react";
import { FlatList, StyleSheet, Text, View } from "react-native";
import { useRouter } from "expo-router";
import {
  checkDigitalCardStock,
  fetchCardCategories,
} from "../../src/lib/api";
import type { DigitalSkus } from "../../src/lib/types";
import { useApp } from "../../src/contexts/AppContext";
import { useCart } from "../../src/contexts/CartContext";
import { RemoteImage } from "../../src/components/RemoteImage";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../src/components/ui";
import { toast } from "../../src/lib/toast";

type CardCategory = {
  id: number;
  name?: string;
  title?: string;
  price?: number | string;
  poster_image?: string | null;
};

export default function GiftCardsScreen() {
  const { locale, t } = useApp();
  const { addItem } = useCart();
  const router = useRouter();
  const [cards, setCards] = useState<CardCategory[]>([]);
  const [skus, setSkus] = useState<DigitalSkus | null>(null);
  const [pendingId, setPendingId] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await fetchCardCategories(locale);
      setCards(data.categories || []);
      setSkus(data.skus || null);
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
      <Text style={styles.lead}>{t("digital.giftCardsLead")}</Text>
      {error ? <ErrorBlock message={error} onRetry={() => void load()} /> : null}
      <FlatList
        style={{ flex: 1 }}
        data={cards}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => {
          const title = item.title || item.name || `Card #${item.id}`;
          const price = Number(item.price || 0);
          return (
            <View style={styles.card}>
              <RemoteImage
                uri={item.poster_image}
                style={styles.image}
              />
              <Text style={styles.title}>{title}</Text>
              <Text style={styles.price}>{price.toFixed(2)} EGP</Text>
              <PrimaryButton
                label={
                  pendingId === item.id
                    ? t("common.loading")
                    : t("common.addToCart")
                }
                disabled={pendingId !== null}
                onPress={() => {
                  void (async () => {
                    const sku = skus?.gift_card;
                    if (!sku) {
                      toast.error(t("digital.skuMissing"));
                      return;
                    }
                    if (!Number.isFinite(price) || price <= 0) {
                      toast.error(t("digital.unavailable"));
                      return;
                    }
                    setPendingId(item.id);
                    try {
                      await checkDigitalCardStock(item.id, locale);
                      await addItem({
                        variationId: sku.variation_id,
                        productId: sku.product_id,
                        name: title,
                        imageUrl: item.poster_image || sku.image_url,
                        unitPrice: price,
                        quantity: 1,
                        digital: {
                          kind: "card",
                          card_category_id: item.id,
                          line_key: `card|category:${item.id}`,
                          title,
                          price,
                        },
                      });
                      toast.success(t("digital.addedToCart"));
                      router.push("/(tabs)/cart");
                    } catch (e) {
                      toast.error(
                        e instanceof Error
                          ? e.message
                          : t("digital.stockFailed"),
                      );
                    } finally {
                      setPendingId(null);
                    }
                  })();
                }}
              />
            </View>
          );
        }}
        ItemSeparatorComponent={() => <View style={{ height: 10 }} />}
        ListEmptyComponent={<Text>{t("digital.noGiftCards")}</Text>}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  lead: { color: "#666", marginBottom: 12, lineHeight: 20 },
  card: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 14,
    gap: 8,
  },
  image: {
    width: "100%",
    height: 140,
    borderRadius: 8,
    backgroundColor: "#eee",
  },
  title: { fontWeight: "800", fontSize: 16 },
  price: { color: "#333" },
});
