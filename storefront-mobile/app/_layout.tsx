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
          <Stack screenOptions={{ headerShown: false }} />
        </CartProvider>
      </AppProvider>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: "#F7F7F5" },
});
