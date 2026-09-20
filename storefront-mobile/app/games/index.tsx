import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useLocalSearchParams, useRouter } from "expo-router";
import { fetchDigitalGames } from "../../src/lib/api";
import { absoluteMediaUrl } from "../../src/lib/storefront-href";
import type { DigitalGameSummary } from "../../src/lib/types";
import { useApp } from "../../src/contexts/AppContext";
import { FormTextInput } from "../../src/components/FormTextInput";
import { RemoteImage } from "../../src/components/RemoteImage";
import { ErrorBlock, LoadingBlock, Screen } from "../../src/components/ui";
import { useRtl } from "../../src/lib/rtl";

type Platform = "4" | "5";

function parsePlatform(raw: string | string[] | undefined): Platform {
  const value = Array.isArray(raw) ? raw[0] : raw;
  return value === "4" ? "4" : "5";
}

function displayPrice(game: DigitalGameSummary): number {
  const primary = Number(game.primary_price ?? 0);
  if (Number.isFinite(primary) && primary > 0) return primary;
  const secondary = Number(game.secondary_price ?? 0);
  if (Number.isFinite(secondary) && secondary > 0) return secondary;
  const full = Number(game.full_price ?? 0);
  return Number.isFinite(full) && full > 0 ? full : 0;
}

function GameCard({
  game,
  platform,
  accent,
}: {
  game: DigitalGameSummary;
  platform: Platform;
  accent: string;
}) {
  const router = useRouter();
  const { t } = useApp();
  const { textAlign, writingDirection } = useRtl();
  const title = game.title || game.name || `Game #${game.id}`;
  const image = absoluteMediaUrl(game.image_url);
  const price = displayPrice(game);

  return (
    <Pressable
      style={styles.card}
      onPress={() =>
        router.push(`/games/${game.id}?platform=${platform}` as never)
      }
      accessibilityRole="button"
      accessibilityLabel={title}
    >
      <View style={styles.cardMedia}>
        {image ? (
          <RemoteImage uri={image} style={styles.cardImage} contentFit="cover" />
        ) : (
          <View style={[styles.cardImage, styles.cardImageFallback]}>
            <Text style={styles.cardInitial}>{title.slice(0, 1).toUpperCase()}</Text>
          </View>
        )}
      </View>
      <Text
        numberOfLines={2}
        style={[styles.cardTitle, { textAlign, writingDirection }]}
      >
        {title}
      </Text>
      {price > 0 ? (
        <Text style={[styles.cardPrice, { color: accent, textAlign }]}>
          {price.toFixed(2)} EGP
        </Text>
      ) : (
        <Text style={[styles.cardUnavailable, { textAlign }]}>
          {t("digital.unavailable")}
        </Text>
      )}
      <Text style={[styles.cardCta, { color: accent, textAlign }]}>
        {t("digital.viewOffers")}
      </Text>
    </Pressable>
  );
}

export default function GamesScreen() {
  const { locale, t, settings, accent } = useApp();
  const { row, textAlign, writingDirection } = useRtl();
  const params = useLocalSearchParams<{ platform?: string; product_type?: string }>();
  const [platform, setPlatform] = useState<Platform>(() =>
    parsePlatform(params.platform),
  );
  const productType =
    (Array.isArray(params.product_type) ? params.product_type[0] : params.product_type) ===
    "subscription"
      ? "subscription"
      : "game";
  const isPlus = productType === "subscription";
  const [q, setQ] = useState("");
  const [debouncedQ, setDebouncedQ] = useState("");
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [games, setGames] = useState<DigitalGameSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const handle = setTimeout(() => setDebouncedQ(q.trim()), 350);
    return () => clearTimeout(handle);
  }, [q]);

  const load = useCallback(
    async (pageNum: number, append: boolean) => {
      if (settings && settings.digital?.enabled === false) {
        setGames([]);
        setLoading(false);
        setLoadingMore(false);
        return;
      }
      if (append) setLoadingMore(true);
      else setLoading(true);
      try {
        const { data } = await fetchDigitalGames(
          platform,
          pageNum,
          locale,
          debouncedQ || undefined,
          productType,
        );
        const list = data.games || [];
        setGames((prev) => (append ? [...prev, ...list] : list));
        setLastPage(Number(data.meta?.last_page || 1));
        setPage(pageNum);
        setError(null);
      } catch (e) {
        setError(e instanceof Error ? e.message : t("common.error"));
        if (!append) setGames([]);
      } finally {
        setLoading(false);
        setLoadingMore(false);
      }
    },
    [locale, t, settings, platform, debouncedQ, productType],
  );

  useEffect(() => {
    void load(1, false);
  }, [load]);

  useEffect(() => {
    const next = parsePlatform(params.platform);
    setPlatform((prev) => (prev === next ? prev : next));
  }, [params.platform]);

  const header = useMemo(
    () => (
      <View>
        <Text style={[styles.lead, { textAlign, writingDirection }]}>
          {isPlus ? t("digital.psPlusLead") : t("digital.gamesLead")}
        </Text>
        <View style={[styles.platformRow, { flexDirection: row }]}>
          {(["5", "4"] as Platform[]).map((p) => {
            const active = platform === p;
            return (
              <Pressable
                key={p}
                style={[
                  styles.chip,
                  active && { borderColor: accent, backgroundColor: "#fff8e8" },
                ]}
                onPress={() => setPlatform(p)}
              >
                <Text style={[styles.chipText, { textAlign, writingDirection }]}>
                  {p === "5" ? t("digital.ps5") : t("digital.ps4")}
                </Text>
              </Pressable>
            );
          })}
        </View>
        <FormTextInput
          style={styles.search}
          placeholder={t("digital.searchGames")}
          value={q}
          onChangeText={setQ}
          autoCapitalize="none"
          returnKeyType="search"
        />
      </View>
    ),
    [accent, isPlus, platform, q, row, t, textAlign, writingDirection],
  );

  if (loading && games.length === 0) {
    return (
      <Screen>
        {header}
        <LoadingBlock />
      </Screen>
    );
  }

  return (
    <Screen padded={false}>
      <View style={styles.body}>
        {error && games.length === 0 ? (
          <>
            {header}
            <ErrorBlock message={error} onRetry={() => void load(1, false)} />
          </>
        ) : (
          <FlatList
            style={styles.list}
            data={games}
            keyExtractor={(item) => String(item.id)}
            numColumns={2}
            columnWrapperStyle={styles.grid}
            contentContainerStyle={styles.listPad}
            ListHeaderComponent={header}
            renderItem={({ item }) => (
              <GameCard game={item} platform={platform} accent={accent} />
            )}
            onEndReached={() => {
              if (!loading && !loadingMore && page < lastPage) {
                void load(page + 1, true);
              }
            }}
            onEndReachedThreshold={0.4}
            ListEmptyComponent={
              loading ? null : (
                <Text style={[styles.empty, { textAlign }]}>
                  {isPlus ? t("digital.noPsPlus") : t("digital.noGames")}
                </Text>
              )
            }
            ListFooterComponent={
              loadingMore ? (
                <ActivityIndicator style={{ marginVertical: 16 }} color={accent} />
              ) : null
            }
          />
        )}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  body: { flex: 1, paddingHorizontal: 16 },
  list: { flex: 1 },
  listPad: { paddingBottom: 24 },
  lead: { color: "#666", marginTop: 8, marginBottom: 12, lineHeight: 20 },
  platformRow: { gap: 8, marginBottom: 10 },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: "#ddd",
    backgroundColor: "#fff",
  },
  chipText: { fontWeight: "600" },
  search: { marginBottom: 14 },
  grid: { justifyContent: "space-between", marginBottom: 12 },
  card: {
    width: "48%",
    backgroundColor: "#fff",
    borderRadius: 14,
    overflow: "hidden",
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "#e8e8e8",
    paddingBottom: 12,
  },
  cardMedia: {
    width: "100%",
    aspectRatio: 1,
    backgroundColor: "#111",
  },
  cardImage: { width: "100%", height: "100%" },
  cardImageFallback: {
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#1a1a1a",
  },
  cardInitial: { color: "#fff", fontSize: 36, fontWeight: "800" },
  cardTitle: {
    marginTop: 10,
    marginHorizontal: 10,
    fontSize: 14,
    fontWeight: "700",
    color: "#111",
    minHeight: 36,
  },
  cardPrice: {
    marginHorizontal: 10,
    marginTop: 4,
    fontSize: 15,
    fontWeight: "800",
  },
  cardUnavailable: {
    marginHorizontal: 10,
    marginTop: 4,
    fontSize: 12,
    color: "#888",
  },
  cardCta: {
    marginHorizontal: 10,
    marginTop: 8,
    fontSize: 12,
    fontWeight: "700",
  },
  empty: { textAlign: "center", color: "#666", marginTop: 24 },
});
