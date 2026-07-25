import { TRACK_CONSOLE_URL } from "./config";
import type { ContentLocale } from "./types";
import { t } from "./i18n";

export type MainNavChild = {
  label: string;
  href: string;
};

export type MainNavItem = {
  label: string;
  href?: string;
  external?: boolean;
  children?: MainNavChild[];
};

/**
 * Same main nav as Qwik `buildMainNavLinks` (paths without locale prefix for Expo Router).
 */
export function buildMainNavLinks(
  locale: ContentLocale,
  options?: { digitalEnabled?: boolean },
): MainNavItem[] {
  const digitalEnabled = options?.digitalEnabled !== false;
  const items: MainNavItem[] = [
    { label: t(locale, "nav.home"), href: "/(tabs)" },
    { label: t(locale, "nav.shop"), href: "/products" },
  ];

  if (digitalEnabled) {
    items.push({
      label: t(locale, "nav.games"),
      children: [
        { label: t(locale, "nav.gamesPs4"), href: "/games?platform=4" },
        { label: t(locale, "nav.gamesPs5"), href: "/games?platform=5" },
      ],
    });
    items.push({
      label: t(locale, "nav.giftCards"),
      href: "/gift-cards",
    });
  }

  items.push(
    { label: t(locale, "nav.brands"), href: "/brands" },
    { label: t(locale, "common.stores"), href: "/stores" },
    { label: t(locale, "common.repair"), href: "/repair-status" },
    {
      label: t(locale, "nav.trackConsole"),
      href: TRACK_CONSOLE_URL,
      external: true,
    },
    { label: t(locale, "common.contact"), href: "/contact" },
    { label: t(locale, "common.faq"), href: "/faq" },
    { label: t(locale, "common.about"), href: "/about" },
    { label: t(locale, "legal.terms"), href: "/legal/terms" },
    { label: t(locale, "legal.privacy"), href: "/legal/privacy" },
    { label: t(locale, "legal.return"), href: "/legal/return" },
  );

  return items;
}
