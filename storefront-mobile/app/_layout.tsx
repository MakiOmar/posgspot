import { Stack, useRouter } from "expo-router";
import * as Linking from "expo-linking";
import { useEffect } from "react";
import { AppProvider } from "../src/contexts/AppContext";
import { CartProvider } from "../src/contexts/CartContext";
import { STOREFRONT_WEB_URL } from "../src/lib/config";

function DeepLinkHandler({ children }: { children: React.ReactNode }) {
  const router = useRouter();

  useEffect(() => {
    const handle = (url: string | null) => {
      if (!url) {
        return;
      }
      try {
        const parsed = Linking.parse(url);
        const path = (parsed.path || "").replace(/^\/+/, "");
        // gamesspot://product/{slug} or https://shop/.../products/{slug}
        const productMatch =
          path.match(/(?:^|\/)products\/([^/?#]+)/i) ||
          path.match(/^product\/([^/?#]+)/i);
        if (productMatch?.[1]) {
          router.push(`/products/${productMatch[1]}`);
          return;
        }
        const orderMatch =
          path.match(/(?:account\/)?orders\/(\d+)/i) ||
          path.match(/^order\/(\d+)/i);
        if (orderMatch?.[1]) {
          router.push(`/account/orders/${orderMatch[1]}`);
        }
      } catch {
        // ignore bad urls
      }
    };

    void Linking.getInitialURL().then(handle);
    const sub = Linking.addEventListener("url", (event) => handle(event.url));
    return () => sub.remove();
  }, [router]);

  return <>{children}</>;
}

export default function RootLayout() {
  return (
    <AppProvider>
      <CartProvider>
        <DeepLinkHandler>
          <Stack screenOptions={{ headerShown: false }}>
            <Stack.Screen name="(tabs)" />
            <Stack.Screen name="products/[slug]" options={{ headerShown: true, title: "Product" }} />
            <Stack.Screen name="checkout/index" options={{ headerShown: true, title: "Checkout" }} />
            <Stack.Screen name="checkout/payment" options={{ headerShown: true, title: "Payment" }} />
            <Stack.Screen name="login" options={{ headerShown: true, title: "Sign in" }} />
            <Stack.Screen name="register" options={{ headerShown: true, title: "Register" }} />
            <Stack.Screen name="search" options={{ headerShown: true, title: "Search" }} />
            <Stack.Screen name="wishlist" options={{ headerShown: true, title: "Wishlist" }} />
            <Stack.Screen name="category/[slug]" options={{ headerShown: true, title: "Category" }} />
            <Stack.Screen name="brands/index" options={{ headerShown: true, title: "Brands" }} />
            <Stack.Screen name="brands/[slug]" options={{ headerShown: true, title: "Brand" }} />
            <Stack.Screen name="games/index" options={{ headerShown: true, title: "Games" }} />
            <Stack.Screen name="games/[id]" options={{ headerShown: true, title: "Game" }} />
            <Stack.Screen name="gift-cards/index" options={{ headerShown: true, title: "Gift cards" }} />
            <Stack.Screen name="stores" options={{ headerShown: true, title: "Stores" }} />
            <Stack.Screen name="contact" options={{ headerShown: true, title: "Contact" }} />
            <Stack.Screen name="repair-status" options={{ headerShown: true, title: "Repair" }} />
            <Stack.Screen name="about" options={{ headerShown: true, title: "About" }} />
            <Stack.Screen name="faq" options={{ headerShown: true, title: "FAQ" }} />
            <Stack.Screen name="legal/[slug]" options={{ headerShown: true, title: "Legal" }} />
            <Stack.Screen name="account/orders/index" options={{ headerShown: true, title: "Orders" }} />
            <Stack.Screen name="account/orders/[id]" options={{ headerShown: true, title: "Order" }} />
            <Stack.Screen name="maintenance" options={{ headerShown: false }} />
          </Stack>
        </DeepLinkHandler>
      </CartProvider>
    </AppProvider>
  );
}

// Keep web URL referenced so deep-link docs stay discoverable in bundle analysis.
void STOREFRONT_WEB_URL;
