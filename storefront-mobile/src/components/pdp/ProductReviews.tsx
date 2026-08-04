import { Pressable, StyleSheet, Text, View } from "react-native";
import { LabeledInput } from "../LabeledInput";
import { PrimaryButton } from "../ui";
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
      {reviews.map((r) => (
        <View key={r.id} style={styles.reviewCard}>
          <Text style={styles.reviewStars}>
            {"★".repeat(r.rating)}
            {"☆".repeat(Math.max(0, 5 - r.rating))}
          </Text>
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
          <Text style={[styles.reviewMeta, { textAlign, writingDirection }]}>
            {r.author_name || "Customer"}
            {r.is_verified_purchase ? ` · ${t("reviews.verified")}` : ""}
          </Text>
        </View>
      ))}
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
    borderRadius: 10,
    padding: 12,
    marginBottom: 8,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "#eee",
  },
  reviewStars: { color: "#F5A623", marginBottom: 4 },
  reviewTitle: { fontWeight: "700", marginBottom: 4 },
  reviewBody: { color: "#333", lineHeight: 20 },
  reviewMeta: { color: "#888", fontSize: 12, marginTop: 6 },
  reviewForm: { marginTop: 12, gap: 8 },
  meta: { color: "#666", marginBottom: 6 },
});
