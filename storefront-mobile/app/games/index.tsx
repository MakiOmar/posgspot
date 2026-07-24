import { useCallback, useEffect, useState } from "react";
import { FlatList, Pressable, StyleSheet, Text, View } from "react-native";
import { Link } from "expo-router";
import { fetchDigitalGames } from "../../src/lib/api";
import { useApp } from "../../src/contexts/AppContext";
import { ErrorBlock, LoadingBlock, Screen } from "../../src/components/ui";

type Platform = "4" | "5";

export default function GamesScreen() {
  const { locale, t, settings, accent } = useApp();
  const [platform, setPlatform] = useState<Platform>("5");
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [games, setGames] = useState<
    Array<{ id: number; title?: string; name?: string }>
  >([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (settings && settings.digital?.enabled === false) {
      setGames([]);
      setLoading(false);
      return;
    }
    setLoading(true);
    try {
      const { data } = await fetchDigitalGames(platform, page, locale);
      setGames(data.games || []);
      setLastPage(Number(data.meta?.last_page || 1));
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setLoading(false);
    }
  }, [locale, t, settings, platform, page]);

  useEffect(() => {
    void load();
  }, [load]);

  if (loading && games.length === 0) {
    return (
      <Screen>
        <LoadingBlock />
      </Screen>
    );
  }

  return (
    <Screen>
      <View style={styles.platformRow}>
        {(["5", "4"] as Platform[]).map((p) => {
          const active = platform === p;
          return (
            <Pressable
              key={p}
              style={[
                styles.chip,
                active && { borderColor: accent, backgroundColor: "#fff8e8" },
              ]}
              onPress={() => {
                setPage(1);
                setPlatform(p);
              }}
            >
              <Text style={styles.chipText}>
                {p === "5" ? t("digital.ps5") : t("digital.ps4")}
              </Text>
            </Pressable>
          );
        })}
      </View>
      {error ? <ErrorBlock message={error} onRetry={() => void load()} /> : null}
      <FlatList
        style={{ flex: 1 }}
        data={games}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <Link href={`/games/${item.id}?platform=${platform}`} asChild>
            <Pressable style={styles.row}>
              <Text style={styles.rowTitle}>
                {item.title || item.name || `Game #${item.id}`}
              </Text>
            </Pressable>
          </Link>
        )}
        ListEmptyComponent={<Text>{t("digital.noGames")}</Text>}
        ListFooterComponent={
          lastPage > 1 ? (
            <View style={styles.pager}>
              <Pressable
                disabled={page <= 1}
                onPress={() => setPage((p) => Math.max(1, p - 1))}
              >
                <Text style={styles.pagerBtn}>{t("plp.prev")}</Text>
              </Pressable>
              <Text>
                {page} / {lastPage}
              </Text>
              <Pressable
                disabled={page >= lastPage}
                onPress={() => setPage((p) => Math.min(lastPage, p + 1))}
              >
                <Text style={styles.pagerBtn}>{t("plp.next")}</Text>
              </Pressable>
            </View>
          ) : null
        }
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  platformRow: { flexDirection: "row", gap: 8, marginBottom: 12 },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: "#ddd",
    backgroundColor: "#fff",
  },
  chipText: { fontWeight: "600" },
  row: {
    backgroundColor: "#fff",
    padding: 14,
    borderRadius: 10,
    marginBottom: 8,
  },
  rowTitle: { fontWeight: "700" },
  pager: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingVertical: 16,
  },
  pagerBtn: { fontWeight: "700", color: "#333" },
});
