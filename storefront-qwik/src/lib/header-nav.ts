import { consoleNavCategories } from "~/lib/console-categories";
import { localePath } from "~/lib/i18n/paths";
import { tStatic } from "~/lib/i18n/context";
import type { StoreLocaleCode } from "~/lib/i18n/config";
import type { Category, ShopMenuPhysicalItem } from "~/lib/types";

export interface ResolvedNavChild {
  label: string;
  href?: string;
  disabled?: boolean;
  hint?: string;
  /** Client action (e.g. open floating support chat). */
  action?: "open-support-chat";
  /** Nested links under a group header (Shop Physical). */
  children?: ResolvedNavChild[];
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

function withLocaleHref(lang: StoreLocaleCode, href: string): string {
  if (!href || href.startsWith("tel:") || /^https?:\/\//i.test(href)) {
    return href;
  }
  const path = href.startsWith("/") ? href : `/${href}`;
  const qIndex = path.indexOf("?");
  if (qIndex === -1) {
    return localePath(lang, path);
  }
  return `${localePath(lang, path.slice(0, qIndex))}${path.slice(qIndex)}`;
}

/** Map resolved shop_menu.physical into nav children (locale-prefixed hrefs). */
export function physicalNavFromShopMenu(
  lang: StoreLocaleCode,
  physical: ShopMenuPhysicalItem[],
): ResolvedNavChild[] {
  return physical.map((item) => mapShopMenuItem(lang, item));
}

function mapShopMenuItem(lang: StoreLocaleCode, item: ShopMenuPhysicalItem): ResolvedNavChild {
  if (item.type === "group") {
    return {
      label: item.label,
      children: (item.children || []).map((child) => mapShopMenuItem(lang, child)),
    };
  }
  return {
    label: item.label,
    href: withLocaleHref(lang, item.href),
  };
}

function flattenNavChildren(links: ResolvedNavChild[]): ResolvedNavChild[] {
  const out: ResolvedNavChild[] = [];
  for (const link of links) {
    if (link.children && link.children.length > 0) {
      out.push(...flattenNavChildren(link.children));
      continue;
    }
    out.push(link);
  }
  return out;
}

/**
 * Build header nav: Home, Shop mega, Services, Our Stores, Build Your Bundle,
 * Community (when enabled), Sell to Us.
 */
export function buildMainNavLinks(
  lang: StoreLocaleCode,
  options?: {
    digitalEnabled?: boolean;
    categories?: Category[];
    /** Locale-resolved Physical column from GET /settings shop_menu. */
    shopMenuPhysical?: ShopMenuPhysicalItem[];
    customBundleEnabled?: boolean;
    sellToUsEnabled?: boolean;
    communityEnabled?: boolean;
  },
): ResolvedNavItem[] {
  const digitalEnabled = options?.digitalEnabled !== false;
  const customBundleEnabled = Boolean(options?.customBundleEnabled);
  const sellToUsEnabled = Boolean(options?.sellToUsEnabled);
  const communityEnabled = Boolean(options?.communityEnabled);

  const configuredPhysical = options?.shopMenuPhysical ?? [];
  const physicalChildren: ResolvedNavChild[] =
    configuredPhysical.length > 0
      ? physicalNavFromShopMenu(lang, configuredPhysical)
      : consoleNavCategories(options?.categories ?? []).map((category) => ({
          label: category.name,
          href: category.slug
            ? localePath(lang, `/category/${category.slug}`)
            : localePath(lang, `/products?category_id=${category.id}`),
        }));

  const shopColumn: ResolvedNavMegaColumn = {
    title: tStatic(lang, "nav.shopColumnPhysical"),
    links: [...physicalChildren],
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
            label: tStatic(lang, "nav.gamesPsPlus"),
            href: localePath(lang, "/games?product_type=subscription&platform=5"),
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

  // Flat children for mobile drawer fallbacks (groups expanded to their links).
  const shopFlatChildren: ResolvedNavChild[] = shopColumns.flatMap((col) =>
    flattenNavChildren(col.links),
  );

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

  if (sellToUsEnabled) {
    items.push({
      label: tStatic(lang, "nav.sellToUs"),
      href: localePath(lang, "/sell-to-us"),
    });
  }

  return items;
}
