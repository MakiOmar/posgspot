export const DEFAULT_CONTENT_LOCALE = "en";

export type StoreLocaleCode = "en" | "ar";

export interface StoreLocaleDefinition {
  code: StoreLocaleCode;
  label: string;
  dir: "ltr" | "rtl";
  default?: boolean;
  intl: string;
}

export const STORE_LOCALES: StoreLocaleDefinition[] = [
  { code: "en", label: "EN", dir: "ltr", default: true, intl: "en-EG" },
  { code: "ar", label: "AR", dir: "rtl", intl: "ar-EG" },
];

export function isSupportedLocale(value: string | undefined | null): value is StoreLocaleCode {
  return value === "en" || value === "ar";
}

export function localeDefinition(code: string): StoreLocaleDefinition {
  return STORE_LOCALES.find((l) => l.code === code) ?? STORE_LOCALES[0];
}
