import type { AuthState } from "~/lib/auth-actions";
import {
  addToWishlist,
  fetchWishlist,
  getActiveContentLocale,
  mergeWishlist,
  removeFromWishlist,
} from "~/lib/api";
import type { ProductDetail, ProductSummary, ProductVariation } from "~/lib/types";

/** Guest wishlist persisted while signed out. */
export const GUEST_WISHLIST_STORAGE_KEY = "gs-wishlist-guest-v1";

/** Per-customer wishlist cache while signed in. */
export const userWishlistStorageKey = (contactId: number) => `gs-wishlist-user-${contactId}-v1`;

export interface WishlistState {
  items: ProductSummary[];
  /** Contact id wishlist was synced for this browser session (client-only). */
  mergedForContactId: number | null;
  /** True once guest wishlist has been read from localStorage. */
  hydrated: boolean;
}

const isProductSummary = (value: unknown): value is ProductSummary =>
  typeof value === "object" &&
  value !== null &&
  typeof (value as ProductSummary).id === "number" &&
  typeof (value as ProductSummary).name === "string";

/** Parse wishlist JSON from localStorage; returns [] on corrupt data. */
export const parseStoredWishlist = (raw: string | null): ProductSummary[] => {
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
};

/** Write wishlist items to localStorage, removing the key when empty. */
export const persistWishlistToStorage = (key: string, items: ProductSummary[]) => {
  if (items.length === 0) {
    localStorage.removeItem(key);
    return;
  }
  localStorage.setItem(key, JSON.stringify(items));
};

/** Load guest wishlist from localStorage. */
export const loadGuestWishlistFromStorage = (): ProductSummary[] =>
  parseStoredWishlist(localStorage.getItem(GUEST_WISHLIST_STORAGE_KEY));

/** Merge guest session items with saved user items (dedupe by product id). */
export const mergeWishlistItems = (
  left: ProductSummary[],
  right: ProductSummary[],
): ProductSummary[] => {
  const merged = new Map<number, ProductSummary>();
  for (const item of [...left, ...right]) {
    merged.set(item.id, item);
  }
  return Array.from(merged.values());
};

export const wishlistCount = (wishlist: WishlistState): number => wishlist.items.length;

export const isInWishlist = (wishlist: WishlistState, productId: number): boolean =>
  wishlist.items.some((item) => item.id === productId);

/** Build a card-friendly summary from PDP data for guest/local toggles. */
export const productSummaryFromDetail = (
  product: ProductDetail,
  variation: ProductVariation,
): ProductSummary => ({
  id: product.id,
  slug: product.slug,
  name: product.name,
  sku: product.sku,
  type: product.type,
  image_url: variation.images[0] || product.images[0] || null,
  variation_id: variation.id || null,
  variation_name: variation.name || null,
  has_options: product.variations.length > 1,
  price: variation.price,
  compare_at_price: variation.compare_at_price ?? null,
  on_sale: variation.on_sale ?? false,
  sale_percent: variation.sale_percent ?? 0,
  in_stock: variation.in_stock,
});

/**
 * Merge guest/local wishlist into the signed-in account and refresh from the API.
 * Call once per login / page load for a given contact id.
 */
export const syncWishlistForUser = async (
  wishlist: WishlistState,
  token: string,
  contactId: number,
  locale?: string,
): Promise<void> => {
  const contentLocale = locale ?? getActiveContentLocale();
  const guestItems = loadGuestWishlistFromStorage();
  const sessionItems = mergeWishlistItems(guestItems, wishlist.items);
  const productIds = sessionItems.map((item) => item.id);

  try {
    const { data } =
      productIds.length > 0
        ? await mergeWishlist(token, productIds, contentLocale)
        : await fetchWishlist(token, contentLocale);
    wishlist.items = data.items;
    wishlist.mergedForContactId = contactId;
    persistWishlistToStorage(userWishlistStorageKey(contactId), data.items);
    localStorage.removeItem(GUEST_WISHLIST_STORAGE_KEY);
  } catch {
    const cached = parseStoredWishlist(localStorage.getItem(userWishlistStorageKey(contactId)));
    wishlist.items = cached.length > 0 ? cached : sessionItems;
    wishlist.mergedForContactId = contactId;
  }
};

export const toggleWishlistItem = async (
  wishlist: WishlistState,
  auth: AuthState,
  product: ProductSummary,
  locale?: string,
): Promise<boolean> => {
  const contentLocale = locale ?? getActiveContentLocale();
  const wasInList = isInWishlist(wishlist, product.id);

  if (auth.token) {
    const { data } = wasInList
      ? await removeFromWishlist(auth.token, product.id, contentLocale)
      : await addToWishlist(auth.token, product.id, contentLocale);
    wishlist.items = data.items;
    if (auth.contact?.id && wishlist.mergedForContactId === auth.contact.id) {
      persistWishlistToStorage(userWishlistStorageKey(auth.contact.id), data.items);
    }
    return !wasInList;
  }

  if (wasInList) {
    wishlist.items = wishlist.items.filter((item) => item.id !== product.id);
  } else {
    wishlist.items = mergeWishlistItems(wishlist.items, [product]);
  }
  return !wasInList;
};

export const removeWishlistItem = async (
  wishlist: WishlistState,
  auth: AuthState,
  productId: number,
  locale?: string,
): Promise<void> => {
  const contentLocale = locale ?? getActiveContentLocale();

  if (auth.token) {
    const { data } = await removeFromWishlist(auth.token, productId, contentLocale);
    wishlist.items = data.items;
    if (auth.contact?.id && wishlist.mergedForContactId === auth.contact.id) {
      persistWishlistToStorage(userWishlistStorageKey(auth.contact.id), data.items);
    }
    return;
  }

  wishlist.items = wishlist.items.filter((item) => item.id !== productId);
};
