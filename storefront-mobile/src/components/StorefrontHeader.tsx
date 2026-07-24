import FontAwesome from "@expo/vector-icons/FontAwesome";
import { useRouter } from "expo-router";
import { Pressable, StyleSheet, Text, TextInput, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { useApp } from "../contexts/AppContext";
import { useCart } from "../contexts/CartContext";

type Props = {
  /** Show search row (home / shop). */
  showSearch?: boolean;
  /** Optional controlled search value (shop tab). */
  searchValue?: string;
  onSearchChange?: (value: string) => void;
  onSearchSubmit?: () => void;
};

/**
 * Storefront chrome matching the mobile mock: welcome + brand, cart/menu, search.
 */
export function StorefrontHeader({
  showSearch = true,
  searchValue,
  onSearchChange,
  onSearchSubmit,
}: Props) {
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { t, settings, accent, displayName, token } = useApp();
  const { count } = useCart();
  const brand = settings?.business_name || "Games Spot";
  const welcomeName = token && displayName ? displayName : brand;

  return (
    <View style={[styles.wrap, { paddingTop: Math.max(insets.top, 8) }]}>
      <View style={styles.topRow}>
        <View style={styles.brandBlock}>
          <View style={[styles.avatar, { backgroundColor: accent }]}>
            <FontAwesome name="gamepad" size={18} color="#111" />
          </View>
          <View style={styles.brandText}>
            <Text style={styles.welcome}>{t("home.welcome")}</Text>
            <Text style={styles.brand} numberOfLines={1}>
              {welcomeName}
            </Text>
          </View>
        </View>
        <View style={styles.actions}>
          <Pressable
            style={styles.iconBtn}
            onPress={() => router.push("/(tabs)/cart")}
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
          <Pressable
            style={styles.iconBtn}
            onPress={() => router.push("/(tabs)/account")}
            accessibilityRole="button"
            accessibilityLabel={t("nav.account")}
          >
            <FontAwesome name="bars" size={20} color="#222" />
          </Pressable>
        </View>
      </View>

      {showSearch ? (
        <View style={styles.searchRow}>
          <Pressable
            style={styles.searchField}
            onPress={() => {
              if (onSearchChange == null) {
                router.push("/search");
              }
            }}
          >
            <FontAwesome name="search" size={16} color="#888" />
            {onSearchChange != null ? (
              <TextInput
                style={styles.searchInput}
                value={searchValue}
                onChangeText={onSearchChange}
                placeholder={t("home.searchProducts")}
                placeholderTextColor="#999"
                returnKeyType="search"
                onSubmitEditing={onSearchSubmit}
                autoCapitalize="none"
              />
            ) : (
              <Text style={styles.searchPlaceholder}>
                {t("home.searchProducts")}
              </Text>
            )}
          </Pressable>
          <Pressable
            style={[styles.filterBtn, { backgroundColor: accent }]}
            onPress={() => router.push("/(tabs)/shop")}
            accessibilityRole="button"
            accessibilityLabel={t("nav.shop")}
          >
            <FontAwesome name="sliders" size={18} color="#111" />
          </Pressable>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    paddingHorizontal: 16,
    paddingBottom: 10,
    backgroundColor: "#F7F7F5",
  },
  topRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 12,
  },
  brandBlock: { flexDirection: "row", alignItems: "center", flex: 1, gap: 10 },
  avatar: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: "center",
    justifyContent: "center",
  },
  brandText: { flex: 1 },
  welcome: { fontSize: 12, color: "#888", marginBottom: 2 },
  brand: { fontSize: 18, fontWeight: "800", color: "#111" },
  actions: { flexDirection: "row", alignItems: "center", gap: 4 },
  iconBtn: {
    width: 40,
    height: 40,
    alignItems: "center",
    justifyContent: "center",
  },
  badge: {
    position: "absolute",
    top: 4,
    right: 2,
    minWidth: 16,
    height: 16,
    borderRadius: 8,
    alignItems: "center",
    justifyContent: "center",
    paddingHorizontal: 3,
  },
  badgeText: { color: "#111", fontSize: 10, fontWeight: "800" },
  searchRow: { flexDirection: "row", alignItems: "center", gap: 10 },
  searchField: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    backgroundColor: "#EFEBE3",
    borderRadius: 14,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  searchInput: { flex: 1, fontSize: 15, color: "#222", padding: 0 },
  searchPlaceholder: { flex: 1, fontSize: 15, color: "#999" },
  filterBtn: {
    width: 48,
    height: 48,
    borderRadius: 14,
    alignItems: "center",
    justifyContent: "center",
  },
});
