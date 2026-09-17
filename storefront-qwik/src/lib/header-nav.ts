import { consoleNavCategories } from "~/lib/console-categories";
import { localePath } from "~/lib/i18n/paths";
import { tStatic } from "~/lib/i18n/context";
import type { StoreLocaleCode } from "~/lib/i18n/config";
import type { Category } from "~/lib/types";

export interface ResolvedNavChild {
  label: string;
  href?: string;
  disabled?: boolean;
  hint?: string;
  /** Client action (e.g. open floating support chat). */
  action?: "open-support-chat";
}

export interface ResolvedNavItem {
  label: string;
  /** Flat link when set; omit for dropdown-only parents. */
  href?: string;
  external?: boolean;
  children?: ResolvedNavChild[];
}

const HOTLINE = "17797";

/**
 * Build header nav items. Digital games is a platform dropdown (not a single page link).
 */
export function buildMainNavLinks(
  lang: StoreLocaleCode,
  options?: {
    digitalEnabled?: boolean;
    categories?: Category[];
    supportChatEnabled?: boolean;
    customBundleEnabled?: boolean;
    sellToUsEnabled?: boolean;
  },
): ResolvedNavItem[] {
  const digitalEnabled = options?.digitalEnabled !== false;
  const supportChatEnabled = Boolean(options?.supportChatEnabled);
  const customBundleEnabled = Boolean(options?.customBundleEnabled);
  const consoleChildren = consoleNavCategories(options?.categories ?? []).map((category) => ({
    label: category.name,
    href: category.slug
      ? localePath(lang, `/category/${category.slug}`)
      : localePath(lang, `/products?category_id=${category.id}`),
  }));

  const consoleItem: ResolvedNavItem = {
    label: tStatic(lang, "nav.consoles"),
    children: [
      { label: tStatic(lang, "nav.shopAll"), href: localePath(lang, "/products") },
      ...consoleChildren,
    ],
  };
  if (customBundleEnabled) {
    consoleItem.children = [
      ...(consoleItem.children ?? []),
      {
        label: tStatic(lang, "nav.customBundle"),
        href: localePath(lang, "/custom-bundle"),
      },
    ];
  }

  const items: ResolvedNavItem[] = [
    { label: tStatic(lang, "nav.home"), href: localePath(lang, "/") },
    consoleItem,
  ];

  if (options?.sellToUsEnabled) {
    items.push({
      label: tStatic(lang, "nav.sellToUs"),
      href: localePath(lang, "/sell-to-us"),
    });
  }

  if (digitalEnabled) {
    items.push({
      label: tStatic(lang, "nav.games"),
      children: [
        {
          label: tStatic(lang, "nav.gamesPs4"),
          href: localePath(lang, "/games?platform=4"),
        },
        {
          label: tStatic(lang, "nav.gamesPs5"),
          href: localePath(lang, "/games?platform=5"),
        },
      ],
    });
    items.push({
      label: tStatic(lang, "nav.giftCards"),
      href: localePath(lang, "/gift-cards"),
    });
  }

  items.push(
    { label: tStatic(lang, "nav.stores"), href: localePath(lang, "/stores") },
    { label: tStatic(lang, "nav.trackRepairs"), href: localePath(lang, "/repair-status") },
    { label: tStatic(lang, "nav.trackConsole"), href: localePath(lang, "/track-console") },
    {
      label: tStatic(lang, "nav.contact"),
      children: [
        { label: tStatic(lang, "nav.callUs"), href: `tel:${HOTLINE}` },
        supportChatEnabled
          ? {
              label: tStatic(lang, "nav.liveChat"),
              action: "open-support-chat" as const,
            }
          : {
              label: tStatic(lang, "nav.liveChat"),
              disabled: true,
              hint: tStatic(lang, "nav.comingSoon"),
            },
        {
          label: tStatic(lang, "nav.leaveMessage"),
          href: localePath(lang, "/contact"),
        },
      ],
    },
    { label: tStatic(lang, "nav.faq"), href: localePath(lang, "/faq") },
    { label: tStatic(lang, "nav.about"), href: localePath(lang, "/about") },
  );

  return items;
}
