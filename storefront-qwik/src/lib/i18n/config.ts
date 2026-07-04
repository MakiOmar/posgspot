export const DEFAULT_CONTENT_LOCALE = "en";

export type StoreLocaleCode = "en" | "ar";

export interface StoreLocaleDefinition {
  code: StoreLocaleCode;
  /** Short code shown in the switcher trigger (EN, AR). */
  label: string;
  /** Full language name for the dropdown option. */
  name: string;
  /** Flag emoji for the switcher. */
  flag: string;
  dir: "ltr" | "rtl";
  default?: boolean;
  intl: string;
}

export const STORE_LOCALES: StoreLocaleDefinition[] = [
  { code: "en", label: "EN", name: "English", flag: "🇬🇧", dir: "ltr", default: true, intl: "en-EG" },
  { code: "ar", label: "AR", name: "العربية", flag: "🇪🇬", dir: "rtl", intl: "ar-EG" },
];

export function isSupportedLocale(value: string | undefined | null): value is StoreLocaleCode {
  return value === "en" || value === "ar";
}

export function localeDefinition(code: string): StoreLocaleDefinition {
  return STORE_LOCALES.find((l) => l.code === code) ?? STORE_LOCALES[0];
}
