import { component$, Slot } from "@builder.io/qwik";
import { routeLoader$, useLocation, type DocumentHead } from "@builder.io/qwik-city";
import { SiteFooter } from "~/components/layout/site-footer";
import { SiteHeader } from "~/components/layout/site-header";
import { CookieConsentBanner } from "~/components/layout/cookie-consent-banner";
import { GlobalPendingIndicator } from "~/components/ui/global-pending-indicator";
import {
  EMPTY_NAV_CATEGORIES,
  FALLBACK_STORE_SETTINGS,
  type NavCategoriesLoad,
} from "~/lib/default-site";
import { API_BASE, fetchCategories, fetchLocations, fetchSettings, setActiveContentLocale } from "~/lib/api";
import { AuthProvider } from "~/lib/auth-context";
import { CartProvider } from "~/lib/cart-context";
import { WishlistProvider } from "~/lib/wishlist-context";
import { I18nProvider } from "~/lib/i18n/context";
import { isSupportedLocale, localeDefinition, type StoreLocaleCode } from "~/lib/i18n/config";
import { localeFromPathname, localePath, stripLocalePrefix } from "~/lib/i18n/paths";
import {
  isMaintenanceExemptPath,
  isMaintenancePagePath,
} from "~/lib/maintenance-gate";
import { PendingProvider } from "~/lib/pending-context";
import { HeaderDropdownProvider } from "~/lib/header-dropdown-context";
import { SiteShellProvider, useSiteShell } from "~/lib/site-shell-context";
import { cachedCategories, cachedLocations, cachedSettings } from "~/lib/ssr-shell-cache";
import { themeHeadStyleFromSettings } from "~/lib/theme";
import type { StoreLocation, StoreSettings } from "~/lib/types";

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
    return await cachedSettings(locale, async () => {
      const { data } = await fetchSettings(locale);
      return data;
    });
  } catch (err) {
    console.error(`[storefront] settings loader failed (API_BASE=${API_BASE})`, err);
    return FALLBACK_STORE_SETTINGS;
  }
});

/** Redirect to maintenance page (503) or home when maintenance toggles off. */
export const useMaintenanceGate = routeLoader$(
  async ({ resolveValue, pathname, redirect, params, status }) => {
    const settings = await resolveValue(useSiteSettings);
    const lang = isSupportedLocale(params.lang) ? params.lang : "en";
    const onMaintenancePage = isMaintenancePagePath(pathname);
    const exempt = isMaintenanceExemptPath(pathname);

    if (settings.maintenance_mode) {
      if (exempt) {
        return null;
      }
      if (!onMaintenancePage) {
        throw redirect(302, localePath(lang, "/maintenance"));
      }
      status(503);
      return { active: true as const };
    }

    if (onMaintenancePage) {
      throw redirect(302, localePath(lang, "/"));
    }

    return null;
  },
);

export const useNavCategories = routeLoader$(async ({ params }): Promise<NavCategoriesLoad> => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  try {
    return await cachedCategories(locale, async () => {
      const { data } = await fetchCategories(locale);
      return { ok: true, items: Array.isArray(data) ? data : [] };
    });
  } catch (err) {
    console.error(`[storefront] categories loader failed (API_BASE=${API_BASE})`, err);
    return EMPTY_NAV_CATEGORIES;
  }
});

export const useFooterLocations = routeLoader$(async ({ params }): Promise<StoreLocation[]> => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  try {
    return await cachedLocations(locale, async () => {
      const { data } = await fetchLocations(locale);
      return Array.isArray(data) ? data : [];
    });
  } catch (err) {
    console.error(`[storefront] locations loader failed (API_BASE=${API_BASE})`, err);
    return [];
  }
});

export const head: DocumentHead = ({ resolveValue, params }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = isSupportedLocale(params.lang) ? params.lang : "en";
  const def = localeDefinition(lang);
  const faviconUrl = settings.favicon_url?.trim() || "";

  return {
    html: {
      lang,
      dir: def.dir,
    },
    styles: [themeHeadStyleFromSettings(settings)],
    links: faviconUrl
      ? [
          {
            key: "favicon",
            rel: "icon",
            href: faviconUrl,
          },
        ]
      : [],
  };
};

const SiteShellHeader = component$(() => {
  const shell = useSiteShell();
  return <SiteHeader settings={shell.settings} categories={shell.categories} />;
});

const SiteShellFooter = component$(() => {
  const shell = useSiteShell();
  return <SiteFooter settings={shell.settings} locations={shell.locations} />;
});

export default component$(() => {
  useLangParam();
  useMaintenanceGate();
  const settings = useSiteSettings();
  const categories = useNavCategories();
  const locations = useFooterLocations();
  const loc = useLocation();
  const activeLocale = localeFromPathname(loc.url.pathname);
  const activeDir = localeDefinition(activeLocale).dir;
  const bare = stripLocalePrefix(loc.url.pathname);
  const isBarePage = bare === "/add-customer" || isMaintenancePagePath(loc.url.pathname);

  const shell = (
    <I18nProvider locale={activeLocale} key={activeLocale}>
      <SiteShellProvider
        key={activeLocale}
        settings={settings.value}
        categories={categories.value}
        locations={locations.value}
      >
        <AuthProvider>
          <WishlistProvider>
            <CartProvider>
            {isBarePage ? (
              <Slot />
            ) : (
              <div class="site-shell" dir={activeDir} lang={activeLocale}>
                <SiteShellHeader />
                <main class="site-main">
                  <Slot />
                </main>
                <SiteShellFooter />
                <CookieConsentBanner />
              </div>
            )}
            </CartProvider>
          </WishlistProvider>
        </AuthProvider>
      </SiteShellProvider>
    </I18nProvider>
  );

  return (
    <PendingProvider>
      <HeaderDropdownProvider>
        <GlobalPendingIndicator />
        {shell}
      </HeaderDropdownProvider>
    </PendingProvider>
  );
});
