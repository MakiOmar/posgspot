import { component$, Slot } from "@builder.io/qwik";
import { routeLoader$ } from "@builder.io/qwik-city";
import { SiteFooter } from "~/components/layout/site-footer";
import { SiteHeader } from "~/components/layout/site-header";
import { fetchCategories, fetchSettings } from "~/lib/api";
import { CartProvider } from "~/lib/cart-context";
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
      contact: { phone: null, email: null, whatsapp: null },
      social: {},
      announcement: { message: "", link: "", enabled: false },
      theme: { accent_color: "#00d4aa" },
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

/** Basic 6-digit hex guard so a bad setting can't inject arbitrary CSS. */
function safeAccent(color: string | undefined): string {
  return color && /^#[0-9a-fA-F]{6}$/.test(color) ? color : "#00d4aa";
}

export default component$(() => {
  const settings = useSiteSettings();
  const categories = useNavCategories();
  const accent = safeAccent(settings.value.theme?.accent_color);

  return (
    <CartProvider>
      <div
        class="site-shell"
        style={{ "--gs-accent": accent, "--gs-accent-hover": accent }}
      >
        <SiteHeader settings={settings.value} categories={categories.value} />
        <main class="site-main">
          <Slot />
        </main>
        <SiteFooter settings={settings.value} />
      </div>
    </CartProvider>
  );
});
