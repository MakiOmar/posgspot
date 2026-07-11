import { TRACK_CONSOLE_URL } from "~/lib/config";
import { localePath } from "~/lib/i18n/paths";
import { tStatic } from "~/lib/i18n/context";
import type { StoreLocaleCode } from "~/lib/i18n/config";

export interface ResolvedNavChild {
  label: string;
  href: string;
}

export interface ResolvedNavItem {
  label: string;
  /** Flat link when set; omit for dropdown-only parents. */
  href?: string;
  external?: boolean;
  children?: ResolvedNavChild[];
}

/**
 * Build header nav items. Digital games is a platform dropdown (not a single page link).
 */
export function buildMainNavLinks(
  lang: StoreLocaleCode,
  options?: { digitalEnabled?: boolean },
): ResolvedNavItem[] {
  const digitalEnabled = options?.digitalEnabled !== false;

  const items: ResolvedNavItem[] = [
    { label: tStatic(lang, "nav.home"), href: localePath(lang, "/") },
    { label: tStatic(lang, "nav.shop"), href: localePath(lang, "/products") },
  ];

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
    { label: tStatic(lang, "nav.brands"), href: localePath(lang, "/brands") },
    { label: tStatic(lang, "nav.stores"), href: localePath(lang, "/stores") },
    { label: tStatic(lang, "nav.trackRepairs"), href: localePath(lang, "/repair-status") },
    {
      label: tStatic(lang, "nav.trackConsole"),
      href: TRACK_CONSOLE_URL,
      external: true,
    },
    { label: tStatic(lang, "nav.contact"), href: localePath(lang, "/contact") },
    { label: tStatic(lang, "nav.faq"), href: localePath(lang, "/faq") },
    { label: tStatic(lang, "nav.about"), href: localePath(lang, "/about") },
  );

  return items;
}
