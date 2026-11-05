import type { DigitalCardCategory, DigitalCardClassification } from "./types";

export const OTHER_KEY = "other";
export const GLOBAL_KEY = "global";

export interface GiftCardCountryGroup {
  key: string;
  label: string;
  flag_url: string | null;
  count: number;
}

export interface GiftCardBrandGroup {
  brand: string;
  label: string;
  logo_url: string | null;
  color: string;
  count: number;
  countries: GiftCardCountryGroup[];
}

const FALLBACK: DigitalCardClassification = {
  brand: OTHER_KEY,
  brand_label: "Other",
  logo_url: null,
  color: "#4b5563",
  country: OTHER_KEY,
  country_label: "Other",
  flag_url: null,
};

function classificationOf(category: DigitalCardCategory): DigitalCardClassification {
  return category.classification ?? FALLBACK;
}

/** Most cards first; "other" (and "global" for countries) always last. */
function byCountThenLabel<T extends { count: number; label: string }>(rank: (item: T) => number) {
  return (a: T, b: T) => rank(a) - rank(b) || b.count - a.count || a.label.localeCompare(b.label);
}

export function hasClassifications(categories: DigitalCardCategory[]): boolean {
  return categories.some((category) => category.classification);
}

export function groupGiftCards(categories: DigitalCardCategory[]): GiftCardBrandGroup[] {
  const brands = new Map<string, GiftCardBrandGroup>();
  const countries = new Map<string, Map<string, GiftCardCountryGroup>>();

  for (const category of categories) {
    const c = classificationOf(category);
    const group = brands.get(c.brand) ?? {
      brand: c.brand,
      label: c.brand_label,
      logo_url: c.logo_url,
      color: c.color,
      count: 0,
      countries: [],
    };
    group.count += 1;
    brands.set(c.brand, group);

    const byKey = countries.get(c.brand) ?? new Map<string, GiftCardCountryGroup>();
    const country = byKey.get(c.country) ?? {
      key: c.country,
      label: c.country_label,
      flag_url: c.flag_url,
      count: 0,
    };
    country.count += 1;
    byKey.set(c.country, country);
    countries.set(c.brand, byKey);
  }

  const countryRank = (item: GiftCardCountryGroup) =>
    item.key === GLOBAL_KEY ? 1 : item.key === OTHER_KEY ? 2 : 0;
  return [...brands.values()]
    .map((group) => ({
      ...group,
      countries: [...(countries.get(group.brand)?.values() ?? [])].sort(byCountThenLabel(countryRank)),
    }))
    .sort(byCountThenLabel((group) => (group.brand === OTHER_KEY ? 1 : 0)));
}

export function filterGiftCards(
  categories: DigitalCardCategory[],
  brand: string | null,
  country: string | null,
): DigitalCardCategory[] {
  return categories.filter((category) => {
    const c = classificationOf(category);
    return (brand === null || c.brand === brand) && (country === null || c.country === country);
  });
}

export interface GiftCardBrowseState {
  groups: GiftCardBrandGroup[];
  brandGroup: GiftCardBrandGroup | undefined;
  country: string | null;
  /** Show brand tiles instead of a card grid. */
  browsing: boolean;
  showAll: boolean;
  categories: DigitalCardCategory[];
}

/**
 * Same rules as the website `/gift-cards` URL state: `q` searches, `all` lists everything,
 * `brand` + `country` narrow; otherwise brand tiles. A brand with one country skips the picker.
 */
export function resolveGiftCardBrowse(
  all: DigitalCardCategory[],
  params: { q?: string | null; brand?: string | null; country?: string | null; all?: boolean },
): GiftCardBrowseState {
  const q = (params.q || "").trim().toLowerCase();
  const searched = q ? all.filter((category) => category.name.toLowerCase().includes(q)) : all;
  const groups = groupGiftCards(searched);
  const brandGroup = q ? undefined : groups.find((group) => group.brand === params.brand);
  let country = brandGroup?.countries.find((option) => option.key === params.country)?.key ?? null;
  if (brandGroup && !country && brandGroup.countries.length === 1) {
    country = brandGroup.countries[0].key;
  }
  const tiles = !q && !brandGroup && hasClassifications(all);
  const showAll = tiles && Boolean(params.all);

  return {
    groups,
    brandGroup,
    country,
    browsing: tiles && !showAll,
    showAll,
    categories: brandGroup ? filterGiftCards(searched, brandGroup.brand, country) : searched,
  };
}
