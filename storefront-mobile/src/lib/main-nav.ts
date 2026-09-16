import { consoleNavCategories } from "./console-categories";
import type { Category, ContentLocale } from "./types";
import { t } from "./i18n";

export type MainNavChild = {
  label: string;
  href?: string;
  disabled?: boolean;
  hint?: string;
};

export type MainNavItem = {
  label: string;
  href?: string;
  external?: boolean;
  children?: MainNavChild[];
};

const HOTLINE = "17797";

/**
 * Same main nav as Qwik `buildMainNavLinks` (paths without locale prefix for Expo Router).
 */
export function buildMainNavLinks(
  locale: ContentLocale,
  options?: { digitalEnabled?: boolean; categories?: Category[]; supportChatEnabled?: boolean },
): MainNavItem[] {
  const digitalEnabled = options?.digitalEnabled !== false;
  const supportChatEnabled = Boolean(options?.supportChatEnabled);
  const consoleChildren = consoleNavCategories(options?.categories ?? []).map((category) => ({
    label: category.name,
    href: `/category/${category.slug}`,
  }));
  const items: MainNavItem[] = [
    { label: t(locale, "nav.home"), href: "/(tabs)" },
    {
      label: t(locale, "nav.consoles"),
      children: [
        { label: t(locale, "nav.shopAll"), href: "/products" },
        ...consoleChildren,
      ],
    },
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
    { label: t(locale, "common.stores"), href: "/stores" },
    { label: t(locale, "common.repair"), href: "/repair-status" },
    { label: t(locale, "nav.trackConsole"), href: "/track-console" },
    {
      label: t(locale, "common.contact"),
      children: [
        { label: t(locale, "contact.callUs"), href: `tel:${HOTLINE}` },
        supportChatEnabled
          ? { label: t(locale, "contact.liveChat"), href: "/support" }
          : {
              label: t(locale, "contact.liveChat"),
              disabled: true,
              hint: t(locale, "contact.comingSoon"),
            },
        { label: t(locale, "contact.leaveMessage"), href: "/contact" },
      ],
    },
    { label: t(locale, "common.faq"), href: "/faq" },
    { label: t(locale, "common.about"), href: "/about" },
    { label: t(locale, "legal.terms"), href: "/legal/terms" },
    { label: t(locale, "legal.privacy"), href: "/legal/privacy" },
    { label: t(locale, "legal.return"), href: "/legal/return" },
  );

  return items;
}
