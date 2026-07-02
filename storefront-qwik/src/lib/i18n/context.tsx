import {
  component$,
  createContextId,
  Slot,
  useContext,
  useContextProvider,
} from "@builder.io/qwik";
import type { StoreLocaleCode } from "./config";
import { localeDefinition } from "./config";
import { translate } from "./translate";

/** Serializable i18n state stored in Qwik context (no functions). */
export interface I18nContextData {
  locale: StoreLocaleCode;
  dir: "ltr" | "rtl";
  intl: string;
}

export const I18nContext = createContextId<I18nContextData>("gs.i18n");

/** Serializable i18n context only — use {@link tStatic} for translated strings in components. */
export function useI18n(): I18nContextData {
  return useContext(I18nContext);
}

interface I18nProviderProps {
  locale: StoreLocaleCode;
}

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
