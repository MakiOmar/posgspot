import { useEffect, useState } from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { useRouter } from "expo-router";
import { useApp } from "../../contexts/AppContext";
import { fetchCommunityPosts } from "../../lib/api";
import { useRtl } from "../../lib/rtl";
import type { CommunityPostSummary, CommunityPostType } from "../../lib/types";
import { FormTextInput } from "../FormTextInput";
import { NewsletterForm } from "../forms/NewsletterForm";
import { PrimaryButton } from "../ui";

const UPCOMING_LIMIT = 5;

type Props = {
  /** List screen type; its own upcoming list is skipped (already on screen). */
  currentListType?: CommunityPostType;
  initialQuery?: string;
  /** News list filters in place; other screens open the news list with `?q=`. */
  onSearch?: (query: string) => void;
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

/** Compact mobile take on the website news sidebar: newsletter, news search, upcoming posts. */
export function CommunityExtras({ currentListType, initialQuery = "", onSearch }: Props) {
  const { t, locale } = useApp();
  const router = useRouter();
  const { textAlign, writingDirection, row } = useRtl();
  const [query, setQuery] = useState(initialQuery);
  const [tournaments, setTournaments] = useState<CommunityPostSummary[]>([]);
  const [events, setEvents] = useState<CommunityPostSummary[]>([]);
  const showTournaments = currentListType !== "tournament";
  const showEvents = currentListType !== "event";

  useEffect(() => {
    setQuery(initialQuery);
  }, [initialQuery]);

  useEffect(() => {
    let cancelled = false;
    const load = (type: CommunityPostType, set: (posts: CommunityPostSummary[]) => void) =>
      fetchCommunityPosts({ type, scope: "upcoming", limit: UPCOMING_LIMIT }, locale)
        .then(({ data }) => {
          if (!cancelled) set((data || []).slice(0, UPCOMING_LIMIT));
        })
        .catch(() => {
          if (!cancelled) set([]);
        });
    if (showTournaments) void load("tournament", setTournaments);
    if (showEvents) void load("event", setEvents);
    return () => {
      cancelled = true;
    };
  }, [locale, showTournaments, showEvents]);

  const submitSearch = () => {
    const q = query.trim();
    if (onSearch) {
      onSearch(q);
      return;
    }
    router.push({ pathname: "/gaming-news", params: q ? { q } : {} } as never);
  };

  const upcoming = (titleKey: string, posts: CommunityPostSummary[], detailBase: string) => (
    <View style={styles.card}>
      <Text style={[styles.title, { textAlign, writingDirection }]}>{t(titleKey)}</Text>
      {posts.length === 0 ? (
        <Text style={[styles.muted, { textAlign, writingDirection }]}>
          {t("community.noUpcoming")}
        </Text>
      ) : (
        posts.map((post) => (
          <Pressable
            key={post.id}
            onPress={() => router.push(`${detailBase}/${post.slug}` as never)}
            style={styles.item}
            accessibilityRole="link"
          >
            <Text style={[styles.itemTitle, { textAlign, writingDirection }]}>{post.title}</Text>
            {post.starts_at ? (
              <Text style={[styles.muted, { textAlign }]}>{formatWhen(post.starts_at, locale)}</Text>
            ) : null}
          </Pressable>
        ))
      )}
    </View>
  );

  return (
    <View style={styles.wrap}>
      <NewsletterForm />

      <View style={styles.card}>
        <Text style={[styles.title, { textAlign, writingDirection }]}>
          {t("community.searchTitle")}
        </Text>
        <View style={[styles.searchRow, { flexDirection: row }]}>
          <FormTextInput
            value={query}
            onChangeText={setQuery}
            placeholder={t("community.searchPlaceholder")}
            returnKeyType="search"
            onSubmitEditing={submitSearch}
            style={styles.searchInput}
          />
          <PrimaryButton label={t("community.searchSubmit")} onPress={submitSearch} />
        </View>
        <Text style={[styles.muted, { textAlign, writingDirection }]}>
          {t("community.searchHint")}
        </Text>
      </View>

      {showTournaments ? upcoming("community.upcomingTournaments", tournaments, "/tournaments") : null}
      {showEvents ? upcoming("community.upcomingEvents", events, "/events") : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { gap: 12, marginTop: 8 },
  card: {
    backgroundColor: "#fff",
    borderRadius: 14,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "#e5e5e5",
    padding: 16,
    gap: 8,
  },
  title: { fontSize: 17, fontWeight: "800", color: "#111" },
  muted: { color: "#777", fontSize: 13, lineHeight: 18 },
  searchRow: { gap: 8, alignItems: "center" },
  searchInput: { flex: 1, paddingVertical: 10 },
  item: {
    paddingVertical: 8,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: "#eee",
    gap: 2,
  },
  itemTitle: { fontWeight: "700", color: "#111" },
});
