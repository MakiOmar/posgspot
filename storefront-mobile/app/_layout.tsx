import "react-native-gesture-handler";
import "react-native-reanimated";
import { Stack, SplashScreen } from "expo-router";
import { useEffect, useState, type ReactNode } from "react";
import { StyleSheet, View } from "react-native";
import { SafeAreaProvider } from "react-native-safe-area-context";
import { KeyboardProvider } from "react-native-keyboard-controller";
import { BrandSplash } from "../src/components/BrandSplash";
import { ContactFab } from "../src/components/ContactFab";
import { MaintenanceGate } from "../src/components/MaintenanceGate";
import { PaymentResumeGate } from "../src/components/PaymentResumeGate";
import { ToastHost } from "../src/components/ToastHost";
import { AppProvider, useApp } from "../src/contexts/AppContext";
import { AvailabilityModalProvider } from "../src/contexts/AvailabilityModalContext";
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
      <KeyboardProvider>
        <AppProvider>
          <CartProvider>
            <WishlistProvider>
              <AvailabilityModalProvider>
              <SplashGate>
                <PaymentResumeGate />
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
                <Stack.Screen name="games/index" options={{ title: "Digital games" }} />
                <Stack.Screen name="games/[id]" options={{ title: "Game" }} />
                <Stack.Screen name="gift-cards/index" options={{ title: "Gift cards" }} />
                <Stack.Screen name="checkout/index" options={{ title: "Checkout" }} />
                <Stack.Screen name="checkout/payment" options={{ title: "Payment" }} />
                <Stack.Screen name="login" options={{ title: "Sign In" }} />
                <Stack.Screen name="register" options={{ title: "Register" }} />
                <Stack.Screen
                  name="verify-email"
                  options={{ title: "Verify email" }}
                />
                <Stack.Screen
                  name="forgot-password"
                  options={{ title: "Forgot password" }}
                />
                <Stack.Screen
                  name="reset-password"
                  options={{ title: "Reset password" }}
                />
                <Stack.Screen name="wishlist" options={{ title: "Wishlist" }} />
                <Stack.Screen name="account" options={{ headerShown: false }} />
                <Stack.Screen name="stores" options={{ title: "Our Stores" }} />
                <Stack.Screen name="contact" options={{ title: "Contact" }} />
                <Stack.Screen name="support" options={{ title: "Support" }} />
                <Stack.Screen name="repair-status" options={{ title: "Repair" }} />
                <Stack.Screen name="track-console" options={{ title: "Track console" }} />
                <Stack.Screen name="track-order" options={{ title: "Track my order" }} />
                <Stack.Screen
                  name="repair-truck-request"
                  options={{ title: "Book a Service Truck" }}
                />
                <Stack.Screen
                  name="request-a-product"
                  options={{ title: "Request a product" }}
                />
                <Stack.Screen name="tournaments/index" options={{ title: "Tournaments" }} />
                <Stack.Screen name="tournaments/[slug]" options={{ title: "Tournament" }} />
                <Stack.Screen name="events/index" options={{ title: "Events" }} />
                <Stack.Screen name="events/[slug]" options={{ title: "Event" }} />
                <Stack.Screen name="gaming-news/index" options={{ title: "Gaming news" }} />
                <Stack.Screen name="gaming-news/[slug]" options={{ title: "News" }} />
                <Stack.Screen name="custom-bundle" options={{ title: "Build Your Bundle" }} />
                <Stack.Screen name="sell-to-us" options={{ title: "Sell to us" }} />
                <Stack.Screen name="about" options={{ title: "About" }} />
                <Stack.Screen name="faq" options={{ title: "FAQs" }} />
                <Stack.Screen name="legal/[slug]" options={{ title: "Legal" }} />
                <Stack.Screen
                  name="maintenance"
                  options={{ title: "Maintenance", headerShown: false }}
                />
                <Stack.Screen name="+not-found" options={{ title: "Not found" }} />
              </Stack>
                </MaintenanceGate>
                <ContactFab />
                <ToastHost />
              </SplashGate>
              </AvailabilityModalProvider>
            </WishlistProvider>
          </CartProvider>
        </AppProvider>
      </KeyboardProvider>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: "#000000" },
  fill: { flex: 1 },
});
