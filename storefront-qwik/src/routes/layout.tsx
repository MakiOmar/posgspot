import { component$, Slot } from "@builder.io/qwik";
import { routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import { SiteFooter } from "~/components/layout/site-footer";
import { SiteHeader } from "~/components/layout/site-header";
import { GlobalPendingIndicator } from "~/components/ui/global-pending-indicator";
import {
  EMPTY_NAV_CATEGORIES,
  FALLBACK_STORE_SETTINGS,
  type NavCategoriesLoad,
} from "~/lib/default-site";
import { fetchCategories, fetchSettings } from "~/lib/api";
import { AuthProvider } from "~/lib/auth-context";
import { CartProvider } from "~/lib/cart-context";
import { PendingProvider } from "~/lib/pending-context";
import { SiteShellProvider, useSiteShell } from "~/lib/site-shell-context";
import { themeHeadStyleFromSettings } from "~/lib/theme";
import type { StoreSettings } from "~/lib/types";

export const useSiteSettings = routeLoader$(async (): Promise<StoreSettings> => {
  try {
    const { data } = await fetchSettings();
    return data;
  } catch (err) {
    console.error("[storefront] settings loader failed", err);
    return FALLBACK_STORE_SETTINGS;
  }
});

/** Top-level categories that drive the header nav. */
export const useNavCategories = routeLoader$(async (): Promise<NavCategoriesLoad> => {
  try {
    const { data } = await fetchCategories();
    return { ok: true, items: Array.isArray(data) ? data : [] };
  } catch (err) {
    console.error("[storefront] categories loader failed", err);
    return EMPTY_NAV_CATEGORIES;
  }
});

export const head: DocumentHead = ({ resolveValue }) => {
  const settings = resolveValue(useSiteSettings);

  return {
    styles: [themeHeadStyleFromSettings(settings)],
  };
};

/** Header reads sticky shell state; kept separate so layout can own the route Slot. */
const SiteShellHeader = component$(() => {
  const shell = useSiteShell();
  return <SiteHeader settings={shell.settings} categories={shell.categories} />;
});

/** Footer reads sticky shell state. */
const SiteShellFooter = component$(() => {
  const shell = useSiteShell();
  return <SiteFooter settings={shell.settings} />;
});

export default component$(() => {
  const settings = useSiteSettings();
  const categories = useNavCategories();

  return (
    <PendingProvider>
      <GlobalPendingIndicator />
      <SiteShellProvider settings={settings.value} categories={categories.value}>
        <AuthProvider>
          <CartProvider>
            <div class="site-shell">
              <SiteShellHeader />
              <main class="site-main">
                {/* Qwik City route outlet must live in the layout default export tree. */}
                <Slot />
              </main>
              <SiteShellFooter />
            </div>
          </CartProvider>
        </AuthProvider>
      </SiteShellProvider>
    </PendingProvider>
  );
});
