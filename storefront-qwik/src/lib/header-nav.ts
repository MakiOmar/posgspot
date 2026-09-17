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

export interface ResolvedNavMegaColumn {
  title?: string;
  links: ResolvedNavChild[];
}

export interface ResolvedNavItem {
  label: string;
  /** Flat link when set; omit for dropdown-only parents. */
  href?: string;
  external?: boolean;
  /** Simple dropdown (Contact, Community, Services without mega). */
  children?: ResolvedNavChild[];
  /** Multi-column mega panel (Shop). When set, preferred over flat children. */
  mega?: {
    columns: ResolvedNavMegaColumn[];
  };
}

const HOTLINE = "17797";

/**
 * Build header nav: Home, Shop mega, Services, Community (flag), Stores, Contact, FAQ, About.
 */
export function buildMainNavLinks(
  lang: StoreLocaleCode,
  options?: {
    digitalEnabled?: boolean;
    categories?: Category[];
    supportChatEnabled?: boolean;
    customBundleEnabled?: boolean;
    sellToUsEnabled?: boolean;
    communityEnabled?: boolean;
    requestProductEnabled?: boolean;
  },
): ResolvedNavItem[] {
  const digitalEnabled = options?.digitalEnabled !== false;
  const supportChatEnabled = Boolean(options?.supportChatEnabled);
  const customBundleEnabled = Boolean(options?.customBundleEnabled);
  const sellToUsEnabled = Boolean(options?.sellToUsEnabled);
  const communityEnabled = Boolean(options?.communityEnabled);
  const requestProductEnabled = Boolean(options?.requestProductEnabled);

  const consoleChildren = consoleNavCategories(options?.categories ?? []).map((category) => ({
    label: category.name,
    href: category.slug
      ? localePath(lang, `/category/${category.slug}`)
      : localePath(lang, `/products?category_id=${category.id}`),
  }));

  const shopColumn: ResolvedNavMegaColumn = {
    title: tStatic(lang, "nav.shopColumnPhysical"),
    links: [
      { label: tStatic(lang, "nav.shopAll"), href: localePath(lang, "/products") },
      ...consoleChildren,
      { label: tStatic(lang, "nav.brands"), href: localePath(lang, "/brands") },
    ],
  };

  const digitalColumn: ResolvedNavMegaColumn | null = digitalEnabled
    ? {
        title: tStatic(lang, "nav.shopColumnDigital"),
        links: [
          {
            label: tStatic(lang, "nav.gamesPs4"),
            href: localePath(lang, "/games?platform=4"),
          },
          {
            label: tStatic(lang, "nav.gamesPs5"),
            href: localePath(lang, "/games?platform=5"),
          },
          {
            label: tStatic(lang, "nav.giftCards"),
            href: localePath(lang, "/gift-cards"),
          },
        ],
      }
    : null;

  const moreLinks: ResolvedNavChild[] = [];
  if (customBundleEnabled) {
    moreLinks.push({
      label: tStatic(lang, "nav.customBundle"),
      href: localePath(lang, "/custom-bundle"),
    });
  }
  if (sellToUsEnabled) {
    moreLinks.push({
      label: tStatic(lang, "nav.sellToUs"),
      href: localePath(lang, "/sell-to-us"),
    });
  }
  if (requestProductEnabled) {
    moreLinks.push({
      label: tStatic(lang, "nav.requestProduct"),
      href: localePath(lang, "/request-a-product"),
    });
  }

  const shopColumns: ResolvedNavMegaColumn[] = [shopColumn];
  if (digitalColumn) {
    shopColumns.push(digitalColumn);
  }
  if (moreLinks.length > 0) {
    shopColumns.push({
      title: tStatic(lang, "nav.shopColumnMore"),
      links: moreLinks,
    });
  }

  // Flat children for mobile drawer (same destinations as mega).
  const shopFlatChildren: ResolvedNavChild[] = shopColumns.flatMap((col) => col.links);

  const items: ResolvedNavItem[] = [
    { label: tStatic(lang, "nav.home"), href: localePath(lang, "/") },
    {
      label: tStatic(lang, "nav.shop"),
      mega: { columns: shopColumns },
      children: shopFlatChildren,
    },
    {
      label: tStatic(lang, "nav.services"),
      children: [
        {
          label: tStatic(lang, "nav.trackRepairs"),
          href: localePath(lang, "/repair-status"),
        },
        {
          label: tStatic(lang, "nav.trackConsole"),
          href: localePath(lang, "/track-console"),
        },
        {
          label: tStatic(lang, "nav.trackOrder"),
          href: localePath(lang, "/track-order"),
        },
        {
          label: tStatic(lang, "nav.repairTruck"),
          href: localePath(lang, "/repair-truck-request"),
        },
      ],
    },
  ];

  if (communityEnabled) {
    items.push({
      label: tStatic(lang, "nav.community"),
      children: [
        {
          label: tStatic(lang, "nav.tournaments"),
          href: localePath(lang, "/tournaments"),
        },
        {
          label: tStatic(lang, "nav.events"),
          href: localePath(lang, "/events"),
        },
        {
          label: tStatic(lang, "nav.gamingNews"),
          href: localePath(lang, "/gaming-news"),
        },
      ],
    });
  }

  items.push(
    { label: tStatic(lang, "nav.stores"), href: localePath(lang, "/stores") },
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
