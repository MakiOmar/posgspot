import { StyleSheet, Text, View } from "react-native";
import { useApp } from "../../contexts/AppContext";
import { useRtl } from "../../lib/rtl";

type Props = {
  average: number;
  count?: number;
  size?: "sm" | "md";
  showCount?: boolean;
  showAverage?: boolean;
};

/** Read-only star display for card / PDP aggregates. */
export function StarRating({
  average,
  count = 0,
  size = "sm",
  showCount,
  showAverage = true,
}: Props) {
  const { t } = useApp();
  const { row, textAlign } = useRtl();
  const avg = Math.max(0, Math.min(5, Number(average) || 0));
  const rounded = Math.round(avg * 2) / 2;
  const starSize = size === "md" ? 16 : 13;
  const shouldShowCount = showCount ?? count > 0;

  return (
    <View
      style={[styles.wrap, { flexDirection: row }]}
      accessibilityRole="text"
      accessibilityLabel={t("reviews.averageTitle", {
        average: avg.toFixed(1),
        count,
      })}
    >
      <View style={[styles.stars, { flexDirection: row }]}>
        {[1, 2, 3, 4, 5].map((star) => {
          const filled = rounded >= star;
          const half = !filled && rounded >= star - 0.5;
          return (
            <Text
              key={star}
              style={[
                styles.star,
                { fontSize: starSize },
                filled || half ? styles.starOn : styles.starOff,
              ]}
            >
              {half ? "⯨" : "★"}
            </Text>
          );
        })}
      </View>
      {shouldShowCount ? (
        <Text style={[styles.meta, { textAlign, fontSize: starSize }]}>
          {showAverage ? `${avg.toFixed(1)} ` : ""}
          ({t("reviews.countLabel", { count })})
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { alignItems: "center", gap: 6, flexWrap: "wrap" },
  stars: { alignItems: "center", gap: 1 },
  star: { lineHeight: 18 },
  starOn: { color: "#F5A623" },
  starOff: { color: "rgba(120,120,120,0.45)" },
  meta: { color: "#666", fontWeight: "600" },
});
