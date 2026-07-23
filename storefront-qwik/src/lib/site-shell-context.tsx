import {
  component$,
  createContextId,
  Slot,
  useContext,
  useContextProvider,
  useStore,
  useTask$,
  useVisibleTask$,
} from "@builder.io/qwik";
import { useLocation } from "@builder.io/qwik-city";
import { fetchCategories, fetchLocations, fetchSettings } from "~/lib/api";
import {
  FALLBACK_STORE_SETTINGS,
  isFallbackStoreSettings,
  type NavCategoriesLoad,
} from "~/lib/default-site";
import { syncStorefrontTheme } from "~/lib/theme";
import type { Category, StoreLocation, StoreSettings } from "~/lib/types";

export interface SiteShellState {
  settings: StoreSettings;
  categories: Category[];
  locations: StoreLocation[];
  hasApiSettings: boolean;
  hasApiCategories: boolean;
  hasApiLocations: boolean;
}

export const SiteShellContext = createContextId<SiteShellState>("storefront.site-shell");

export function useSiteShell(): SiteShellState {
  return useContext(SiteShellContext);
}

interface SiteShellProviderProps {
  settings: StoreSettings;
  categories: NavCategoriesLoad;
  locations: StoreLocation[];
}

function syncShellTheme(next: StoreSettings): void {
  syncStorefrontTheme(next);
}

function applyShellSettings(shell: SiteShellState, next: StoreSettings): void {
  shell.settings = next;
  shell.hasApiSettings = true;
  syncShellTheme(next);
}

function applyShellCategories(shell: SiteShellState, items: Category[]): void {
  shell.categories = items;
  shell.hasApiCategories = true;
}

function applyShellLocations(shell: SiteShellState, items: StoreLocation[]): void {
  shell.locations = items;
  shell.hasApiLocations = true;
}

/**
 * Keeps header/footer settings and nav categories stable across client navigations.
 * Layout loaders can intermittently return fallbacks on preview SPA transitions;
 * this context retains the last good API payload and can recover client-side.
 */
export const SiteShellProvider = component$<SiteShellProviderProps>(
  ({ settings, categories, locations }) => {
    const loc = useLocation();
    const initialSettings = isFallbackStoreSettings(settings)
      ? FALLBACK_STORE_SETTINGS
      : settings;

    const shell = useStore<SiteShellState>({
      settings: initialSettings,
      categories: categories.ok ? categories.items : [],
      locations: Array.isArray(locations) ? locations : [],
      hasApiSettings: !isFallbackStoreSettings(settings),
      hasApiCategories: categories.ok,
      hasApiLocations: Array.isArray(locations),
    });

    useContextProvider(SiteShellContext, shell);

    useTask$(({ track }) => {
      track(() => settings);
      if (!isFallbackStoreSettings(settings)) {
        applyShellSettings(shell, settings);
        return;
      }
      if (shell.hasApiSettings) {
        syncShellTheme(shell.settings);
      }
    });

    useTask$(({ track }) => {
      track(() => categories);
      if (categories.ok) {
        applyShellCategories(shell, categories.items);
      }
    });

    useTask$(({ track }) => {
      track(() => locations);
      if (Array.isArray(locations)) {
        applyShellLocations(shell, locations);
      }
    });

    // Client recovery when a SPA navigation returns loader fallbacks.
    // eslint-disable-next-line qwik/no-use-visible-task
    useVisibleTask$(({ track }) => {
      track(() => loc.url.pathname);
      track(() => settings);
      track(() => categories);
      track(() => locations);
      track(() => shell.settings.theme?.accent_color);

      // Re-apply on every client navigation — route head patches can drop theme tokens.
      syncShellTheme(shell.settings);

      const settingsMissing = isFallbackStoreSettings(settings);
      const categoriesMissing = !categories.ok;
      const locationsMissing = !Array.isArray(locations) || locations.length === 0;

      if (!settingsMissing && !categoriesMissing && !locationsMissing) {
        return;
      }

      void (async () => {
        if (settingsMissing && !shell.hasApiSettings) {
          try {
            const { data } = await fetchSettings();
            applyShellSettings(shell, data);
          } catch {
            // Keep fallback until a later navigation succeeds.
          }
        }

        if (categoriesMissing && !shell.hasApiCategories) {
          try {
            const { data } = await fetchCategories();
            applyShellCategories(shell, Array.isArray(data) ? data : []);
          } catch {
            // Keep empty until a later navigation succeeds.
          }
        }

        if (locationsMissing && !shell.hasApiLocations) {
          try {
            const { data } = await fetchLocations();
            applyShellLocations(shell, Array.isArray(data) ? data : []);
          } catch {
            // Keep empty until a later navigation succeeds.
          }
        }
      })();
    });

    return <Slot />;
  },
);
