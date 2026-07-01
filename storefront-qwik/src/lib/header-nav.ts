import { REPAIR_STATUS_URL, TRACK_CONSOLE_URL } from "~/lib/config";

export interface HeaderNavLink {
  label: string;
  href: string;
  external?: boolean;
}

/** Primary nav links (excluding Categories drawer). */
export const MAIN_NAV_LINKS: HeaderNavLink[] = [
  { label: "Home", href: "/" },
  { label: "Shop", href: "/products" },
  { label: "Track my repairs", href: REPAIR_STATUS_URL, external: true },
  { label: "Track my console", href: TRACK_CONSOLE_URL, external: true },
  { label: "Contact us", href: "/contact" },
  { label: "FAQs", href: "/faq" },
  { label: "About us", href: "/about" },
];
