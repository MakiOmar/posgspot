import type { StoreLocaleCode } from "~/lib/i18n/config";

/** Where Arabic UI font files are loaded from. Switch to `self-hosted` when files live under `public/fonts/`. */
export type ArabicFontProvider = "google" | "self-hosted";

const providerFromEnv = (): ArabicFontProvider => {
  const raw = import.meta.env.PUBLIC_ARABIC_FONT_PROVIDER?.trim().toLowerCase();
  return raw === "self-hosted" ? "self-hosted" : "google";
};

export const ARABIC_FONT = {
  family: "Cairo",
  provider: providerFromEnv(),
  /** Self-hosted bundle: drop `cairo.css` + woff2 files under `public/fonts/` */
  selfHostedCssHref: "/fonts/cairo.css",
  google: {
    weights: [400, 600, 700],
    display: "swap" as const,
  },
} as const;

export const LATIN_FONT_STACK = '"Segoe UI", system-ui, -apple-system, sans-serif';

export function arabicFontStack(): string {
  return `"${ARABIC_FONT.family}", ${LATIN_FONT_STACK}`;
}

export function shouldLoadArabicFont(locale: StoreLocaleCode): boolean {
  return locale === "ar";
}

export function googleFontsCssHref(): string {
  const weights = ARABIC_FONT.google.weights.join(";");
  const family = ARABIC_FONT.family.replace(/ /g, "+");
  return `https://fonts.googleapis.com/css2?family=${family}:wght@${weights}&display=${ARABIC_FONT.google.display}`;
}
