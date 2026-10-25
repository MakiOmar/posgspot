import { GLOBAL_KEY, OTHER_KEY } from "~/lib/gift-card-groups";
import type { StoreLocaleCode } from "~/lib/i18n/config";
import { translate } from "~/lib/i18n/translate";

/** Country name in the page language (Intl), with translated Global/Other. */
export function countryDisplayLabel(lang: StoreLocaleCode, key: string, fallback: string): string {
  if (key === GLOBAL_KEY) {
    return translate(lang, "digital.globalCountry");
  }
  if (key === OTHER_KEY) {
    return translate(lang, "digital.otherCountry");
  }
  try {
    return new Intl.DisplayNames([lang], { type: "region" }).of(key.toUpperCase()) ?? fallback;
  } catch {
    return fallback;
  }
}

export function brandDisplayLabel(lang: StoreLocaleCode, brand: string, fallback: string): string {
  return brand === OTHER_KEY ? translate(lang, "digital.otherBrand") : fallback;
}
