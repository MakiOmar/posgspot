import { useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import { ScrollView, StyleSheet, Text, View } from "react-native";
import {
  checkDigitalGameStock,
  fetchDigitalGame,
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

type Offer = "primary" | "secondary";
type Platform = "4" | "5";

function num(value: unknown): number {
  const n = Number(value);
  return Number.isFinite(n) ? n : 0;
}

function boolish(value: unknown): boolean {
  return value === true || value === 1 || value === "1";
}

export default function GameDetailScreen() {
  const { id, platform: platformParam } = useLocalSearchParams<{
    id: string;
    platform?: string;
  }>();
  const platform: Platform = platformParam === "5" ? "5" : "4";
  const { locale, t } = useApp();
  const { addItem } = useCart();
  const router = useRouter();
  const [game, setGame] = useState<Record<string, unknown> | null>(null);
  const [skus, setSkus] = useState<DigitalSkus | null>(null);
  const [loading, setLoading] = useState(true);
  const [pending, setPending] = useState<Offer | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await fetchDigitalGame(Number(id), locale);
      setGame(data.game);
      setSkus(data.skus);
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
  const image =
    platform === "5"
      ? String(game.ps5_image_url || game.image_url || "")
      : String(game.ps4_image_url || game.image_url || "");

  const primaryPrice = num(
    game[`ps${platform}_primary_price`] ??
      game.primary_price ??
      game.ps4_primary_price,
  );
  const secondaryPrice = num(
    game[`ps${platform}_secondary_price`] ??
      game.secondary_price ??
      game.ps4_secondary_price,
  );
  const primaryOk = boolish(
    game[`ps${platform}_primary_status`] ?? game.primary_status,
  );
  const secondaryOk = boolish(
    game[`ps${platform}_secondary_status`] ?? game.secondary_status,
  );
  const primaryStock = num(
    game[`ps${platform}_primary_stock`] ??
      game.total_primary_stock ??
      game.ps4_primary_stock,
  );
  const secondaryStock = num(
    game[`ps${platform}_secondary_stock`] ??
      game.total_secondary_stock ??
      game.ps4_secondary_stock,
  );
  const primaryInStock = primaryOk && primaryPrice > 0 && primaryStock > 0;
  const secondaryInStock =
    secondaryOk && secondaryPrice > 0 && secondaryStock > 0;

  const addOffer = async (offer: Offer) => {
    const sku = offer === "primary" ? skus?.primary : skus?.secondary;
    if (!sku) {
      toast.error(t("digital.skuMissing"));
      return;
    }
    const price = offer === "primary" ? primaryPrice : secondaryPrice;
    const stock = offer === "primary" ? primaryStock : secondaryStock;
    const offerEnabled = offer === "primary" ? primaryOk : secondaryOk;
    if (!offerEnabled || price <= 0) {
      toast.error(t("digital.unavailable"));
      return;
    }
    if (stock <= 0) {
      toast.error(t("digital.outOfStock"));
      return;
    }

    setPending(offer);
    try {
      const stockCheck = await checkDigitalGameStock(
        {
          game_id: Number(game.id || id),
          type: offer,
          platform,
        },
        locale,
      );
      const stockData = stockCheck.data as {
        is_available?: boolean;
        stock?: number | string;
      };
      const liveStock = Number(stockData?.stock ?? 0);
      if (
        stockData?.is_available === false ||
        (Number.isFinite(liveStock) && liveStock <= 0)
      ) {
        toast.error(t("digital.outOfStock"));
        return;
      }

      const label =
        offer === "primary" ? t("digital.primary") : t("digital.secondary");
      const lineTitle = `${title} (${label} · PS${platform})`;
      await addItem({
        variationId: sku.variation_id,
        productId: sku.product_id,
        name: lineTitle,
        imageUrl: image || sku.image_url,
        unitPrice: price,
        quantity: 1,
        digital: {
          kind: "game",
          game_id: Number(game.id || id),
          type: offer,
          platform,
          line_key: `ps${platform}_${offer}_stock|game:${game.id || id}`,
          title: lineTitle,
          price,
        },
      });
      toast.success(t("digital.addedToCart"));
      router.push("/(tabs)/cart");
    } catch (e) {
      toast.error(
        e instanceof Error ? e.message : t("digital.stockFailed"),
      );
    } finally {
      setPending(null);
    }
  };

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={styles.pad}>
        <RemoteImage uri={image} style={styles.image} />
        <Text style={styles.title}>{title}</Text>
        <Text style={styles.meta}>
          {t("digital.platformLabel")} PS{platform}
        </Text>

        {primaryOk && primaryPrice > 0 ? (
          <View style={styles.offer}>
            <Text style={styles.offerTitle}>
              {t("digital.primary")} — {primaryPrice.toFixed(2)} EGP
            </Text>
            <Text style={styles.meta}>
              {primaryInStock
                ? t("catalog.inStock")
                : t("catalog.outOfStock")}
            </Text>
            <PrimaryButton
              label={
                pending === "primary"
                  ? t("common.loading")
                  : t("common.addToCart")
              }
              disabled={!primaryInStock || pending !== null}
              onPress={() => void addOffer("primary")}
            />
          </View>
        ) : null}

        {secondaryOk && secondaryPrice > 0 ? (
          <View style={styles.offer}>
            <Text style={styles.offerTitle}>
              {t("digital.secondary")} — {secondaryPrice.toFixed(2)} EGP
            </Text>
            <Text style={styles.meta}>
              {secondaryInStock
                ? t("catalog.inStock")
                : t("catalog.outOfStock")}
            </Text>
            <PrimaryButton
              label={
                pending === "secondary"
                  ? t("common.loading")
                  : t("common.addToCart")
              }
              disabled={!secondaryInStock || pending !== null}
              onPress={() => void addOffer("secondary")}
            />
          </View>
        ) : null}
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pad: { padding: 16, gap: 12 },
  image: { width: "100%", height: 280, borderRadius: 12, backgroundColor: "#eee" },
  placeholder: {
    width: "100%",
    height: 200,
    borderRadius: 12,
    backgroundColor: "#e8e8e8",
  },
  title: { fontSize: 22, fontWeight: "800" },
  meta: { color: "#666" },
  offer: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 14,
    gap: 8,
  },
  offerTitle: { fontWeight: "700", fontSize: 16 },
});
