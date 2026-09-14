import FontAwesome from "@expo/vector-icons/FontAwesome";
import { useRouter } from "expo-router";
import { useState } from "react";
import { Pressable, StyleSheet, Text, TextInput, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { useApp } from "../contexts/AppContext";
import { useCart } from "../contexts/CartContext";
import { useRtl } from "../lib/rtl";
import { NavDrawer } from "./NavDrawer";
import { RemoteImage } from "./RemoteImage";

type Props = {
  /** Show search row (home / shop). */
  showSearch?: boolean;
  /** Optional controlled search value (shop tab). */
  searchValue?: string;
  onSearchChange?: (value: string) => void;
  onSearchSubmit?: () => void;
};

/**
 * Storefront chrome: EN = menu/cart left, welcome right; AR = welcome left, cart/menu right.
 */
export function StorefrontHeader({
  showSearch = true,
  searchValue,
  onSearchChange,
  onSearchSubmit,
}: Props) {
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { t, settings, accent, displayName, token, contact, locale } = useApp();
  const { count } = useCart();
  const { row, textAlign, writingDirection } = useRtl();
  const [menuOpen, setMenuOpen] = useState(false);
  const brand = settings?.business_name || "Games Spot";
  const welcomeName = token && displayName ? displayName : brand;
  const avatarUrl = token ? contact?.avatar_url : null;
  const isRtl = locale === "ar";
  const welcomeAlign = isRtl ? "left" : "right";

  const menuBtn = (
    <Pressable
      style={styles.iconBtn}
      onPress={() => setMenuOpen(true)}
      accessibilityRole="button"
      accessibilityLabel={t("nav.menu")}
    >
      <FontAwesome name="bars" size={20} color="#222" />
    </Pressable>
  );

  const cartBtn = (
    <Pressable
      style={styles.iconBtn}
      onPress={() => router.push("/(tabs)/cart")}
      accessibilityRole="button"
      accessibilityLabel={t("nav.cart")}
    >
      <FontAwesome name="shopping-cart" size={20} color="#222" />
      {count > 0 ? (
        <View style={[styles.badge, { backgroundColor: accent, right: 2 }]}>
          <Text style={styles.badgeText}>{count > 9 ? "9+" : count}</Text>
        </View>
      ) : null}
    </Pressable>
  );

  const avatar = (
    <View style={[styles.avatar, { backgroundColor: accent }]}>
      {avatarUrl ? (
        <RemoteImage
          uri={avatarUrl}
          style={styles.avatarImage}
          contentFit="cover"
        />
      ) : (
        <FontAwesome name="gamepad" size={18} color="#111" />
      )}
    </View>
  );

  const welcomeText = (
    <View style={styles.brandText}>
      <Text
        style={[
          styles.welcome,
          { textAlign: welcomeAlign, writingDirection },
        ]}
      >
        {t("home.welcome")}
      </Text>
      <Text
        style={[
          styles.brand,
          { textAlign: welcomeAlign, writingDirection },
        ]}
        numberOfLines={1}
      >
        {welcomeName}
      </Text>
    </View>
  );

  const welcomeBlock = (
    <View
      style={[
        styles.brandBlock,
        {
          flexDirection: "row",
          justifyContent: isRtl ? "flex-start" : "flex-end",
        },
      ]}
    >
      {isRtl ? (
        <>
          {avatar}
          {welcomeText}
        </>
      ) : (
        <>
          {welcomeText}
          {avatar}
        </>
      )}
    </View>
  );

  return (
    <View style={[styles.wrap, { paddingTop: Math.max(insets.top, 8) }]}>
      <View style={[styles.topRow, { flexDirection: "row" }]}>
        {isRtl ? welcomeBlock : null}
        <View style={[styles.actions, { flexDirection: "row" }]}>
          {isRtl ? (
            <>
              {cartBtn}
              {menuBtn}
            </>
          ) : (
            <>
              {menuBtn}
              {cartBtn}
            </>
          )}
        </View>
        {isRtl ? null : welcomeBlock}
      </View>

      {showSearch ? (
        <View style={[styles.searchRow, { flexDirection: row }]}>
          <Pressable
            style={[styles.searchField, { flexDirection: row }]}
            onPress={() => {
              if (onSearchChange == null) {
                router.push("/search");
              }
            }}
          >
            <FontAwesome name="search" size={16} color="#888" />
            {onSearchChange != null ? (
              <TextInput
                style={[styles.searchInput, { textAlign, writingDirection, color: "#111" }]}
                value={searchValue}
                onChangeText={onSearchChange}
                placeholder={t("home.searchProducts")}
                placeholderTextColor="#888"
                returnKeyType="search"
                onSubmitEditing={onSearchSubmit}
                autoCapitalize="none"
                underlineColorAndroid="transparent"
              />
            ) : (
              <Text
                style={[
                  styles.searchPlaceholder,
                  { textAlign, writingDirection },
                ]}
              >
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

      <NavDrawer visible={menuOpen} onClose={() => setMenuOpen(false)} />
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
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 12,
  },
  brandBlock: { alignItems: "center", flex: 1, gap: 10, minWidth: 0 },
  avatar: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: "center",
    justifyContent: "center",
    overflow: "hidden",
  },
  avatarImage: { width: 42, height: 42 },
  brandText: { flex: 1 },
  welcome: { fontSize: 12, color: "#888", marginBottom: 2 },
  brand: { fontSize: 18, fontWeight: "800", color: "#111" },
  actions: { alignItems: "center", gap: 4 },
  iconBtn: {
    width: 40,
    height: 40,
    alignItems: "center",
    justifyContent: "center",
  },
  badge: {
    position: "absolute",
    top: 4,
    minWidth: 16,
    height: 16,
    borderRadius: 8,
    alignItems: "center",
    justifyContent: "center",
    paddingHorizontal: 3,
  },
  badgeText: { color: "#111", fontSize: 10, fontWeight: "800" },
  searchRow: { alignItems: "center", gap: 10 },
  searchField: {
    flex: 1,
    alignItems: "center",
    gap: 10,
    backgroundColor: "#EFEBE3",
    borderRadius: 14,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  searchInput: { flex: 1, fontSize: 15, color: "#111", padding: 0 },
  searchPlaceholder: { flex: 1, fontSize: 15, color: "#999" },
  filterBtn: {
    width: 48,
    height: 48,
    borderRadius: 14,
    alignItems: "center",
    justifyContent: "center",
  },
});
