import { useWindowDimensions } from "react-native";
import type {
  HomepageHeroSlide,
  HomepagePromoTile,
  HomepageTrustBadge,
} from "../../lib/types";

/** Run async work with a concurrency ceiling (home shelf product fetches). */
export async function mapPool<T, R>(
  items: T[],
  limit: number,
  mapper: (item: T, index: number) => Promise<R>,
): Promise<R[]> {
  const results: R[] = new Array(items.length);
  let next = 0;
  const workers = Array.from(
    { length: Math.min(Math.max(1, limit), Math.max(1, items.length)) },
    async () => {
      while (next < items.length) {
        const i = next++;
        results[i] = await mapper(items[i], i);
      }
    },
  );
  await Promise.all(workers);
  return results;
}

export function asSlides(settings: Record<string, unknown>): HomepageHeroSlide[] {
  const raw = settings.slides;
  if (!Array.isArray(raw)) return [];
  return raw.filter(
    (s): s is HomepageHeroSlide =>
      !!s &&
      typeof s === "object" &&
      typeof (s as HomepageHeroSlide).image_url === "string",
  );
}

export function asTiles(settings: Record<string, unknown>): HomepagePromoTile[] {
  const raw = settings.tiles;
  if (!Array.isArray(raw)) return [];
  return raw.filter(
    (t): t is HomepagePromoTile =>
      !!t &&
      typeof t === "object" &&
      typeof (t as HomepagePromoTile).image_url === "string" &&
      typeof (t as HomepagePromoTile).href === "string" &&
      (t as HomepagePromoTile).href.length > 0,
  );
}

export function asTrustBadges(
  settings: Record<string, unknown>,
): HomepageTrustBadge[] {
  const raw = settings.items;
  if (!Array.isArray(raw)) return [];
  return raw as HomepageTrustBadge[];
}

export function settingNumber(
  settings: Record<string, unknown>,
  key: string,
  fallback: number,
): number {
  const raw = settings[key];
  const n = typeof raw === "number" ? raw : Number(raw);
  return Number.isFinite(n) && n > 0 ? n : fallback;
}

/** Hand-picked product ids for a category shelf; empty when the shelf is in automatic mode. */
export function shelfPickedIds(settings: Record<string, unknown>): number[] {
  if (settings.product_mode !== "selected" || !Array.isArray(settings.product_ids)) {
    return [];
  }
  return settings.product_ids
    .map((id) => Number(id))
    .filter((id) => Number.isInteger(id) && id > 0);
}

/** Screen width for home layout; prefer over module-level Dimensions.get. */
export function useHomeScreenWidth(): number {
  const { width } = useWindowDimensions();
  return width;
}
