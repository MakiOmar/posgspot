import {
  ARABIC_FONT,
  googleFontsCssHref,
  shouldLoadArabicFont,
} from "~/lib/fonts/config";
import type { StoreLocaleCode } from "~/lib/i18n/config";

/** Whether RouterHead should emit Cairo font resources for this locale. */
export function needsArabicFont(locale: StoreLocaleCode): boolean {
  return shouldLoadArabicFont(locale);
}

export function arabicFontStylesheetHref(): string {
  return ARABIC_FONT.provider === "self-hosted"
    ? ARABIC_FONT.selfHostedCssHref
    : googleFontsCssHref();
}

export function shouldPreconnectGoogleFonts(): boolean {
  return ARABIC_FONT.provider === "google";
}
