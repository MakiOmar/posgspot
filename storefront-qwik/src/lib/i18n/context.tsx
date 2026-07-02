import {
  component$,
  createContextId,
  Slot,
  useContextProvider,
} from "@builder.io/qwik";
import { useLocation } from "@builder.io/qwik-city";
import type { StoreLocaleCode } from "./config";
import { localeDefinition } from "./config";
import { localeFromPathname } from "./paths";
import { translate } from "./translate";

/** Serializable i18n state stored in Qwik context (no functions). */
export interface I18nContextData {
  locale: StoreLocaleCode;
  dir: "ltr" | "rtl";
  intl: string;
}

export const I18nContext = createContextId<I18nContextData>("gs.i18n");

/**
 * Active UI locale from the URL prefix (reactive on client navigations).
 * Prefer this over route-loader locale so language switches translate without reload.
 */
export function useI18n(): I18nContextData {
  const loc = useLocation();
  const locale = localeFromPathname(loc.url.pathname);
  const def = localeDefinition(locale);
  return {
    locale,
    dir: def.dir,
    intl: def.intl,
  };
}

interface I18nProviderProps {
  locale: StoreLocaleCode;
}

/** Keeps a serializable locale snapshot in context for any future consumers. */
export const I18nProvider = component$<I18nProviderProps>(({ locale }) => {
  const def = localeDefinition(locale);
  useContextProvider(I18nContext, {
    locale,
    dir: def.dir,
    intl: def.intl,
  });

  return <Slot />;
});

/** Helper for route loaders — translate without React context. */
export function tStatic(locale: StoreLocaleCode, key: string, params?: Record<string, string | number>) {
  return translate(locale, key, params);
}
