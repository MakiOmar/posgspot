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
 * Same main nav IA as Qwik `buildMainNavLinks` (paths without locale prefix for Expo Router).
 */
export function buildMainNavLinks(
  locale: ContentLocale,
  options?: {
    digitalEnabled?: boolean;
    categories?: Category[];
    supportChatEnabled?: boolean;
    customBundleEnabled?: boolean;
    sellToUsEnabled?: boolean;
    communityEnabled?: boolean;
    requestProductEnabled?: boolean;
  },
): MainNavItem[] {
  const digitalEnabled = options?.digitalEnabled !== false;
  const supportChatEnabled = Boolean(options?.supportChatEnabled);
  const customBundleEnabled = Boolean(options?.customBundleEnabled);
  const sellToUsEnabled = Boolean(options?.sellToUsEnabled);
  const communityEnabled = Boolean(options?.communityEnabled);
  const requestProductEnabled = Boolean(options?.requestProductEnabled);

  const consoleChildren = consoleNavCategories(options?.categories ?? []).map((category) => ({
    label: category.name,
    href: category.slug
      ? `/category/${category.slug}`
      : `/products?category_id=${category.id}`,
  }));

  const shopChildren: MainNavChild[] = [
    { label: t(locale, "nav.shopAll"), href: "/products" },
    ...consoleChildren,
    { label: t(locale, "nav.brands"), href: "/brands" },
  ];

  if (digitalEnabled) {
    shopChildren.push(
      { label: t(locale, "nav.gamesPs4"), href: "/games?platform=4" },
      { label: t(locale, "nav.gamesPs5"), href: "/games?platform=5" },
      { label: t(locale, "nav.giftCards"), href: "/gift-cards" },
    );
  }
  if (customBundleEnabled) {
    shopChildren.push({
      label: t(locale, "nav.customBundle"),
      href: "/custom-bundle",
    });
  }
  if (sellToUsEnabled) {
    shopChildren.push({
      label: t(locale, "nav.sellToUs"),
      href: "/sell-to-us",
    });
  }
  if (requestProductEnabled) {
    shopChildren.push({
      label: t(locale, "nav.requestProduct"),
      href: "/request-a-product",
    });
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

  items.push(
    { label: t(locale, "common.stores"), href: "/stores" },
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
