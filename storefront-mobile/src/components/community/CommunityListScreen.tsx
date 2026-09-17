import { useCallback, useEffect, useState } from "react";
import {
  FlatList,
  Image,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useRouter } from "expo-router";
import { fetchCommunityPosts } from "../../lib/api";
import { useApp } from "../../contexts/AppContext";
import { LoadingBlock, Screen } from "../ui";
import type { CommunityPostSummary, CommunityPostType } from "../../lib/types";

type Props = {
  type: CommunityPostType;
  detailBase: string;
  titleKey: string;
  leadKey: string;
  scoped?: boolean;
};

function formatWhen(value: string | null | undefined, locale: string): string {
  if (!value) return "";
  const d = new Date(value);
  return Number.isNaN(d.getTime())
    ? value
    : d.toLocaleDateString(locale === "ar" ? "ar-EG" : "en-EG", {
        year: "numeric",
        month: "short",
        day: "numeric",
      });
}

/** Shared community list used by tournaments / events / gaming-news screens. */
export function CommunityListScreen({
  type,
  detailBase,
  titleKey,
  leadKey,
  scoped = false,
}: Props) {
  const { t, locale, accent, settings } = useApp();
  const router = useRouter();
  const enabled = settings?.community?.enabled === true;
  const [scope, setScope] = useState<"upcoming" | "previous">("upcoming");
  const [posts, setPosts] = useState<CommunityPostSummary[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    if (!enabled) {
      setPosts([]);
      setLoading(false);
      return;
    }
    setLoading(true);
    void fetchCommunityPosts(
      {
        type,
        ...(scoped ? { scope } : {}),
      },
      locale,
    )
      .then(({ data }) => setPosts(data || []))
      .catch(() => setPosts([]))
      .finally(() => setLoading(false));
  }, [enabled, type, scoped, scope, locale]);

  useEffect(() => {
    load();
  }, [load]);

  if (!enabled) {
    return (
      <Screen>
        <Text style={styles.muted}>{t("community.unavailable")}</Text>
      </Screen>
    );
  }

  return (
    <Screen padded={false}>
      <View style={styles.head}>
        <Text style={styles.title}>{t(titleKey)}</Text>
        <Text style={styles.lead}>{t(leadKey)}</Text>
        {scoped ? (
          <View style={styles.tabs}>
            {(["upcoming", "previous"] as const).map((id) => {
              const active = scope === id;
              return (
                <Pressable
                  key={id}
                  style={[styles.tab, active && { backgroundColor: accent }]}
                  onPress={() => setScope(id)}
                >
                  <Text style={[styles.tabText, active && styles.tabTextActive]}>
                    {t(id === "upcoming" ? "community.upcoming" : "community.previous")}
                  </Text>
                </Pressable>
              );
            })}
          </View>
        ) : null}
      </View>
      {loading ? <LoadingBlock /> : null}
      {!loading && posts.length === 0 ? (
        <Text style={styles.muted}>{t("community.empty")}</Text>
      ) : null}
      <FlatList
        data={posts}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={{ padding: 16, paddingTop: 0, gap: 12 }}
        renderItem={({ item }) => (
          <Pressable
            style={styles.card}
            onPress={() => router.push(`${detailBase}/${item.slug}` as never)}
          >
            {item.cover_url ? (
              <Image source={{ uri: item.cover_url }} style={styles.cover} />
            ) : null}
            <View style={styles.cardBody}>
              {item.starts_at || item.published_at ? (
                <Text style={styles.meta}>
                  {formatWhen(item.starts_at || item.published_at, locale)}
                </Text>
              ) : null}
              <Text style={styles.cardTitle}>{item.title}</Text>
              {item.excerpt ? (
                <Text style={styles.excerpt} numberOfLines={3}>
                  {item.excerpt}
                </Text>
              ) : null}
              <Text style={[styles.readMore, { color: accent }]}>
                {t("community.readMore")}
              </Text>
            </View>
          </Pressable>
        )}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  head: { paddingHorizontal: 16, paddingTop: 12, paddingBottom: 8 },
  title: { fontSize: 22, fontWeight: "800", color: "#111" },
  lead: { fontSize: 14, color: "#666", marginTop: 4, marginBottom: 10 },
  tabs: { flexDirection: "row", gap: 8, marginBottom: 4 },
  tab: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    backgroundColor: "#e8e8e8",
  },
  tabText: { fontWeight: "700", color: "#333" },
  tabTextActive: { color: "#111" },
  muted: { color: "#888", padding: 16 },
  card: {
    backgroundColor: "#fff",
    borderRadius: 12,
    overflow: "hidden",
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "#e0e0e0",
  },
  cover: { width: "100%", height: 140 },
  cardBody: { padding: 12, gap: 4 },
  meta: { fontSize: 12, color: "#888" },
  cardTitle: { fontSize: 16, fontWeight: "800", color: "#111" },
  excerpt: { fontSize: 14, color: "#555", lineHeight: 20 },
  readMore: { fontWeight: "700", marginTop: 4 },
});
