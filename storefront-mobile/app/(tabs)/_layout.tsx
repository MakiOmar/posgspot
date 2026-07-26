import { Tabs } from "expo-router";
import type { ComponentProps } from "react";
import FontAwesome from "@expo/vector-icons/FontAwesome";
import { useApp } from "../../src/contexts/AppContext";
import { useCart } from "../../src/contexts/CartContext";

function TabIcon({
  name,
  color,
}: {
  name: ComponentProps<typeof FontAwesome>["name"];
  color: string;
}) {
  return (
    <FontAwesome
      name={name}
      size={22}
      color={color}
      style={{ marginBottom: -2 }}
    />
  );
}

export default function TabLayout() {
  const { t, accent } = useApp();
  const { count } = useCart();

  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: accent,
        tabBarInactiveTintColor: "#888",
        headerShown: true,
        headerShadowVisible: false,
        headerStyle: { backgroundColor: "#F7F7F5" },
        tabBarStyle: { backgroundColor: "#fff", borderTopColor: "#eee" },
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: t("nav.home"),
          headerShown: false,
          tabBarIcon: ({ color }) => (
            <TabIcon name="home" color={String(color)} />
          ),
        }}
      />
      <Tabs.Screen
        name="shop"
        options={{
          title: t("nav.shop"),
          headerShown: false,
          tabBarIcon: ({ color }) => (
            <TabIcon name="shopping-bag" color={String(color)} />
          ),
        }}
      />
      <Tabs.Screen
        name="cart"
        options={{
          title: t("nav.cart"),
          tabBarBadge: count > 0 ? count : undefined,
          tabBarIcon: ({ color }) => (
            <TabIcon name="shopping-cart" color={String(color)} />
          ),
        }}
      />
      <Tabs.Screen
        name="wishlist"
        options={{
          title: t("nav.wishlist"),
          tabBarIcon: ({ color }) => (
            <TabIcon name="heart" color={String(color)} />
          ),
        }}
      />
      <Tabs.Screen
        name="account"
        options={{
          title: t("account.title"),
          tabBarIcon: ({ color }) => (
            <TabIcon name="user" color={String(color)} />
          ),
        }}
      />
    </Tabs>
  );
}
