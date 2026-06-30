import { component$, Slot } from "@builder.io/qwik";
import { routeLoader$ } from "@builder.io/qwik-city";
import { SiteFooter } from "~/components/layout/site-footer";
import { SiteHeader } from "~/components/layout/site-header";
import { fetchSettings } from "~/lib/api";
import { CartProvider } from "~/lib/cart-context";
import type { StoreSettings } from "~/lib/types";

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
      cod_enabled: true,
      maintenance_mode: false,
      locales: ["en"],
    };
  }
});

export default component$(() => {
  const settings = useSiteSettings();

  return (
    <CartProvider>
      <div class="site-shell">
        <SiteHeader settings={settings.value} />
        <main class="site-main">
          <Slot />
        </main>
        <SiteFooter settings={settings.value} />
      </div>
    </CartProvider>
  );
});
