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
import { fetchCategories, fetchSettings } from "~/lib/api";
import {
  FALLBACK_STORE_SETTINGS,
  isFallbackStoreSettings,
  type NavCategoriesLoad,
} from "~/lib/default-site";
import { syncStorefrontTheme } from "~/lib/theme";
import type { Category, StoreSettings } from "~/lib/types";

export interface SiteShellState {
  settings: StoreSettings;
  categories: Category[];
  hasApiSettings: boolean;
  hasApiCategories: boolean;
}

export const SiteShellContext = createContextId<SiteShellState>("storefront.site-shell");

export function useSiteShell(): SiteShellState {
  return useContext(SiteShellContext);
}

interface SiteShellProviderProps {
  settings: StoreSettings;
  categories: NavCategoriesLoad;
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

/**
 * Keeps header/footer settings and nav categories stable across client navigations.
 * Layout loaders can intermittently return fallbacks on preview SPA transitions;
 * this context retains the last good API payload and can recover client-side.
 */
export const SiteShellProvider = component$<SiteShellProviderProps>(
  ({ settings, categories }) => {
    const loc = useLocation();
    const initialSettings = isFallbackStoreSettings(settings)
      ? FALLBACK_STORE_SETTINGS
      : settings;

    const shell = useStore<SiteShellState>({
      settings: initialSettings,
      categories: categories.ok ? categories.items : [],
      hasApiSettings: !isFallbackStoreSettings(settings),
      hasApiCategories: categories.ok,
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

    // Client recovery when a SPA navigation returns loader fallbacks.
    // eslint-disable-next-line qwik/no-use-visible-task
    useVisibleTask$(({ track }) => {
      track(() => loc.url.pathname);
      track(() => settings);
      track(() => categories);
      track(() => shell.settings.theme?.accent_color);

      // Re-apply on every client navigation — route head patches can drop theme tokens.
      syncShellTheme(shell.settings);

      const settingsMissing = isFallbackStoreSettings(settings);
      const categoriesMissing = !categories.ok;

      if (!settingsMissing && !categoriesMissing) {
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
      })();
    });

    return <Slot />;
  },
);
