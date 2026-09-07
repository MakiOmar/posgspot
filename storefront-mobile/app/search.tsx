import { useCallback, useEffect, useMemo, useState } from "react";
import {
  FlatList,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useRouter } from "expo-router";
import { useApp } from "../src/contexts/AppContext";
import { FormTextInput } from "../src/components/FormTextInput";
import { ProductGridScreen } from "../src/components/catalog/ProductGridScreen";
import { RemoteImage } from "../src/components/RemoteImage";
import { ErrorBlock, LoadingBlock, Screen } from "../src/components/ui";
import { searchCatalog } from "../src/lib/api";
import { useRtl } from "../src/lib/rtl";
import { absoluteMediaUrl, hrefToAppPath } from "../src/lib/storefront-href";
import type { CatalogSearchType, SearchHit } from "../src/lib/types";

const TYPES: CatalogSearchType[] = ["products", "games", "gift_cards"];

function typeLabelKey(type: CatalogSearchType): string {
  if (type === "games") return "search.typeGames";
  if (type === "gift_cards") return "search.typeGiftCards";
  return "search.typeProducts";
}

function placeholderKey(type: CatalogSearchType): string {
  if (type === "games") return "search.placeholderGames";
  if (type === "gift_cards") return "search.placeholderGiftCards";
  return "search.placeholderProducts";
}

export default function SearchScreen() {
  const { locale, t, settings, accent } = useApp();
  const { row, textAlign, writingDirection } = useRtl();
  const router = useRouter();
  const [q, setQ] = useState("");
  const [searchType, setSearchType] = useState<CatalogSearchType>("products");
  const [hits, setHits] = useState<SearchHit[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const digitalEnabled = settings?.digital?.enabled !== false;
  const availableTypes = useMemo(
    () => (digitalEnabled ? TYPES : (["products"] as CatalogSearchType[])),
    [digitalEnabled],
  );

  useEffect(() => {
    if (!digitalEnabled && searchType !== "products") {
      setSearchType("products");
    }
  }, [digitalEnabled, searchType]);

  const loadDigital = useCallback(async () => {
    const term = q.trim();
    if (searchType === "products") {
      return;
    }
    if (term.length < 1) {
      setHits([]);
      setError(null);
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const { data } = await searchCatalog(
        term,
        { limit: 20, type: searchType },
        locale,
      );
      setHits(Array.isArray(data) ? data : []);
    } catch (e) {
      setHits([]);
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setLoading(false);
    }
  }, [q, searchType, locale, t]);

  useEffect(() => {
    if (searchType === "products") {
      setHits([]);
      setError(null);
      setLoading(false);
      return;
    }
    const handle = setTimeout(() => {
      void loadDigital();
    }, 300);
    return () => clearTimeout(handle);
  }, [loadDigital, searchType]);

  const openHit = (hit: SearchHit) => {
    const path = hrefToAppPath(hit.href) || (
      hit.kind === "game"
        ? `/games/${hit.id}?platform=${hit.platform || "5"}`
        : "/gift-cards"
    );
    router.push(path as never);
  };

  const typeChips = (
    <View style={[styles.typeRow, { flexDirection: row }]} accessibilityRole="tablist">
      {availableTypes.map((type) => {
        const active = searchType === type;
        return (
          <Pressable
            key={type}
            accessibilityRole="tab"
            accessibilityState={{ selected: active }}
            style={[
              styles.typeChip,
              active && { borderColor: accent, backgroundColor: "#fff8e8" },
            ]}
            onPress={() => setSearchType(type)}
          >
            <Text
              style={[
                styles.typeChipText,
                active && { color: accent, fontWeight: "700" },
                { textAlign, writingDirection },
              ]}
            >
              {t(typeLabelKey(type))}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );

  const searchField = (
    <FormTextInput
      style={styles.input}
      placeholder={t(placeholderKey(searchType))}
      value={q}
      onChangeText={setQ}
      autoFocus
      autoCapitalize="none"
      returnKeyType="search"
    />
  );

  if (searchType === "products") {
    return (
      <Screen padded={false}>
        <ProductGridScreen
          locale={locale}
          mode="search"
          searchQ={q}
          header={
            <View>
              {typeChips}
              {searchField}
            </View>
          }
        />
      </Screen>
    );
  }

  const emptyMessage =
    q.trim().length < 1
      ? t("search.prompt")
      : searchType === "gift_cards"
        ? t("search.noGiftCards")
        : t("search.noGames");

  return (
    <Screen padded={false}>
      <View style={styles.body}>
        {typeChips}
        {searchField}
        {loading && hits.length === 0 ? (
          <LoadingBlock />
        ) : error && hits.length === 0 ? (
          <ErrorBlock message={error} onRetry={() => void loadDigital()} />
        ) : (
          <FlatList
            style={styles.list}
            data={hits}
            keyExtractor={(item) =>
              `${item.kind || searchType}-${item.id}-${item.platform || ""}`
            }
            contentContainerStyle={styles.listPad}
            renderItem={({ item }) => {
              const image = absoluteMediaUrl(item.image_url);
              const price = Number(item.price || 0);
              return (
                <Pressable
                  style={[styles.hitCard, { flexDirection: row }]}
                  onPress={() => openHit(item)}
                >
                  <RemoteImage uri={image} style={styles.hitImage} />
                  <View style={styles.hitBody}>
                    <Text
                      style={[styles.hitTitle, { textAlign, writingDirection }]}
                      numberOfLines={2}
                    >
                      {item.name}
                    </Text>
                    {item.variation_name ? (
                      <Text
                        style={[styles.hitMeta, { textAlign, writingDirection }]}
                      >
                        {item.variation_name}
                      </Text>
                    ) : null}
                    {price > 0 ? (
                      <Text
                        style={[styles.hitPrice, { textAlign, writingDirection }]}
                      >
                        {price.toFixed(2)} EGP
                      </Text>
                    ) : null}
                  </View>
                </Pressable>
              );
            }}
            ListEmptyComponent={
              loading ? null : (
                <Text style={[styles.empty, { textAlign }]}>{emptyMessage}</Text>
              )
            }
            ItemSeparatorComponent={() => <View style={{ height: 10 }} />}
          />
        )}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  body: { flex: 1, paddingHorizontal: 16 },
  typeRow: {
    flexWrap: "wrap",
    gap: 8,
    marginTop: 8,
    marginBottom: 4,
  },
  typeChip: {
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 20,
    paddingHorizontal: 14,
    paddingVertical: 8,
    backgroundColor: "#fff",
  },
  typeChipText: { fontSize: 13, color: "#444" },
  input: { marginBottom: 12, marginTop: 8 },
  list: { flex: 1 },
  listPad: { paddingBottom: 24 },
  hitCard: {
    gap: 12,
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 12,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "#e8e8e8",
  },
  hitImage: {
    width: 72,
    height: 72,
    borderRadius: 8,
    backgroundColor: "#eee",
  },
  hitBody: { flex: 1, justifyContent: "center", gap: 4 },
  hitTitle: { fontSize: 15, fontWeight: "700", color: "#111" },
  hitMeta: { fontSize: 12, color: "#777" },
  hitPrice: { fontSize: 14, fontWeight: "700", color: "#222" },
  empty: { color: "#666", marginTop: 24 },
});
