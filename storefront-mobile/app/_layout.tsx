import "react-native-gesture-handler";
import "react-native-reanimated";
import { Stack, SplashScreen } from "expo-router";
import { useEffect, useState, type ReactNode } from "react";
import { StyleSheet, View } from "react-native";
import { SafeAreaProvider } from "react-native-safe-area-context";
import { BrandSplash } from "../src/components/BrandSplash";
import { MaintenanceGate } from "../src/components/MaintenanceGate";
import { ToastHost } from "../src/components/ToastHost";
import { AppProvider, useApp } from "../src/contexts/AppContext";
import { CartProvider } from "../src/contexts/CartContext";
import { WishlistProvider } from "../src/contexts/WishlistContext";

// Keep native splash until the branded JS splash is on screen.
void SplashScreen.preventAutoHideAsync().catch(() => undefined);

const MIN_SPLASH_MS = 1800;

function SplashGate({ children }: { children: ReactNode }) {
  const { loading } = useApp();
  const [minElapsed, setMinElapsed] = useState(false);
  const [showSplash, setShowSplash] = useState(true);

  useEffect(() => {
    // Native splash → branded splash (same black bg) without a flash.
    void SplashScreen.hideAsync().catch(() => undefined);
    const timer = setTimeout(() => setMinElapsed(true), MIN_SPLASH_MS);
    return () => clearTimeout(timer);
  }, []);

  useEffect(() => {
    if (!loading && minElapsed) {
      setShowSplash(false);
    }
  }, [loading, minElapsed]);

  return (
    <View style={styles.fill}>
      {children}
      <BrandSplash visible={showSplash} />
    </View>
  );
}

export default function RootLayout() {
  return (
    <SafeAreaProvider style={styles.root}>
      <AppProvider>
        <CartProvider>
          <WishlistProvider>
            <SplashGate>
              <MaintenanceGate>
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
                <Stack.Screen
                  name="forgot-password"
                  options={{ title: "Forgot password" }}
                />
                <Stack.Screen
                  name="reset-password"
                  options={{ title: "Reset password" }}
                />
                <Stack.Screen name="wishlist" options={{ title: "Wishlist" }} />
                <Stack.Screen name="account/profile" options={{ title: "Profile" }} />
                <Stack.Screen name="account/orders/index" options={{ title: "Orders" }} />
                <Stack.Screen name="account/orders/[id]" options={{ title: "Order" }} />
                <Stack.Screen name="stores" options={{ title: "Stores" }} />
                <Stack.Screen name="contact" options={{ title: "Contact" }} />
                <Stack.Screen name="repair-status" options={{ title: "Repair" }} />
                <Stack.Screen name="about" options={{ title: "About" }} />
                <Stack.Screen name="faq" options={{ title: "FAQ" }} />
                <Stack.Screen name="legal/[slug]" options={{ title: "Legal" }} />
                <Stack.Screen
                  name="maintenance"
                  options={{ title: "Maintenance", headerShown: false }}
                />
                <Stack.Screen name="+not-found" options={{ title: "Not found" }} />
              </Stack>
              </MaintenanceGate>
              <ToastHost />
            </SplashGate>
          </WishlistProvider>
        </CartProvider>
      </AppProvider>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: "#000000" },
  fill: { flex: 1 },
});
