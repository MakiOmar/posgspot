import { useEffect, useMemo, useState } from "react";
import {
  Image,
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
  useWindowDimensions,
} from "react-native";
import { WebView } from "react-native-webview";
import { useLocalSearchParams, useRouter } from "expo-router";
import { ApiError, fetchCommunityPost, submitCommunityApplication } from "../../lib/api";
import { useApp } from "../../contexts/AppContext";
import { toast } from "../../lib/toast";
import { LabeledInput } from "../LabeledInput";
import { PhoneInput } from "../PhoneInput";
import { LoadingBlock, PrimaryButton, Screen } from "../ui";
import type { CommunityPostDetail, CommunityPostType } from "../../lib/types";

function stripUnsafeHtml(html: string): string {
  return html
    .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, "")
    .replace(/\son\w+\s*=\s*(['"]).*?\1/gi, "")
    .replace(/\son\w+\s*=\s*[^\s>]+/gi, "")
    .replace(/javascript:/gi, "");
}

function wrapHtml(html: string, isRtl: boolean): string {
  const safe = stripUnsafeHtml(html || "");
  return `<!DOCTYPE html><html dir="${isRtl ? "rtl" : "ltr"}"><head>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src https: data:; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none';" />
<style>
  body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, sans-serif;
    font-size: 16px; line-height: 1.55; color: #222; }
  img, video { max-width: 100%; height: auto; }
  a { color: #0a7; }
</style></head><body>${safe}</body></html>`;
}

function formatWhen(value: string | null | undefined, locale: string): string {
  if (!value) return "";
  const d = new Date(value);
  return Number.isNaN(d.getTime())
    ? value
    : d.toLocaleDateString(locale === "ar" ? "ar-EG" : "en-EG", {
        year: "numeric",
        month: "long",
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

function HtmlBlock({
  html,
  isRtl,
  width,
}: {
  html?: string | null;
  isRtl: boolean;
  width: number;
}) {
  const [height, setHeight] = useState(120);
  const content = (html || "").trim();
  if (!content) return null;

  return (
    <WebView
      originWhitelist={["about:blank"]}
      source={{ html: wrapHtml(content, isRtl) }}
      style={{ width: width - 32, height, backgroundColor: "transparent" }}
      scrollEnabled={false}
      javaScriptEnabled
      setSupportMultipleWindows={false}
      onShouldStartLoadWithRequest={(req) => {
        const url = req.url || "";
        return url === "about:blank" || url.startsWith("data:text/html");
      }}
      onMessage={(event) => {
        const next = Number(event.nativeEvent.data);
        if (!Number.isNaN(next) && next > 40) {
          setHeight(next);
        }
      }}
      injectedJavaScript={`
        setTimeout(function(){
          var h = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
          window.ReactNativeWebView.postMessage(String(h));
        }, 50);
        true;
      `}
    />
  );
}

type Props = {
  expectedType: CommunityPostType;
  listPath: string;
};

/** Shared community detail used by tournament / event / news slug routes. */
export function CommunityDetailScreen({ expectedType, listPath }: Props) {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const { t, locale, settings, contact, accent } = useApp();
  const router = useRouter();
  const { width } = useWindowDimensions();
  const enabled = settings?.community?.enabled === true;
  const isRtl = locale === "ar";
  const [post, setPost] = useState<CommunityPostDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [notFound, setNotFound] = useState(false);
  const [name, setName] = useState("");
  const [dialCode, setDialCode] = useState("+20");
  const [nationalNumber, setNationalNumber] = useState("");
  const [fullPhone, setFullPhone] = useState("");
  const [submitting, setSubmitting] = useState(false);

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

  useEffect(() => {
    if (!contact) return;
    if (!name) {
      setName(contact.name || "");
    }
  }, [contact, name]);

  const facts = useMemo(() => {
    if (!post) return [] as Array<{ label: string; value: string }>;
    const rows: Array<{ label: string; value: string }> = [];
    if (post.game_title) rows.push({ label: t("community.game"), value: post.game_title });
    if (post.location?.name) {
      rows.push({ label: t("community.location"), value: post.location.name });
    }
    if (post.prize_pool) {
      rows.push({ label: t("community.prizePool"), value: post.prize_pool });
    }
    if (post.entry_fee) {
      rows.push({ label: t("community.entryFee"), value: post.entry_fee });
    }
    if (post.available_spots != null) {
      rows.push({ label: t("community.spots"), value: String(post.available_spots) });
    }
    if (post.winner) rows.push({ label: t("community.winner"), value: post.winner });
    return rows;
  }, [post, t]);

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

  const range = formatRange(post.starts_at, post.ends_at, locale, t);
  const dateLine =
    range ||
    formatWhen(
      expectedType === "news" ? post.published_at : post.starts_at,
      locale,
    );
  const regOpen = Boolean(post.registration_open);
  const mode = post.registration_mode || "off";
  const detailBase =
    expectedType === "news"
      ? "/gaming-news"
      : expectedType === "tournament"
        ? "/tournaments"
        : "/events";

  const submitInternal = async () => {
    if (!name.trim() || !fullPhone.trim()) {
      toast.error(t("community.applyFailed"));
      return;
    }
    setSubmitting(true);
    try {
      await submitCommunityApplication(
        post.slug,
        {
          name: name.trim(),
          mobile: fullPhone.trim(),
          dial_code: dialCode,
          source: "mobile",
        },
        locale,
      );
      toast.success(t("community.applySuccess"));
      setName("");
      setNationalNumber("");
      setFullPhone("");
    } catch (e) {
      toast.error(
        e instanceof ApiError
          ? e.message || t("community.applyFailed")
          : t("community.applyFailed"),
      );
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={{ paddingBottom: 40 }}>
        {post.cover_url ? (
          <Image source={{ uri: post.cover_url }} style={styles.cover} />
        ) : null}
        <View style={styles.body}>
          <Text style={styles.title}>{post.title}</Text>
          {dateLine ? <Text style={styles.meta}>{dateLine}</Text> : null}
          {post.excerpt ? <Text style={styles.excerpt}>{post.excerpt}</Text> : null}

          {facts.length > 0 ? (
            <View style={styles.facts}>
              {facts.map((row) => (
                <View key={row.label} style={styles.factRow}>
                  <Text style={styles.factLabel}>{row.label}</Text>
                  <Text style={styles.factValue}>{row.value}</Text>
                </View>
              ))}
            </View>
          ) : null}

          <HtmlBlock html={post.body} isRtl={isRtl} width={width} />

          {post.rules ? (
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>{t("community.rules")}</Text>
              <HtmlBlock html={post.rules} isRtl={isRtl} width={width} />
            </View>
          ) : null}
          {post.results ? (
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>{t("community.results")}</Text>
              <HtmlBlock html={post.results} isRtl={isRtl} width={width} />
            </View>
          ) : null}
          {post.highlights ? (
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>{t("community.highlights")}</Text>
              <HtmlBlock html={post.highlights} isRtl={isRtl} width={width} />
            </View>
          ) : null}
          {post.recap ? (
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>{t("community.recap")}</Text>
              <HtmlBlock html={post.recap} isRtl={isRtl} width={width} />
            </View>
          ) : null}

          {post.media && post.media.length > 0 ? (
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>{t("community.gallery")}</Text>
              <View style={styles.gallery}>
                {post.media.map((item, index) => (
                  <Pressable
                    key={`${item.url}-${index}`}
                    onPress={() => void Linking.openURL(item.url)}
                    style={styles.galleryItem}
                  >
                    {item.kind === "image" ? (
                      <Image source={{ uri: item.url }} style={styles.galleryImage} />
                    ) : (
                      <Text style={[styles.galleryVideo, { color: accent }]}>
                        {item.caption || t("community.gallery")}
                      </Text>
                    )}
                  </Pressable>
                ))}
              </View>
            </View>
          ) : null}

          {regOpen && mode === "external" && post.registration_url ? (
            <View style={styles.section}>
              {post.registration_details ? (
                <Text style={styles.excerpt}>{post.registration_details}</Text>
              ) : null}
              <PrimaryButton
                label={t("community.registerNow")}
                onPress={() => void Linking.openURL(post.registration_url!)}
              />
            </View>
          ) : null}

          {regOpen && mode === "internal" ? (
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>{t("community.registerNow")}</Text>
              {post.registration_details ? (
                <Text style={styles.excerpt}>{post.registration_details}</Text>
              ) : null}
              <LabeledInput
                label={t("community.name")}
                value={name}
                onChangeText={setName}
              />
              <PhoneInput
                label={t("community.mobile")}
                dialCode={dialCode}
                nationalNumber={nationalNumber}
                onChange={({ dialCode: nextDial, nationalNumber: nextNational, fullPhone: nextFull }) => {
                  setDialCode(nextDial);
                  setNationalNumber(nextNational);
                  setFullPhone(nextFull);
                }}
              />
              <PrimaryButton
                label={submitting ? t("community.submitting") : t("community.submitApply")}
                onPress={() => void submitInternal()}
                disabled={submitting}
              />
            </View>
          ) : null}

          {post.related_posts && post.related_posts.length > 0 ? (
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>{t("community.related")}</Text>
              {post.related_posts.map((related) => (
                <Pressable
                  key={related.id}
                  style={styles.relatedCard}
                  onPress={() =>
                    router.push(`${detailBase}/${related.slug}` as never)
                  }
                >
                  {related.cover_url ? (
                    <Image
                      source={{ uri: related.cover_url }}
                      style={styles.relatedCover}
                    />
                  ) : null}
                  <Text style={styles.relatedTitle}>{related.title}</Text>
                </Pressable>
              ))}
            </View>
          ) : null}

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
  meta: { fontSize: 13, color: "#888" },
  excerpt: { fontSize: 15, color: "#555", lineHeight: 22 },
  facts: { gap: 8, marginVertical: 4 },
  factRow: { gap: 2 },
  factLabel: { fontSize: 11, color: "#888", textTransform: "uppercase" },
  factValue: { fontSize: 15, fontWeight: "700", color: "#222" },
  section: { marginTop: 12, gap: 8 },
  sectionTitle: { fontSize: 17, fontWeight: "800", color: "#111" },
  gallery: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  galleryItem: {
    width: "47%",
    borderRadius: 8,
    overflow: "hidden",
    backgroundColor: "#f2f2f2",
  },
  galleryImage: { width: "100%", height: 100 },
  galleryVideo: {
    padding: 16,
    fontWeight: "700",
    textAlign: "center",
  },
  relatedCard: {
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "#e0e0e0",
    borderRadius: 10,
    overflow: "hidden",
    backgroundColor: "#fff",
    marginBottom: 8,
  },
  relatedCover: { width: "100%", height: 100 },
  relatedTitle: { padding: 10, fontWeight: "700", color: "#111" },
});
