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

/**
 * Same main nav IA as Qwik `buildMainNavLinks` (paths without locale prefix for Expo Router).
 */
export function buildMainNavLinks(
  locale: ContentLocale,
  options?: {
    digitalEnabled?: boolean;
    categories?: Category[];
    customBundleEnabled?: boolean;
    sellToUsEnabled?: boolean;
    communityEnabled?: boolean;
  },
): MainNavItem[] {
  const digitalEnabled = options?.digitalEnabled !== false;
  const customBundleEnabled = Boolean(options?.customBundleEnabled);
  const sellToUsEnabled = Boolean(options?.sellToUsEnabled);
  const communityEnabled = Boolean(options?.communityEnabled);

  const consoleChildren = consoleNavCategories(options?.categories ?? []).map((category) => ({
    label: category.name,
    href: category.slug
      ? `/category/${category.slug}`
      : `/products?category_id=${category.id}`,
  }));

  const shopChildren: MainNavChild[] = [
    { label: t(locale, "nav.shopAll"), href: "/products" },
    ...consoleChildren,
  ];

  if (digitalEnabled) {
    shopChildren.push(
      { label: t(locale, "nav.gamesPs4"), href: "/games?platform=4" },
      { label: t(locale, "nav.gamesPs5"), href: "/games?platform=5" },
      { label: t(locale, "nav.giftCards"), href: "/gift-cards" },
    );
  }

  const items: MainNavItem[] = [
    { label: t(locale, "nav.home"), href: "/(tabs)" },
    {
      label: t(locale, "nav.shop"),
      children: shopChildren,
    },
    {
      label: t(locale, "nav.services"),
      children: [
        { label: t(locale, "nav.trackRepairs"), href: "/repair-status" },
        { label: t(locale, "nav.trackConsole"), href: "/track-console" },
        { label: t(locale, "nav.trackOrder"), href: "/track-order" },
        { label: t(locale, "nav.repairTruck"), href: "/repair-truck-request" },
      ],
    },
  ];

  if (customBundleEnabled) {
    items.push({
      label: t(locale, "nav.customBundle"),
      href: "/custom-bundle",
    });
  }

  items.push({ label: t(locale, "nav.stores"), href: "/stores" });

  if (communityEnabled) {
    items.push({
      label: t(locale, "nav.community"),
      children: [
        { label: t(locale, "nav.tournaments"), href: "/tournaments" },
        { label: t(locale, "nav.events"), href: "/events" },
        { label: t(locale, "nav.gamingNews"), href: "/gaming-news" },
      ],
    });
  }

  if (sellToUsEnabled) {
    items.push({
      label: t(locale, "nav.sellToUs"),
      href: "/sell-to-us",
    });
  }

  items.push(
    { label: t(locale, "legal.terms"), href: "/legal/terms" },
    { label: t(locale, "legal.privacy"), href: "/legal/privacy" },
    { label: t(locale, "legal.return"), href: "/legal/return" },
  );

  return items;
}
