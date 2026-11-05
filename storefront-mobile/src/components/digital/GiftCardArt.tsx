import { StyleSheet, Text, View } from "react-native";
import { RemoteImage } from "../RemoteImage";
import type { DigitalCardArt } from "../../lib/types";

/**
 * Generated gift card face for categories without a poster.
 * Same layout as the website `GiftCardArt` (white value band over the brand colour).
 */
export function GiftCardArt({ art }: { art: DigitalCardArt }) {
  const country = art.country_code ? ` (${art.country_code.toUpperCase()})` : "";
  return (
    <View
      style={[styles.card, { backgroundColor: art.color || "#4b5563" }]}
      accessible
      accessibilityRole="image"
      accessibilityLabel={`${art.brand_label} gift card ${art.value}${country}`}
    >
      <View style={styles.top}>
        <View>
          <Text style={styles.kicker}>بطاقة هدايا</Text>
          <Text style={styles.kicker}>GIFT CARD</Text>
        </View>
        <View style={styles.valueWrap}>
          <Text style={styles.value} numberOfLines={1} adjustsFontSizeToFit>
            {art.value}
          </Text>
          {art.flag_url ? (
            <RemoteImage uri={art.flag_url} style={styles.flag} contentFit="cover" />
          ) : null}
        </View>
      </View>
      <View style={styles.body}>
        {art.logo_url ? (
          <RemoteImage
            uri={art.logo_url}
            style={styles.logo}
            contentFit="contain"
            placeholderColor="transparent"
          />
        ) : null}
        <Text style={styles.brand} numberOfLines={2}>
          {art.brand_label}
        </Text>
        <View style={styles.pill}>
          <Text style={styles.pillText}>DIGITAL CODE</Text>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { width: "100%", aspectRatio: 1, overflow: "hidden", borderRadius: 8 },
  top: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 6,
    paddingVertical: 8,
    paddingHorizontal: 10,
    backgroundColor: "#fff",
    direction: "ltr",
  },
  kicker: { fontSize: 9, fontWeight: "700", color: "#111", letterSpacing: 0.5 },
  valueWrap: { flexDirection: "row", alignItems: "center", gap: 6, flexShrink: 1 },
  value: { fontSize: 20, fontWeight: "800", color: "#111", flexShrink: 1 },
  flag: { width: 22, height: 16, borderRadius: 2 },
  body: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
    padding: 8,
    backgroundColor: "rgba(0,0,0,0.12)",
  },
  logo: { width: 48, height: 48 },
  brand: { color: "#fff", fontSize: 16, fontWeight: "700", textAlign: "center" },
  pill: {
    paddingVertical: 3,
    paddingHorizontal: 10,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.6)",
    borderRadius: 99,
  },
  pillText: { color: "#fff", fontSize: 9, fontWeight: "600", letterSpacing: 1.2 },
});
