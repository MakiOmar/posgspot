import { GLOBAL_KEY, OTHER_KEY } from "./gift-card-groups";
import { t } from "./i18n";
import type { ContentLocale } from "./types";

/** Country name in the app language (Intl when the JS engine has it), with translated Global/Other. */
export function countryDisplayLabel(locale: ContentLocale, key: string, fallback: string): string {
  if (key === GLOBAL_KEY) return t(locale, "digital.globalCountry");
  if (key === OTHER_KEY) return t(locale, "digital.otherCountry");
  try {
    if (typeof Intl.DisplayNames === "function") {
      return new Intl.DisplayNames([locale], { type: "region" }).of(key.toUpperCase()) ?? fallback;
    }
  } catch {
    // Unknown region codes throw; keep the API label.
  }
  return fallback;
}

export function brandDisplayLabel(locale: ContentLocale, brand: string, fallback: string): string {
  return brand === OTHER_KEY ? t(locale, "digital.otherBrand") : fallback;
}
