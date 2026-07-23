import { useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import { Text, View } from "react-native";
import { fetchDigitalGame } from "../../src/lib/api";
import { useApp } from "../../src/contexts/AppContext";
import { useCart } from "../../src/contexts/CartContext";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../src/components/ui";

export default function GameDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { locale, t, settings } = useApp();
  const { addItem } = useCart();
  const router = useRouter();
  const [game, setGame] = useState<Record<string, unknown> | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await fetchDigitalGame(Number(id), locale);
      setGame(data as Record<string, unknown>);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setLoading(false);
    }
  }, [id, locale, t]);

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
  if (error || !game) {
    return (
      <Screen>
        <ErrorBlock message={error || undefined} onRetry={() => void load()} />
      </Screen>
    );
  }

  const title = String(game.title || game.name || `Game #${id}`);
  const price = Number(game.price || game.primary_price || 0);
  const variationId = Number(settings?.digital && (settings as { digital?: { primary_product_id?: number } }).digital?.primary_product_id) || 0;

  return (
    <Screen>
      <Text style={{ fontSize: 22, fontWeight: "800", marginBottom: 8 }}>
        {title}
      </Text>
      <Text style={{ marginBottom: 16 }}>{price.toFixed(2)} EGP</Text>
      <View style={{ gap: 10 }}>
        <PrimaryButton
          label="Add primary account"
          onPress={() => {
            void addItem({
              variationId: variationId || Number(id),
              productId: variationId || Number(id),
              name: `${title} (Primary)`,
              unitPrice: price,
              quantity: 1,
              digital: {
                kind: "game",
                game_id: Number(id),
                type: "primary",
                platform: "5",
                line_key: `ps5_primary_stock|game:${id}`,
                title: `${title} (Primary)`,
                price,
              },
            }).then(() => router.push("/(tabs)/cart"));
          }}
        />
        <PrimaryButton
          label="Add secondary account"
          onPress={() => {
            void addItem({
              variationId: variationId || Number(id),
              productId: variationId || Number(id),
              name: `${title} (Secondary)`,
              unitPrice: price,
              quantity: 1,
              digital: {
                kind: "game",
                game_id: Number(id),
                type: "secondary",
                platform: "5",
                line_key: `ps5_secondary_stock|game:${id}`,
                title: `${title} (Secondary)`,
                price,
              },
            }).then(() => router.push("/(tabs)/cart"));
          }}
        />
      </View>
    </Screen>
  );
}
