import { $ } from "@builder.io/qwik";
import type { CartItem, CartLine } from "~/lib/types";

/** Legacy single-key cart persisted before guest/user split. */
export const LEGACY_CART_STORAGE_KEY = "gs-cart-v1";

/** Guest cart persisted while signed out. */
export const GUEST_CART_STORAGE_KEY = "gs-cart-guest-v1";

/** Per-customer cart persisted while signed in. */
export const userCartStorageKey = (contactId: number) => `gs-cart-user-${contactId}-v1`;

/** Serializable cart state (items only). */
export interface CartState {
  items: CartItem[];
  /** Contact id carts were merged for this browser session (client-only). */
  mergedForContactId: number | null;
  /** True once guest cart has been read from localStorage. */
  hydrated: boolean;
}

const isCartItem = (value: unknown): value is CartItem =>
  typeof value === "object" &&
  value !== null &&
  typeof (value as CartItem).variationId === "number" &&
  typeof (value as CartItem).quantity === "number";

/** Parse cart JSON from localStorage; returns [] on corrupt data. */
export const parseStoredCart = (raw: string | null): CartItem[] => {
  if (!raw) {
    return [];
  }
  try {
    const parsed = JSON.parse(raw) as unknown;
    if (!Array.isArray(parsed)) {
      return [];
    }
    return parsed.filter(isCartItem).map((line) => ({ ...line }));
  } catch {
    return [];
  }
};

/** Merge two carts, summing quantity for the same variation. */
export const mergeCartItems = (left: CartItem[], right: CartItem[]): CartItem[] => {
  const merged = new Map<number, CartItem>();
  for (const line of [...left, ...right]) {
    const existing = merged.get(line.variationId);
    if (existing) {
      merged.set(line.variationId, {
        ...existing,
        ...line,
        quantity: existing.quantity + line.quantity,
      });
    } else {
      merged.set(line.variationId, { ...line });
    }
  }
  return Array.from(merged.values());
};

/** Write cart lines to localStorage, removing the key when empty. */
export const persistCartToStorage = (key: string, items: CartItem[]) => {
  if (items.length === 0) {
    localStorage.removeItem(key);
    return;
  }
  localStorage.setItem(key, JSON.stringify(items));
};

/** Load guest cart, migrating legacy storage when present. */
export const loadGuestCartFromStorage = (): CartItem[] => {
  const guest = parseStoredCart(localStorage.getItem(GUEST_CART_STORAGE_KEY));
  if (guest.length > 0) {
    return guest;
  }
  const legacy = parseStoredCart(localStorage.getItem(LEGACY_CART_STORAGE_KEY));
  if (legacy.length > 0) {
    persistCartToStorage(GUEST_CART_STORAGE_KEY, legacy);
    localStorage.removeItem(LEGACY_CART_STORAGE_KEY);
  }
  return legacy;
};

/**
 * Merge the active guest session cart with a signed-in customer's saved cart.
 * Call once per login / page load for a given contact id.
 */
export const mergeGuestCartForUser = (
  cart: CartState,
  contactId: number,
  sessionItems: CartItem[],
): CartItem[] => {
  const userKey = userCartStorageKey(contactId);
  const savedUserItems = parseStoredCart(localStorage.getItem(userKey));
  const merged = mergeCartItems(sessionItems, savedUserItems);
  cart.items = merged;
  cart.mergedForContactId = contactId;
  localStorage.removeItem(GUEST_CART_STORAGE_KEY);
  localStorage.removeItem(LEGACY_CART_STORAGE_KEY);
  persistCartToStorage(userKey, merged);
  return merged;
};

export const totalCartItems = (cart: CartState) =>
  cart.items.reduce((sum: number, line: CartItem) => sum + line.quantity, 0);

export const cartSubtotal = (cart: CartState) =>
  cart.items.reduce(
    (sum: number, line: CartItem) => sum + line.price * line.quantity,
    0,
  );

/** Apply validated API lines to in-memory cart items (prices + labels). */
export const applyCartValidation = (cart: CartState, lines: CartLine[]): boolean => {
  const byVariation = new Map(lines.map((line) => [line.variation_id, line]));
  let pricesChanged = false;

  for (const item of cart.items) {
    const validated = byVariation.get(item.variationId);
    if (!validated) {
      continue;
    }
    if (item.price !== validated.unit_price) {
      item.price = validated.unit_price;
      pricesChanged = true;
    }
    if (item.name !== validated.name) {
      item.name = validated.name;
    }
    if (item.variationName !== validated.variation_name) {
      item.variationName = validated.variation_name;
    }
  }

  return pricesChanged;
};

export const addCartItem = $((cart: CartState, item: CartItem) => {
  const existing = cart.items.find((line) => line.variationId === item.variationId);
  if (existing) {
    existing.quantity += item.quantity;
  } else {
    cart.items.push({ ...item });
  }
});

export const removeCartItem = $((cart: CartState, variationId: number) => {
  cart.items = cart.items.filter((line) => line.variationId !== variationId);
});

export const setCartQuantity = $((cart: CartState, variationId: number, quantity: number) => {
  const line = cart.items.find((entry) => entry.variationId === variationId);
  if (!line) {
    return;
  }
  if (quantity <= 0) {
    cart.items = cart.items.filter((entry) => entry.variationId !== variationId);
  } else {
    line.quantity = quantity;
  }
});

export const clearCart = $((cart: CartState) => {
  cart.items = [];
});
