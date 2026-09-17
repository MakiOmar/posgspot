import { useEffect, useState } from "react";
import { Image, ScrollView, StyleSheet, Text, View } from "react-native";
import { useLocalSearchParams, useRouter } from "expo-router";
import { fetchCommunityPost } from "../../src/lib/api";
import { useApp } from "../../src/contexts/AppContext";
import { LoadingBlock, PrimaryButton, Screen } from "../../src/components/ui";
import type { CommunityPostDetail, CommunityPostType } from "../../src/lib/types";

function stripHtml(html: string | undefined): string {
  if (!html) return "";
  return html
    .replace(/<br\s*\/?>/gi, "\n")
    .replace(/<\/p>/gi, "\n\n")
    .replace(/<[^>]+>/g, "")
    .replace(/&nbsp;/g, " ")
    .replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .trim();
}

type Props = {
  expectedType: CommunityPostType;
  listPath: string;
};

/** Shared community detail used by tournament / event / news slug routes. */
export function CommunityDetailScreen({ expectedType, listPath }: Props) {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const { t, locale, settings } = useApp();
  const router = useRouter();
  const enabled = settings?.community?.enabled === true;
  const [post, setPost] = useState<CommunityPostDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [notFound, setNotFound] = useState(false);

  useEffect(() => {
    if (!enabled || !slug) {
      setLoading(false);
      setNotFound(true);
      return;
    }
    setLoading(true);
    void fetchCommunityPost(String(slug), locale)
      .then(({ data }) => {
        if (data.type !== expectedType) {
          setNotFound(true);
          setPost(null);
          return;
        }
        setPost(data);
        setNotFound(false);
      })
      .catch(() => {
        setPost(null);
        setNotFound(true);
      })
      .finally(() => setLoading(false));
  }, [enabled, slug, locale, expectedType]);

  if (!enabled) {
    return (
      <Screen>
        <Text style={styles.muted}>{t("community.unavailable")}</Text>
      </Screen>
    );
  }

  if (loading) {
    return (
      <Screen>
        <LoadingBlock />
      </Screen>
    );
  }

  if (notFound || !post) {
    return (
      <Screen>
        <Text style={styles.muted}>{t("community.empty")}</Text>
        <PrimaryButton
          label={t("community.back")}
          onPress={() => router.replace(listPath as never)}
        />
      </Screen>
    );
  }

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={{ paddingBottom: 40 }}>
        {post.cover_url ? (
          <Image source={{ uri: post.cover_url }} style={styles.cover} />
        ) : null}
        <View style={styles.body}>
          <Text style={styles.title}>{post.title}</Text>
          {post.excerpt ? <Text style={styles.excerpt}>{post.excerpt}</Text> : null}
          <Text style={styles.content}>{stripHtml(post.body)}</Text>
          <PrimaryButton
            label={t("community.back")}
            onPress={() => router.replace(listPath as never)}
          />
        </View>
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  muted: { color: "#888", padding: 16 },
  cover: { width: "100%", height: 200 },
  body: { padding: 16, gap: 10 },
  title: { fontSize: 22, fontWeight: "800", color: "#111" },
  excerpt: { fontSize: 15, color: "#555", lineHeight: 22 },
  content: { fontSize: 15, color: "#333", lineHeight: 24, marginBottom: 16 },
});
