import AsyncStorage from "@react-native-async-storage/async-storage";
import {
  RECENTLY_VIEWED_MAX,
  parseStoredRecentlyViewed,
  prependRecentlyViewed,
  recentlyViewedForDisplay,
  recentlyViewedStorageKey,
  type StoredRecentlyViewed,
} from "./recently-viewed";
import type { ProductSummary } from "./types";

async function loadStored(locale: string): Promise<StoredRecentlyViewed[]> {
  try {
    return parseStoredRecentlyViewed(await AsyncStorage.getItem(recentlyViewedStorageKey(locale)));
  } catch {
    return [];
  }
}

async function persist(locale: string, items: StoredRecentlyViewed[]): Promise<void> {
  const key = recentlyViewedStorageKey(locale);
  try {
    if (items.length === 0) {
      await AsyncStorage.removeItem(key);
      return;
    }
    await AsyncStorage.setItem(key, JSON.stringify(items.slice(0, RECENTLY_VIEWED_MAX)));
  } catch {
    // History is best-effort; storage failures must not break the product page.
  }
}

export async function recordRecentlyViewed(locale: string, product: ProductSummary): Promise<void> {
  await persist(locale, prependRecentlyViewed(await loadStored(locale), product));
}

export async function getRecentlyViewedForDisplay(
  locale: string,
  options: { excludeProductId?: number; limit?: number } = {},
): Promise<ProductSummary[]> {
  return recentlyViewedForDisplay(await loadStored(locale), options);
}
