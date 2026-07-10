import { REPAIR_STATUS_URL, TRACK_CONSOLE_URL } from "~/lib/config";
import { localePath } from "~/lib/i18n/paths";
import { tStatic } from "~/lib/i18n/context";
import type { StoreLocaleCode } from "~/lib/i18n/config";

export interface HeaderNavLink {
  labelKey: string;
  path: string;
  external?: boolean;
}

const NAV_DEFS: HeaderNavLink[] = [
  { labelKey: "nav.home", path: "/" },
  { labelKey: "nav.shop", path: "/products" },
  { labelKey: "nav.stores", path: "/stores" },
  { labelKey: "nav.trackRepairs", path: REPAIR_STATUS_URL, external: true },
  { labelKey: "nav.trackConsole", path: TRACK_CONSOLE_URL, external: true },
  { labelKey: "nav.contact", path: "/contact" },
  { labelKey: "nav.faq", path: "/faq" },
  { labelKey: "nav.about", path: "/about" },
];

export interface ResolvedNavLink {
  label: string;
  href: string;
  external?: boolean;
}

export function buildMainNavLinks(lang: StoreLocaleCode): ResolvedNavLink[] {
  return NAV_DEFS.map((item) => ({
    label: tStatic(lang, item.labelKey),
    href: item.external ? item.path : localePath(lang, item.path),
    external: item.external,
  }));
}
