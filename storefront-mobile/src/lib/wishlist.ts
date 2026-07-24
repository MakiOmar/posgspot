import AsyncStorage from "@react-native-async-storage/async-storage";
import type { ProductSummary } from "./types";

export const GUEST_WISHLIST_STORAGE_KEY = "gs-wishlist-guest-v1";
export const WISHLIST_MAX_ITEMS = 100;

export function userWishlistStorageKey(contactId: number): string {
  return `gs-wishlist-user-${contactId}-v1`;
}

function isProductSummary(value: unknown): value is ProductSummary {
  return (
    typeof value === "object" &&
    value !== null &&
    typeof (value as ProductSummary).id === "number" &&
    typeof (value as ProductSummary).name === "string"
  );
}

export function parseStoredWishlist(raw: string | null): ProductSummary[] {
  if (!raw) {
    return [];
  }
  try {
    const parsed = JSON.parse(raw) as unknown;
    if (!Array.isArray(parsed)) {
      return [];
    }
    return parsed.filter(isProductSummary).map((item) => ({ ...item }));
  } catch {
    return [];
  }
}

export async function loadWishlistItems(key: string): Promise<ProductSummary[]> {
  const raw = await AsyncStorage.getItem(key);
  return parseStoredWishlist(raw);
}

export async function saveWishlistItems(
  key: string,
  items: ProductSummary[],
): Promise<void> {
  if (!items.length) {
    await AsyncStorage.removeItem(key);
    return;
  }
  await AsyncStorage.setItem(key, JSON.stringify(items.slice(0, WISHLIST_MAX_ITEMS)));
}

export function mergeWishlistItems(
  left: ProductSummary[],
  right: ProductSummary[],
): ProductSummary[] {
  const merged = new Map<number, ProductSummary>();
  for (const item of [...left, ...right]) {
    merged.set(item.id, item);
  }
  return Array.from(merged.values()).slice(0, WISHLIST_MAX_ITEMS);
}
