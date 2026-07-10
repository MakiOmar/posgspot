import type { Category, StoreSettings } from "~/lib/types";

/** Hard-coded loader fallback when the Storefront API is unreachable. */
export const FALLBACK_STORE_SETTINGS: StoreSettings = {
  business_name: "Games Spot",
  logo_url: null,
  currency: { code: "EGP", symbol: "L.E.", precision: 2, symbol_placement: "before" },
  contact: { phone: null, email_encoded: null, whatsapp: null },
  social: {},
  announcement: { message: "", link: "", enabled: false },
  theme: { accent_color: "#00d4aa" },
  sale_badge: { mode: "percent", text: "Sale" },
  catalog: { show_availability_on_cards: true },
  cod_enabled: true,
  maintenance_mode: false,
  online_payments: { enabled: false, provider: null, label: null },
  reward_points: { enabled: false, name: "Reward Points" },
  promo_codes: { enabled_at_checkout: true, allow_stacking: false },
  payment_icons: [],
  banners: [],
  newsletter: { enabled: false },
  turnstile: { enabled: false, site_key: null },
  locales: ["en"],
};

export function isFallbackStoreSettings(settings: StoreSettings): boolean {
  return (
    settings.business_name === FALLBACK_STORE_SETTINGS.business_name &&
    settings.logo_url === FALLBACK_STORE_SETTINGS.logo_url &&
    settings.theme?.accent_color === FALLBACK_STORE_SETTINGS.theme.accent_color &&
    settings.announcement?.enabled === FALLBACK_STORE_SETTINGS.announcement.enabled &&
    settings.contact?.phone === FALLBACK_STORE_SETTINGS.contact.phone
  );
}

export interface NavCategoriesLoad {
  ok: boolean;
  items: Category[];
}

export const EMPTY_NAV_CATEGORIES: NavCategoriesLoad = { ok: false, items: [] };
