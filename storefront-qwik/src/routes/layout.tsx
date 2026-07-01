import { component$, Slot, useVisibleTask$ } from "@builder.io/qwik";
import { routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import { SiteFooter } from "~/components/layout/site-footer";
import { SiteHeader } from "~/components/layout/site-header";
import { GlobalPendingIndicator } from "~/components/ui/global-pending-indicator";
import { PendingProvider } from "~/lib/pending-context";
import { fetchCategories, fetchSettings } from "~/lib/api";
import { AuthProvider } from "~/lib/auth-context";
import { CartProvider } from "~/lib/cart-context";
import { applyThemeAccent, safeAccent, themeAccentCss } from "~/lib/theme";
import type { Category, StoreSettings } from "~/lib/types";

export const useSiteSettings = routeLoader$(async (): Promise<StoreSettings> => {
  try {
    const { data } = await fetchSettings();
    return data;
  } catch {
    return {
      business_name: "Games Spot",
      logo_url: null,
      currency: { code: "EGP", symbol: "£", precision: 2, symbol_placement: "before" },
      contact: { phone: null, email_encoded: null, whatsapp: null },
      social: {},
      announcement: { message: "", link: "", enabled: false },
      theme: { accent_color: "#00d4aa" },
      sale_badge: { mode: "percent", text: "Sale" },
      cod_enabled: true,
      maintenance_mode: false,
      locales: ["en"],
    };
  }
});

/** Top-level categories that drive the header nav (empty on failure). */
export const useNavCategories = routeLoader$(async (): Promise<Category[]> => {
  try {
    const { data } = await fetchCategories();
    return Array.isArray(data) ? data : [];
  } catch {
    return [];
  }
});

export const head: DocumentHead = ({ resolveValue }) => {
  const settings = resolveValue(useSiteSettings);
  const accent = safeAccent(settings.theme?.accent_color);

  return {
    styles: [
      {
        key: "storefront-theme-vars",
        props: { id: "storefront-theme-vars" },
        style: themeAccentCss(accent),
      },
    ],
  };
};

export default component$(() => {
  const settings = useSiteSettings();
  const categories = useNavCategories();

  // Keep :root accent in sync on client navigations (inline layout styles are not enough).
  useVisibleTask$(({ track }) => {
    track(() => settings.value.theme?.accent_color);
    applyThemeAccent(safeAccent(settings.value.theme?.accent_color));
  });

  return (
    <PendingProvider>
      <GlobalPendingIndicator />
      <AuthProvider>
        <CartProvider>
          <div class="site-shell">
            <SiteHeader settings={settings.value} categories={categories.value} />
            <main class="site-main">
              <Slot />
            </main>
            <SiteFooter settings={settings.value} />
          </div>
        </CartProvider>
      </AuthProvider>
    </PendingProvider>
  );
});
