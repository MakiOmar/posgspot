import { useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import { ScrollView, StyleSheet, Text, View } from "react-native";
import {
  checkDigitalGameStock,
  fetchDigitalGame,
} from "../../src/lib/api";
import { absoluteMediaUrl } from "../../src/lib/storefront-href";
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
import {
  digitalOfferEnabled,
  digitalOfferInStock,
  digitalOfferPrice,
  digitalOfferStock,
  liveCheckStockIsOut,
} from "../../src/lib/digital-game";
import { toast } from "../../src/lib/toast";
import { useRtl } from "../../src/lib/rtl";

type Offer = "primary" | "secondary";
type Platform = "4" | "5";

export default function GameDetailScreen() {
  const { id, platform: platformParam } = useLocalSearchParams<{
    id: string;
    platform?: string;
  }>();
  const platform: Platform = platformParam === "5" ? "5" : "4";
  const { locale, t, accent } = useApp();
  const { textAlign, writingDirection } = useRtl();
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
  const imageRaw =
    platform === "5"
      ? String(game.ps5_image_url || game.image_url || "")
      : String(game.ps4_image_url || game.image_url || "");
  const image = absoluteMediaUrl(imageRaw) || imageRaw;

  const primaryPrice = digitalOfferPrice(game, platform, "primary");
  const secondaryPrice = digitalOfferPrice(game, platform, "secondary");
  const primaryOk = digitalOfferEnabled(game, platform, "primary");
  const secondaryOk = digitalOfferEnabled(game, platform, "secondary");
  const primaryStock = digitalOfferStock(game, platform, "primary");
  const secondaryStock = digitalOfferStock(game, platform, "secondary");
  const primaryInStock = digitalOfferInStock(game, platform, "primary");
  const secondaryInStock = digitalOfferInStock(game, platform, "secondary");

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
      if (liveCheckStockIsOut(stockData)) {
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
        <RemoteImage uri={image} style={styles.image} contentFit="cover" />
        <Text style={[styles.title, { textAlign, writingDirection }]}>
          {title}
        </Text>
        <Text style={[styles.meta, { textAlign, writingDirection }]}>
          {t("digital.platformLabel")} · PS{platform}
        </Text>

        {primaryOk && primaryPrice > 0 ? (
          <View style={[styles.offer, { borderColor: accent }]}>
            <Text style={[styles.offerTitle, { textAlign, writingDirection }]}>
              {t("digital.primary")}
            </Text>
            <Text style={[styles.offerPrice, { color: accent, textAlign }]}>
              {primaryPrice.toFixed(2)} EGP
            </Text>
            <Text style={[styles.meta, { textAlign }]}>
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
            <Text style={[styles.offerTitle, { textAlign, writingDirection }]}>
              {t("digital.secondary")}
            </Text>
            <Text style={[styles.offerPrice, { color: accent, textAlign }]}>
              {secondaryPrice.toFixed(2)} EGP
            </Text>
            <Text style={[styles.meta, { textAlign }]}>
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

        {!primaryOk && !secondaryOk ? (
          <Text style={[styles.meta, { textAlign }]}>
            {t("digital.unavailable")}
          </Text>
        ) : null}
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pad: { padding: 16, gap: 12, paddingBottom: 32 },
  image: {
    width: "100%",
    height: 320,
    borderRadius: 16,
    backgroundColor: "#111",
  },
  title: { fontSize: 24, fontWeight: "800", color: "#111" },
  meta: { color: "#666", fontSize: 14 },
  offer: {
    backgroundColor: "#fff",
    borderRadius: 14,
    padding: 16,
    gap: 8,
    borderWidth: 1,
    borderColor: "#eee",
  },
  offerTitle: { fontWeight: "800", fontSize: 16 },
  offerPrice: { fontWeight: "800", fontSize: 20 },
});
