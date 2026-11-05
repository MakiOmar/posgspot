import { useCallback, useEffect, useMemo, useState } from "react";
import { FlatList, Pressable, StyleSheet, Text, View } from "react-native";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import {
  checkDigitalCardStock,
  fetchCardCategories,
} from "../../src/lib/api";
import type { DigitalCardCategory, DigitalSkus } from "../../src/lib/types";
import { useApp } from "../../src/contexts/AppContext";
import { useCart } from "../../src/contexts/CartContext";
import { RemoteImage } from "../../src/components/RemoteImage";
import { GiftCardArt } from "../../src/components/digital/GiftCardArt";
import { GiftCardCountrySheet } from "../../src/components/digital/GiftCardCountrySheet";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../src/components/ui";
import { toast } from "../../src/lib/toast";
import { useRtl } from "../../src/lib/rtl";
import {
  resolveGiftCardBrowse,
  type GiftCardBrandGroup,
} from "../../src/lib/gift-card-groups";
import { brandDisplayLabel, countryDisplayLabel } from "../../src/lib/gift-card-labels";

function firstParam(value: string | string[] | undefined): string {
  return (Array.isArray(value) ? value[0] : value) || "";
}

function normalizeCategories(
  rows: Array<Partial<DigitalCardCategory> & { id: number; title?: string }>,
): DigitalCardCategory[] {
  return rows.map((row) => ({
    id: Number(row.id),
    name: String(row.name ?? row.title ?? `Card #${row.id}`),
    price: row.price ?? 0,
    poster_image: row.poster_image ?? null,
    card_art: row.card_art ?? null,
    classification: row.classification ?? null,
    pos_sku: row.pos_sku ?? null,
  }));
}

export default function GiftCardsScreen() {
  const { locale, t, accent } = useApp();
  const { row, textAlign, writingDirection } = useRtl();
  const { addItem } = useCart();
  const router = useRouter();
  const params = useLocalSearchParams<{
    q?: string;
    brand?: string;
    country?: string;
    all?: string;
  }>();
  const [cards, setCards] = useState<DigitalCardCategory[]>([]);
  const [skus, setSkus] = useState<DigitalSkus | null>(null);
  const [pendingId, setPendingId] = useState<number | null>(null);
  const [modalBrand, setModalBrand] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const browse = useMemo(
    () =>
      resolveGiftCardBrowse(cards, {
        q: firstParam(params.q),
        brand: firstParam(params.brand) || null,
        country: firstParam(params.country) || null,
        all: firstParam(params.all) === "1",
      }),
    [cards, params.q, params.brand, params.country, params.all],
  );
  const modalGroup = browse.groups.find((group) => group.brand === modalBrand) ?? null;
  const brandGroup = browse.brandGroup;
  const brandLabel = brandGroup
    ? brandDisplayLabel(locale, brandGroup.brand, brandGroup.label)
    : "";
  const countryLabel =
    brandGroup && browse.country
      ? countryDisplayLabel(
          locale,
          browse.country,
          brandGroup.countries.find((c) => c.key === browse.country)?.label ?? browse.country,
        )
      : t("digital.allCountries");

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await fetchCardCategories(locale);
      setCards(normalizeCategories(data.categories || []));
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

  const showBrand = (brand: string, country: string | null) => {
    setModalBrand(null);
    router.setParams({ brand, country: country ?? "", all: "" });
  };

  const chooseBrand = (group: GiftCardBrandGroup) => {
    if (group.countries.length === 1) {
      showBrand(group.brand, group.countries[0].key);
      return;
    }
    setModalBrand(group.brand);
  };

  const resetBrowse = () => router.setParams({ brand: "", country: "", all: "" });

  const addCard = async (card: DigitalCardCategory) => {
    const sku = card.pos_sku ?? skus?.gift_card;
    if (!sku) {
      toast.error(t("digital.skuMissing"));
      return;
    }
    const price = Number(card.price);
    if (!Number.isFinite(price) || price <= 0) {
      toast.error(t("digital.unavailable"));
      return;
    }
    setPendingId(card.id);
    try {
      await checkDigitalCardStock(card.id, locale);
      await addItem({
        variationId: sku.variation_id,
        productId: sku.product_id,
        name: card.name,
        imageUrl: card.poster_image || sku.image_url,
        unitPrice: price,
        quantity: 1,
        digital: {
          kind: "card",
          card_category_id: card.id,
          line_key: `card|category:${card.id}`,
          title: card.name,
          price,
        },
      });
      toast.success(t("digital.addedToCart"));
      router.push("/(tabs)/cart");
    } catch (e) {
      toast.error(e instanceof Error ? e.message : t("digital.stockFailed"));
    } finally {
      setPendingId(null);
    }
  };

  if (loading) {
    return (
      <Screen>
        <LoadingBlock />
      </Screen>
    );
  }

  const header = (
    <View style={styles.header}>
      <Text style={[styles.lead, { textAlign, writingDirection }]}>
        {t("digital.giftCardsLead")}
      </Text>
      {brandGroup ? (
        <View style={[styles.filterBar, { flexDirection: row }]}>
          <Pressable onPress={resetBrowse} accessibilityRole="link">
            <Text style={[styles.filterLink, { color: accent }]}>{t("digital.allBrands")}</Text>
          </Pressable>
          <Text style={styles.filterSep}>›</Text>
          <Text style={styles.filterStrong}>{brandLabel}</Text>
          <Text style={styles.filterSep}>›</Text>
          <Text style={styles.filterStrong}>{countryLabel}</Text>
          {brandGroup.countries.length > 1 ? (
            <Pressable
              style={[styles.chip, { borderColor: accent }]}
              onPress={() => setModalBrand(brandGroup.brand)}
            >
              <Text style={{ color: accent, fontWeight: "700" }}>
                {t("digital.changeCountry")}
              </Text>
            </Pressable>
          ) : null}
        </View>
      ) : browse.showAll ? (
        <Pressable onPress={resetBrowse} accessibilityRole="link">
          <Text style={[styles.filterLink, { color: accent, textAlign }]}>
            ‹ {t("digital.allBrands")}
          </Text>
        </Pressable>
      ) : null}
      {browse.browsing ? (
        <View style={[styles.brandsHead, { flexDirection: row }]}>
          <Text style={[styles.brandsTitle, { textAlign, writingDirection }]}>
            {t("digital.chooseBrand")}
          </Text>
          <Pressable
            style={[styles.chip, { borderColor: accent }]}
            onPress={() => router.setParams({ all: "1", brand: "", country: "" })}
          >
            <Text style={{ color: accent, fontWeight: "700" }}>{t("digital.allCards")}</Text>
          </Pressable>
        </View>
      ) : null}
      {error ? <ErrorBlock message={error} onRetry={() => void load()} /> : null}
    </View>
  );

  return (
    <Screen padded={false}>
      <Stack.Screen options={{ title: brandGroup ? brandLabel : t("common.giftCards") }} />
      {browse.browsing ? (
        <FlatList
          key="brands"
          style={styles.flex}
          contentContainerStyle={styles.listPad}
          data={browse.groups}
          numColumns={2}
          columnWrapperStyle={styles.grid}
          keyExtractor={(item) => item.brand}
          ListHeaderComponent={header}
          renderItem={({ item }) => (
            <Pressable
              style={styles.tile}
              onPress={() => chooseBrand(item)}
              accessibilityRole="button"
              accessibilityLabel={brandDisplayLabel(locale, item.brand, item.label)}
            >
              <View style={[styles.tileLogo, { backgroundColor: item.color || "#4b5563" }]}>
                {item.logo_url ? (
                  <RemoteImage
                    uri={item.logo_url}
                    style={styles.tileLogoImg}
                    contentFit="contain"
                    placeholderColor="transparent"
                  />
                ) : (
                  <Text style={styles.tileInitial}>
                    {brandDisplayLabel(locale, item.brand, item.label).slice(0, 1).toUpperCase()}
                  </Text>
                )}
              </View>
              <Text style={styles.tileLabel} numberOfLines={2}>
                {brandDisplayLabel(locale, item.brand, item.label)}
              </Text>
              <Text style={styles.tileCount}>
                {t("digital.brandCardsCount", { count: item.count })}
              </Text>
            </Pressable>
          )}
        />
      ) : (
        <FlatList
          key="cards"
          style={styles.flex}
          contentContainerStyle={styles.listPad}
          data={browse.categories}
          numColumns={2}
          columnWrapperStyle={styles.grid}
          keyExtractor={(item) => String(item.id)}
          ListHeaderComponent={header}
          renderItem={({ item }) => (
            <View style={styles.card}>
              {item.poster_image ? (
                <RemoteImage uri={item.poster_image} style={styles.image} />
              ) : item.card_art ? (
                <GiftCardArt art={item.card_art} />
              ) : (
                <View style={styles.image} />
              )}
              <Text style={[styles.title, { textAlign, writingDirection }]} numberOfLines={2}>
                {item.name}
              </Text>
              <Text style={[styles.price, { color: accent, textAlign }]}>
                {Number(item.price || 0).toFixed(2)} EGP
              </Text>
              <PrimaryButton
                label={pendingId === item.id ? t("common.loading") : t("common.addToCart")}
                disabled={pendingId !== null}
                onPress={() => void addCard(item)}
              />
            </View>
          )}
          ListEmptyComponent={
            error ? null : (
              <Text style={[styles.empty, { textAlign }]}>
                {cards.length > 0 ? t("digital.noCardsForSelection") : t("digital.noGiftCards")}
              </Text>
            )
          }
        />
      )}
      <GiftCardCountrySheet
        group={modalGroup}
        onChoose={showBrand}
        onClose={() => setModalBrand(null)}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  listPad: { padding: 16, paddingBottom: 32 },
  header: { gap: 10, marginBottom: 12 },
  lead: { color: "#666", lineHeight: 20 },
  filterBar: { alignItems: "center", flexWrap: "wrap", gap: 6 },
  filterLink: { fontWeight: "700" },
  filterSep: { color: "#999" },
  filterStrong: { fontWeight: "800", color: "#111" },
  chip: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderWidth: 1,
    borderRadius: 999,
  },
  brandsHead: { alignItems: "center", justifyContent: "space-between", gap: 8 },
  brandsTitle: { fontSize: 17, fontWeight: "800", color: "#111", flex: 1 },
  grid: { justifyContent: "space-between", marginBottom: 12 },
  tile: {
    width: "48%",
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 12,
    alignItems: "center",
    gap: 6,
  },
  tileLogo: {
    width: 72,
    height: 72,
    borderRadius: 16,
    alignItems: "center",
    justifyContent: "center",
    overflow: "hidden",
  },
  tileLogoImg: { width: 52, height: 52 },
  tileInitial: { color: "#fff", fontSize: 28, fontWeight: "800" },
  tileLabel: { fontWeight: "800", color: "#111", textAlign: "center" },
  tileCount: { color: "#666", fontSize: 12 },
  card: {
    width: "48%",
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 10,
    gap: 6,
  },
  image: {
    width: "100%",
    aspectRatio: 1,
    borderRadius: 8,
    backgroundColor: "#eee",
  },
  title: { fontWeight: "800", fontSize: 14, color: "#111" },
  price: { fontWeight: "700" },
  empty: { color: "#666", marginTop: 24 },
});
