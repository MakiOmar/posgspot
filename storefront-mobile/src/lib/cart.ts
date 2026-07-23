import AsyncStorage from "@react-native-async-storage/async-storage";
import type { CartApiItem, CartItem } from "./types";

export const GUEST_CART_STORAGE_KEY = "gs-cart-guest-v1";
export const LEGACY_CART_STORAGE_KEY = "gs-cart-v1";
export const APPLIED_COUPON_STORAGE_KEY = "gs-applied-coupon-v1";

export const userCartStorageKey = (contactId: number) =>
  `gs-cart-user-${contactId}-v1`;

export function cartLineKey(item: CartItem): string {
  if (item.digital?.line_key) {
    return `d:${item.digital.line_key}`;
  }
  return `v:${item.variationId}`;
}

export function toCartApiItem(item: CartItem): CartApiItem {
  const api: CartApiItem = {
    variation_id: item.variationId,
    quantity: item.digital ? 1 : item.quantity,
  };
  if (item.digital) {
    api.digital = item.digital;
    api.unit_price = item.digital.price;
  }
  return api;
}

export async function loadCartItems(key: string): Promise<CartItem[]> {
  try {
    const raw = await AsyncStorage.getItem(key);
    if (!raw) {
      return [];
    }
    const parsed = JSON.parse(raw) as { items?: CartItem[] } | CartItem[];
    if (Array.isArray(parsed)) {
      return parsed;
    }
    return Array.isArray(parsed.items) ? parsed.items : [];
  } catch {
    return [];
  }
}

export async function saveCartItems(key: string, items: CartItem[]): Promise<void> {
  await AsyncStorage.setItem(key, JSON.stringify({ items }));
}

export function mergeCartItems(a: CartItem[], b: CartItem[]): CartItem[] {
  const map = new Map<string, CartItem>();
  [...a, ...b].forEach((item) => {
    const key = cartLineKey(item);
    const existing = map.get(key);
    if (!existing) {
      map.set(key, { ...item });
      return;
    }
    if (item.digital) {
      map.set(key, { ...existing, ...item, quantity: 1 });
      return;
    }
    map.set(key, {
      ...existing,
      quantity: existing.quantity + item.quantity,
      unitPrice: item.unitPrice ?? existing.unitPrice,
    });
  });
  return Array.from(map.values());
}
