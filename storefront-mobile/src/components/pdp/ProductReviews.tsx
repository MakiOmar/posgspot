import { Pressable, StyleSheet, Text, View } from "react-native";
import { LabeledInput } from "../LabeledInput";
import { PrimaryButton } from "../ui";
import { StarRating } from "../catalog/StarRating";
import { useApp } from "../../contexts/AppContext";
import { useRtl } from "../../lib/rtl";
import type {
  ProductReviewItem,
  ReviewEligibility,
} from "../../lib/types";

type Props = {
  reviews: ProductReviewItem[];
  eligibility: ReviewEligibility | null;
  token: string | null;
  reviewRating: number;
  reviewTitle: string;
  reviewBody: string;
  reviewBusy: boolean;
  onRatingChange: (rating: number) => void;
  onTitleChange: (title: string) => void;
  onBodyChange: (body: string) => void;
  onSubmit: () => void;
  onSignIn: () => void;
};

function reviewerInitials(name: string): string {
  return (
    name
      .trim()
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((p) => p[0])
      .join("")
      .toUpperCase() || "?"
  );
}

/**
 * Product reviews list + write-review form.
 * Uses `.map()` (not FlatList) so it can sit inside a parent ScrollView.
 */
export function ProductReviews({
  reviews,
  eligibility,
  token,
  reviewRating,
  reviewTitle,
  reviewBody,
  reviewBusy,
  onRatingChange,
  onTitleChange,
  onBodyChange,
  onSubmit,
  onSignIn,
}: Props) {
  const { t, accent } = useApp();
  const { row, textAlign, writingDirection } = useRtl();

  return (
    <View style={styles.reviews}>
      <Text style={[styles.section, { textAlign, writingDirection }]}>
        {t("reviews.title")} ({reviews.length})
      </Text>
      {reviews.map((r) => {
        const name = r.author_name || "Customer";
        return (
          <View key={r.id} style={styles.reviewCard}>
            <View style={[styles.reviewHead, { flexDirection: row }]}>
              <View style={styles.reviewAvatar}>
                <Text style={styles.reviewAvatarText}>
                  {reviewerInitials(name)}
                </Text>
              </View>
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={[styles.reviewAuthor, { textAlign }]}>{name}</Text>
                <StarRating
                  average={r.rating}
                  count={0}
                  size="sm"
                  showAverage={false}
                />
              </View>
            </View>
            {r.title ? (
              <Text
                style={[styles.reviewTitle, { textAlign, writingDirection }]}
              >
                {r.title}
              </Text>
            ) : null}
            <Text style={[styles.reviewBody, { textAlign, writingDirection }]}>
              {r.body}
            </Text>
            {r.is_verified_purchase ? (
              <Text style={[styles.reviewMeta, { textAlign, writingDirection }]}>
                {t("reviews.verified")}
              </Text>
            ) : null}
          </View>
        );
      })}
      {token && eligibility?.can_review ? (
        <View style={styles.reviewForm}>
          <Text style={[styles.section, { textAlign, writingDirection }]}>
            {t("reviews.write")}
          </Text>
          <View style={[styles.varRow, { flexDirection: row }]}>
            {[1, 2, 3, 4, 5].map((n) => (
              <Pressable key={n} onPress={() => onRatingChange(n)}>
                <Text
                  style={{
                    fontSize: 22,
                    color: n <= reviewRating ? accent : "#ccc",
                  }}
                >
                  ★
                </Text>
              </Pressable>
            ))}
          </View>
          <LabeledInput
            label={t("reviews.titlePlaceholder")}
            value={reviewTitle}
            onChangeText={onTitleChange}
          />
          <LabeledInput
            label={t("reviews.bodyPlaceholder")}
            value={reviewBody}
            onChangeText={onBodyChange}
            multiline
            style={{ height: 90, textAlignVertical: "top" }}
          />
          <PrimaryButton
            label={reviewBusy ? t("common.loading") : t("reviews.submit")}
            disabled={reviewBusy || !reviewBody.trim()}
            onPress={onSubmit}
          />
        </View>
      ) : token && eligibility && !eligibility.can_review ? (
        <Text style={[styles.meta, { textAlign, writingDirection }]}>
          {eligibility.message || t("reviews.notEligible")}
        </Text>
      ) : !token ? (
        <PrimaryButton label={t("reviews.signIn")} onPress={onSignIn} />
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  reviews: { paddingHorizontal: 16, marginTop: 24 },
  section: { fontSize: 16, fontWeight: "800", marginBottom: 8, color: "#111" },
  varRow: { flexWrap: "wrap", gap: 8 },
  reviewCard: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 12,
    marginBottom: 10,
    gap: 8,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "#eee",
  },
  reviewHead: { gap: 10, alignItems: "center" },
  reviewAvatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: "#1f2937",
    alignItems: "center",
    justifyContent: "center",
  },
  reviewAvatarText: { color: "#fff", fontWeight: "800", fontSize: 13 },
  reviewAuthor: { fontWeight: "700", fontSize: 15, color: "#111" },
  reviewTitle: { fontWeight: "700", marginBottom: 2 },
  reviewBody: { color: "#333", lineHeight: 20 },
  reviewMeta: { color: "#888", fontSize: 12 },
  reviewForm: { marginTop: 12, gap: 8 },
  meta: { color: "#666", marginBottom: 6 },
});
