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
  /** Simple dropdown (Services, Community). */
  children?: ResolvedNavChild[];
  /** Multi-column mega panel (Shop). When set, preferred over flat children. */
  mega?: {
    columns: ResolvedNavMegaColumn[];
  };
}

/**
 * Build header nav: Home, Shop mega, Services, Our Stores, Build Your Bundle,
 * Community (no destinations yet), Sell to Us.
 */
export function buildMainNavLinks(
  lang: StoreLocaleCode,
  options?: {
    digitalEnabled?: boolean;
    categories?: Category[];
    customBundleEnabled?: boolean;
    sellToUsEnabled?: boolean;
  },
): ResolvedNavItem[] {
  const digitalEnabled = options?.digitalEnabled !== false;
  const customBundleEnabled = Boolean(options?.customBundleEnabled);
  const sellToUsEnabled = Boolean(options?.sellToUsEnabled);

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

  const shopColumns: ResolvedNavMegaColumn[] = [shopColumn];
  if (digitalColumn) {
    shopColumns.push(digitalColumn);
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

  if (customBundleEnabled) {
    items.push({
      label: tStatic(lang, "nav.customBundle"),
      href: localePath(lang, "/custom-bundle"),
    });
  }

  items.push({ label: tStatic(lang, "nav.stores"), href: localePath(lang, "/stores") });

  // Community stays in the bar as a dropdown, but has no destinations yet.
  items.push({
    label: tStatic(lang, "nav.community"),
    children: [
      {
        label: tStatic(lang, "nav.comingSoon"),
        disabled: true,
      },
    ],
  });

  if (sellToUsEnabled) {
    items.push({
      label: tStatic(lang, "nav.sellToUs"),
      href: localePath(lang, "/sell-to-us"),
    });
  }

  return items;
}
