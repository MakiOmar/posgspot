import { $ } from "@builder.io/qwik";
import type { CartInspection, CartItem, CartLine, CartLineStatus } from "~/lib/types";

/** Guest/session promo code persisted alongside cart lines. */
export const APPLIED_COUPON_STORAGE_KEY = "gs-applied-coupon-v1";

export interface AppliedCouponState {
  code: string;
  label?: string;
}

const parseStoredCoupons = (raw: string | null): AppliedCouponState[] => {
  if (!raw) {
    return [];
  }
  try {
    const parsed = JSON.parse(raw) as unknown;
    if (Array.isArray(parsed)) {
      return parsed
        .filter(
          (entry): entry is AppliedCouponState =>
            typeof entry === "object" &&
            entry !== null &&
            typeof (entry as AppliedCouponState).code === "string" &&
            (entry as AppliedCouponState).code.trim() !== "",
        )
        .map((entry) => ({
          code: entry.code.trim(),
          label: entry.label,
        }));
    }
    if (
      typeof parsed === "object" &&
      parsed !== null &&
      typeof (parsed as AppliedCouponState).code === "string" &&
      (parsed as AppliedCouponState).code.trim() !== ""
    ) {
      const single = parsed as AppliedCouponState;
      return [{ code: single.code.trim(), label: single.label }];
    }
  } catch {
    return [];
  }
  return [];
};

export const loadAppliedCoupons = (): AppliedCouponState[] => {
  if (typeof localStorage === "undefined") {
    return [];
  }
  return parseStoredCoupons(localStorage.getItem(APPLIED_COUPON_STORAGE_KEY));
};

export const loadAppliedCoupon = (): AppliedCouponState | null => loadAppliedCoupons()[0] ?? null;

export const persistAppliedCoupons = (coupons: AppliedCouponState[]) => {
  if (typeof localStorage === "undefined") {
    return;
  }
  const cleaned = coupons
    .map((coupon) => ({ code: coupon.code.trim(), label: coupon.label }))
    .filter((coupon) => coupon.code !== "");
  if (cleaned.length === 0) {
    localStorage.removeItem(APPLIED_COUPON_STORAGE_KEY);
    return;
  }
  localStorage.setItem(APPLIED_COUPON_STORAGE_KEY, JSON.stringify(cleaned));
};

export const persistAppliedCoupon = (coupon: AppliedCouponState | null) => {
  persistAppliedCoupons(coupon ? [coupon] : []);
};

export const clearAppliedCoupon = () => {
  persistAppliedCoupons([]);
};

/** Build cart validate payload for single or stacked promo codes. */
export const couponRequestPayload = (
  codes: string[],
  allowStacking: boolean,
): { coupon_code?: string; coupon_codes?: string[] } => {
  const normalized = codes.map((code) => code.trim()).filter(Boolean);
  if (normalized.length === 0) {
    return {};
  }
  if (allowStacking) {
    return { coupon_codes: normalized };
  }
  return { coupon_code: normalized[0] };
};

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
    return parsed.filter(isCartItem).map((line) => {
      const next = { ...line };
      // Backfill digital.price from line price when older carts omitted it.
      if (next.digital?.kind && (next.digital.price == null || !Number.isFinite(Number(next.digital.price)))) {
        next.digital = { ...next.digital, price: next.price };
      }
      return next;
    });
  } catch {
    return [];
  }
};

/** Stable cart line identity (digital lines share POS SKUs but must not merge). */
export const cartLineKey = (item: CartItem): string => {
  if (item.digital?.line_key) {
    return `d:${item.digital.line_key}`;
  }
  return `v:${item.variationId}`;
};

/**
 * Payload line for cart validate / checkout.
 * Always re-attach digital.price from the cart line so POS SKU price (often 0) is not used.
 */
export const toCartApiItem = (line: CartItem): Record<string, unknown> => {
  const item: Record<string, unknown> = {
    variation_id: line.variationId,
    quantity: line.quantity,
  };
  if (line.digital?.kind) {
    const price = Number(line.digital.price ?? line.price);
    item.digital = {
      ...line.digital,
      price: Number.isFinite(price) ? price : line.price,
    };
    item.unit_price = Number.isFinite(price) ? price : line.price;
  }
  return item;
};

/** Merge two carts, summing quantity for the same line key. */
export const mergeCartItems = (left: CartItem[], right: CartItem[]): CartItem[] => {
  const merged = new Map<string, CartItem>();
  for (const line of [...left, ...right]) {
    const key = cartLineKey(line);
    const existing = merged.get(key);
    if (existing) {
      merged.set(key, {
        ...existing,
        ...line,
        digital: line.digital ?? existing.digital,
        quantity: existing.quantity + line.quantity,
      });
    } else {
      merged.set(key, { ...line });
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
    // Keep Accounts catalog price for digital lines.
    if (!item.digital && item.price !== validated.unit_price) {
      item.price = validated.unit_price;
      pricesChanged = true;
    }
    if (!item.digital && item.name !== validated.name) {
      item.name = validated.name;
    }
    if (!item.digital && item.variationName !== validated.variation_name) {
      item.variationName = validated.variation_name;
    }
  }

  return pricesChanged;
};

/** Lines where requested quantity exceeds available stock (partial stock only). */
export const getPartialStockIssues = (lineStatus: CartLineStatus[]): CartLineStatus[] =>
  lineStatus.filter(
    (line) =>
      line.stock_tracked &&
      line.max_quantity !== null &&
      line.max_quantity > 0 &&
      line.requested_quantity > line.max_quantity,
  );

/** True when the line should be dropped from the cart (OOS or unavailable). */
export const shouldAutoRemoveCartLine = (line: CartLineStatus): boolean =>
  line.max_quantity !== null && line.max_quantity <= 0;

/**
 * Apply inspect API results: drop OOS lines, refresh prices, return sync metadata.
 */
export const syncCartFromInspection = (
  cart: CartState,
  inspection: CartInspection,
): { removedCount: number; pricesChanged: boolean; partialIssues: CartLineStatus[] } => {
  const statusByVariation = new Map(inspection.line_status.map((line) => [line.variation_id, line]));
  let removedCount = 0;

  cart.items = cart.items.filter((item) => {
    const status = statusByVariation.get(item.variationId);
    if (status && shouldAutoRemoveCartLine(status)) {
      removedCount += 1;
      return false;
    }
    return true;
  });

  const pricesChanged = applyCartValidation(cart, inspection.lines);
  for (const item of cart.items) {
    const status = statusByVariation.get(item.variationId);
    if (!status) {
      continue;
    }
    if (item.digital) {
      continue;
    }
    if (item.name !== status.name) {
      item.name = status.name;
    }
    if (item.variationName !== status.variation_name) {
      item.variationName = status.variation_name;
    }
    if (item.price !== status.unit_price) {
      item.price = status.unit_price;
    }
  }

  return {
    removedCount,
    pricesChanged,
    partialIssues: getPartialStockIssues(inspection.line_status),
  };
};

export const formatMaxCartQuantity = (max: number): string => {
  return Number.isInteger(max) ? String(max) : String(max);
};

export const addCartItem = $((cart: CartState, item: CartItem) => {
  const key = cartLineKey(item);
  const existing = cart.items.find((line) => cartLineKey(line) === key);
  if (existing) {
    // Digital secrets are one-per-line; keep quantity at 1.
    if (item.digital) {
      existing.quantity = 1;
      existing.digital = item.digital;
      existing.price = item.price;
      existing.name = item.name;
      return;
    }
    existing.quantity += item.quantity;
  } else {
    cart.items.push({
      ...item,
      quantity: item.digital ? 1 : item.quantity,
    });
  }
});

/** Add multiple lines (e.g. reorder); merges quantities for matching line keys. */
export const addCartItems = $((cart: CartState, items: CartItem[]) => {
  for (const item of items) {
    if (!item.variationId || item.quantity <= 0) {
      continue;
    }
    const key = cartLineKey(item);
    const existing = cart.items.find((line) => cartLineKey(line) === key);
    if (existing) {
      if (item.digital) {
        existing.quantity = 1;
        existing.digital = item.digital;
        existing.price = item.price;
        existing.name = item.name;
      } else {
        existing.quantity += item.quantity;
      }
    } else {
      cart.items.push({
        ...item,
        quantity: item.digital ? 1 : item.quantity,
      });
    }
  }
});

export const removeCartItem = $((cart: CartState, lineKey: string) => {
  cart.items = cart.items.filter((line) => cartLineKey(line) !== lineKey);
});

export const setCartQuantity = $((cart: CartState, lineKey: string, quantity: number) => {
  const line = cart.items.find((entry) => cartLineKey(entry) === lineKey);
  if (!line) {
    return;
  }
  if (line.digital) {
    // Digital deliveries are always quantity 1.
    if (quantity <= 0) {
      cart.items = cart.items.filter((entry) => cartLineKey(entry) !== lineKey);
    }
    return;
  }
  if (quantity <= 0) {
    cart.items = cart.items.filter((entry) => cartLineKey(entry) !== lineKey);
  } else {
    line.quantity = quantity;
  }
});

export const clearCart = $((cart: CartState) => {
  cart.items = [];
  clearAppliedCoupon();
});
