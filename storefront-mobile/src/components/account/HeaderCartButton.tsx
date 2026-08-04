import { Pressable, StyleSheet, Text, View } from "react-native";
import FontAwesome from "@expo/vector-icons/FontAwesome";
import { useRouter } from "expo-router";
import { useApp } from "../../contexts/AppContext";
import { useCart } from "../../contexts/CartContext";

/** Cart icon with badge for Stack headers (matches StorefrontHeader badge style). */
export function HeaderCartButton() {
  const router = useRouter();
  const { count } = useCart();
  const { accent, t } = useApp();

  return (
    <Pressable
      onPress={() => router.push("/(tabs)/cart")}
      style={styles.wrap}
      hitSlop={8}
      accessibilityRole="button"
      accessibilityLabel={t("nav.cart")}
    >
      <FontAwesome name="shopping-cart" size={20} color="#222" />
      {count > 0 ? (
        <View style={[styles.badge, { backgroundColor: accent }]}>
          <Text style={styles.badgeText}>{count > 9 ? "9+" : count}</Text>
        </View>
      ) : null}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  wrap: { marginRight: 12, padding: 4 },
  badge: {
    position: "absolute",
    top: 0,
    right: 0,
    minWidth: 16,
    height: 16,
    borderRadius: 8,
    alignItems: "center",
    justifyContent: "center",
    paddingHorizontal: 3,
  },
  badgeText: { color: "#111", fontSize: 10, fontWeight: "800" },
});
