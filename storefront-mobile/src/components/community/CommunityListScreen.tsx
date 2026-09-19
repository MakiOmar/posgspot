import { useCallback, useEffect, useMemo, useState } from "react";
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

function formatRange(
  startsAt: string | null | undefined,
  endsAt: string | null | undefined,
  locale: string,
  t: (key: string) => string,
): string {
  const start = formatWhen(startsAt, locale);
  const end = formatWhen(endsAt, locale);
  if (start && end) {
    return t("community.dateRange").replace("{start}", start).replace("{end}", end);
  }
  return start || end;
}

function PostCard({
  item,
  detailBase,
  locale,
  accent,
  t,
  onPress,
}: {
  item: CommunityPostSummary;
  detailBase: string;
  locale: string;
  accent: string;
  t: (key: string) => string;
  onPress: () => void;
}) {
  const range = formatRange(item.starts_at, item.ends_at, locale, t);
  const dateLine = range || formatWhen(item.published_at, locale);

  return (
    <Pressable style={styles.card} onPress={onPress}>
      {item.cover_url ? (
        <Image source={{ uri: item.cover_url }} style={styles.cover} />
      ) : null}
      <View style={styles.cardBody}>
        {item.game_title ? (
          <Text style={[styles.game, { color: accent }]}>{item.game_title}</Text>
        ) : null}
        {dateLine ? <Text style={styles.meta}>{dateLine}</Text> : null}
        {item.location?.name ? (
          <Text style={styles.meta}>{item.location.name}</Text>
        ) : null}
        <Text style={styles.cardTitle}>{item.title}</Text>
        {item.prize_pool ? (
          <Text style={styles.meta}>
            {t("community.prizePool")}: {item.prize_pool}
          </Text>
        ) : null}
        {item.excerpt ? (
          <Text style={styles.excerpt} numberOfLines={3}>
            {item.excerpt}
          </Text>
        ) : null}
        <Text style={[styles.readMore, { color: accent }]}>{t("community.readMore")}</Text>
      </View>
    </Pressable>
  );
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

  const { hero, latest } = useMemo(() => {
    if (type !== "news") {
      return { hero: null as CommunityPostSummary | null, latest: posts };
    }
    const featured = posts.filter((p) => p.is_featured);
    const nextHero =
      featured.length > 0
        ? [...featured].sort((a, b) => {
            const ta = a.published_at ? new Date(a.published_at).getTime() : 0;
            const tb = b.published_at ? new Date(b.published_at).getTime() : 0;
            return tb - ta;
          })[0]
        : null;
    return {
      hero: nextHero,
      latest: nextHero ? posts.filter((p) => p.id !== nextHero.id) : posts,
    };
  }, [posts, type]);

  if (!enabled) {
    return (
      <Screen>
        <Text style={styles.muted}>{t("community.unavailable")}</Text>
      </Screen>
    );
  }

  return (
    <Screen padded={false}>
      <FlatList
        data={type === "news" ? latest : posts}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={{ padding: 16, paddingTop: 0, gap: 12 }}
        ListHeaderComponent={
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
            {loading ? <LoadingBlock /> : null}
            {!loading && posts.length === 0 ? (
              <Text style={styles.mutedInline}>{t("community.empty")}</Text>
            ) : null}
            {hero ? (
              <View style={styles.featuredWrap}>
                <Text style={styles.sectionLabel}>{t("community.featured")}</Text>
                <PostCard
                  item={hero}
                  detailBase={detailBase}
                  locale={locale}
                  accent={accent}
                  t={t}
                  onPress={() => router.push(`${detailBase}/${hero.slug}` as never)}
                />
                {latest.length > 0 ? (
                  <Text style={[styles.sectionLabel, { marginTop: 16 }]}>
                    {t("community.latest")}
                  </Text>
                ) : null}
              </View>
            ) : null}
          </View>
        }
        renderItem={({ item }) => (
          <PostCard
            item={item}
            detailBase={detailBase}
            locale={locale}
            accent={accent}
            t={t}
            onPress={() => router.push(`${detailBase}/${item.slug}` as never)}
          />
        )}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  head: { paddingTop: 12, paddingBottom: 8 },
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
  mutedInline: { color: "#888", marginBottom: 8 },
  featuredWrap: { marginTop: 8, marginBottom: 4 },
  sectionLabel: {
    fontSize: 13,
    fontWeight: "700",
    color: "#666",
    textTransform: "uppercase",
    letterSpacing: 0.4,
    marginBottom: 8,
  },
  card: {
    backgroundColor: "#fff",
    borderRadius: 12,
    overflow: "hidden",
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "#e0e0e0",
  },
  cover: { width: "100%", height: 140 },
  cardBody: { padding: 12, gap: 4 },
  game: { fontSize: 12, fontWeight: "700" },
  meta: { fontSize: 12, color: "#888" },
  cardTitle: { fontSize: 16, fontWeight: "800", color: "#111" },
  excerpt: { fontSize: 14, color: "#555", lineHeight: 20 },
  readMore: { fontWeight: "700", marginTop: 4 },
});
