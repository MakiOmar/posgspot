import { useCallback, useEffect, useState } from "react";
import { FlatList, Pressable, Text } from "react-native";
import { Link } from "expo-router";
import { fetchDigitalGames } from "../../src/lib/api";
import { useApp } from "../../src/contexts/AppContext";
import { ErrorBlock, LoadingBlock, Screen } from "../../src/components/ui";

export default function GamesScreen() {
  const { locale, t, settings } = useApp();
  const [games, setGames] = useState<Array<{ id: number; title?: string; name?: string }>>([]);
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
      const { data } = await fetchDigitalGames(locale);
      const list = Array.isArray(data)
        ? data
        : ((data as { games?: unknown[] })?.games as typeof games) || [];
      setGames(list as typeof games);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setLoading(false);
    }
  }, [locale, t, settings]);

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
        data={games}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <Link href={`/games/${item.id}`} asChild>
            <Pressable
              style={{
                backgroundColor: "#fff",
                padding: 14,
                borderRadius: 10,
                marginBottom: 8,
              }}
            >
              <Text style={{ fontWeight: "700" }}>
                {item.title || item.name || `Game #${item.id}`}
              </Text>
            </Pressable>
          </Link>
        )}
        ListEmptyComponent={<Text>No games available</Text>}
      />
    </Screen>
  );
}
