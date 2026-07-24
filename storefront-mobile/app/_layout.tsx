import "react-native-gesture-handler";
import "react-native-reanimated";
import { Stack, SplashScreen } from "expo-router";
import { useEffect } from "react";
import { StyleSheet } from "react-native";
import { SafeAreaProvider } from "react-native-safe-area-context";
import { AppProvider } from "../src/contexts/AppContext";
import { CartProvider } from "../src/contexts/CartContext";

// expo-router also calls preventAutoHide internally; always hide once mounted.
void SplashScreen.hideAsync().catch(() => undefined);

export default function RootLayout() {
  useEffect(() => {
    SplashScreen.hide();
    void SplashScreen.hideAsync().catch(() => undefined);
  }, []);

  return (
    <SafeAreaProvider style={styles.root}>
      <AppProvider>
        <CartProvider>
          <Stack
            screenOptions={{
              headerShown: true,
              headerStyle: { backgroundColor: "#F7F7F5" },
              headerShadowVisible: false,
              headerTintColor: "#111",
              contentStyle: { backgroundColor: "#F7F7F5" },
            }}
          >
            <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
            <Stack.Screen name="products/index" options={{ title: "Shop" }} />
            <Stack.Screen name="products/[slug]" options={{ title: "Product" }} />
            <Stack.Screen name="category/[slug]" options={{ title: "Category" }} />
            <Stack.Screen name="brands/index" options={{ title: "Brands" }} />
            <Stack.Screen name="brands/[slug]" options={{ title: "Brand" }} />
            <Stack.Screen name="search" options={{ title: "Search" }} />
            <Stack.Screen name="games/index" options={{ title: "Games" }} />
            <Stack.Screen name="games/[id]" options={{ title: "Game" }} />
            <Stack.Screen name="gift-cards/index" options={{ title: "Gift cards" }} />
            <Stack.Screen name="checkout/index" options={{ title: "Checkout" }} />
            <Stack.Screen name="checkout/payment" options={{ title: "Payment" }} />
            <Stack.Screen name="login" options={{ title: "Login" }} />
            <Stack.Screen name="register" options={{ title: "Register" }} />
            <Stack.Screen name="wishlist" options={{ title: "Wishlist" }} />
            <Stack.Screen name="account/orders/index" options={{ title: "Orders" }} />
            <Stack.Screen name="account/orders/[id]" options={{ title: "Order" }} />
            <Stack.Screen name="stores" options={{ title: "Stores" }} />
            <Stack.Screen name="contact" options={{ title: "Contact" }} />
            <Stack.Screen name="repair-status" options={{ title: "Repair" }} />
            <Stack.Screen name="about" options={{ title: "About" }} />
            <Stack.Screen name="faq" options={{ title: "FAQ" }} />
            <Stack.Screen name="legal/[slug]" options={{ title: "Legal" }} />
            <Stack.Screen name="maintenance" options={{ title: "Maintenance", headerShown: false }} />
            <Stack.Screen name="+not-found" options={{ title: "Not found" }} />
          </Stack>
        </CartProvider>
      </AppProvider>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: "#F7F7F5" },
});
