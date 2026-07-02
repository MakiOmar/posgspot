import { component$, Slot } from "@builder.io/qwik";
import { routeLoader$, useLocation, type DocumentHead } from "@builder.io/qwik-city";
import { SiteFooter } from "~/components/layout/site-footer";
import { SiteHeader } from "~/components/layout/site-header";
import { GlobalPendingIndicator } from "~/components/ui/global-pending-indicator";
import {
  EMPTY_NAV_CATEGORIES,
  FALLBACK_STORE_SETTINGS,
  type NavCategoriesLoad,
} from "~/lib/default-site";
import { fetchCategories, fetchSettings, setActiveContentLocale } from "~/lib/api";
import { AuthProvider } from "~/lib/auth-context";
import { CartProvider } from "~/lib/cart-context";
import { I18nProvider } from "~/lib/i18n/context";
import { isSupportedLocale, localeDefinition, type StoreLocaleCode } from "~/lib/i18n/config";
import { localeFromPathname, stripLocalePrefix } from "~/lib/i18n/paths";
import { PendingProvider } from "~/lib/pending-context";
import { SiteShellProvider, useSiteShell } from "~/lib/site-shell-context";
import { themeHeadStyleFromSettings } from "~/lib/theme";
import type { StoreSettings } from "~/lib/types";

export const useLangParam = routeLoader$(({ params, redirect }): StoreLocaleCode => {
  if (!isSupportedLocale(params.lang)) {
    throw redirect(302, "/en/");
  }
  setActiveContentLocale(params.lang);
  return params.lang;
});

export const useSiteSettings = routeLoader$(async ({ params }): Promise<StoreSettings> => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  setActiveContentLocale(locale);
  try {
    const { data } = await fetchSettings(locale);
    return data;
  } catch (err) {
    console.error("[storefront] settings loader failed", err);
    return FALLBACK_STORE_SETTINGS;
  }
});

export const useNavCategories = routeLoader$(async ({ params }): Promise<NavCategoriesLoad> => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  try {
    const { data } = await fetchCategories(locale);
    return { ok: true, items: Array.isArray(data) ? data : [] };
  } catch (err) {
    console.error("[storefront] categories loader failed", err);
    return EMPTY_NAV_CATEGORIES;
  }
});

export const head: DocumentHead = ({ resolveValue, params }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = isSupportedLocale(params.lang) ? params.lang : "en";
  const def = localeDefinition(lang);

  return {
    html: {
      lang,
      dir: def.dir,
    },
    styles: [themeHeadStyleFromSettings(settings)],
  };
};

const SiteShellHeader = component$(() => {
  const shell = useSiteShell();
  return <SiteHeader settings={shell.settings} categories={shell.categories} />;
});

const SiteShellFooter = component$(() => {
  const shell = useSiteShell();
  return <SiteFooter settings={shell.settings} />;
});

export default component$(() => {
  useLangParam();
  const settings = useSiteSettings();
  const categories = useNavCategories();
  const loc = useLocation();
  const activeLocale = localeFromPathname(loc.url.pathname);
  const bare = stripLocalePrefix(loc.url.pathname);
  const isLandingPage = bare === "/add-customer";

  const shell = (
    <I18nProvider locale={activeLocale} key={activeLocale}>
      <SiteShellProvider
        key={activeLocale}
        settings={settings.value}
        categories={categories.value}
      >
        <AuthProvider>
          <CartProvider>
            {isLandingPage ? (
              <Slot />
            ) : (
              <div class="site-shell">
                <SiteShellHeader />
                <main class="site-main">
                  <Slot />
                </main>
                <SiteShellFooter />
              </div>
            )}
          </CartProvider>
        </AuthProvider>
      </SiteShellProvider>
    </I18nProvider>
  );

  return (
    <PendingProvider>
      <GlobalPendingIndicator />
      {shell}
    </PendingProvider>
  );
});
