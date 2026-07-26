import { Pressable, StyleSheet, Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import { useCart } from "../../contexts/CartContext";

/** Cart icon with badge for Stack headers. */
export function HeaderCartButton() {
  const router = useRouter();
  const { count } = useCart();

  return (
    <Pressable
      onPress={() => router.push("/(tabs)/cart")}
      style={styles.wrap}
      hitSlop={8}
    >
      <Ionicons name="cart-outline" size={24} color="#111" />
      {count > 0 ? (
        <View style={[styles.badge, { backgroundColor: "#E53935" }]}>
          <Text style={styles.badgeText}>{count > 99 ? "99+" : count}</Text>
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
  badgeText: { color: "#fff", fontSize: 10, fontWeight: "700" },
});
