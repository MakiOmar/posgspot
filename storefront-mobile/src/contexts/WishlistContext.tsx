import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";
import AsyncStorage from "@react-native-async-storage/async-storage";
import {
  addWishlist,
  fetchWishlist,
  mergeWishlist,
  removeWishlist,
} from "../lib/api";
import type { ProductSummary } from "../lib/types";
import {
  GUEST_WISHLIST_STORAGE_KEY,
  loadWishlistItems,
  mergeWishlistItems,
  saveWishlistItems,
  userWishlistStorageKey,
  WISHLIST_MAX_ITEMS,
} from "../lib/wishlist";
import { useApp } from "./AppContext";

interface WishlistContextValue {
  items: ProductSummary[];
  count: number;
  hydrated: boolean;
  isInWishlist: (productId: number) => boolean;
  toggle: (product: ProductSummary) => Promise<void>;
  refresh: () => Promise<void>;
}

const WishlistContext = createContext<WishlistContextValue | null>(null);

export function WishlistProvider({ children }: { children: React.ReactNode }) {
  const { token, contact } = useApp();
  const [items, setItems] = useState<ProductSummary[]>([]);
  const [hydrated, setHydrated] = useState(false);

  const persistGuest = useCallback(async (next: ProductSummary[]) => {
    setItems(next);
    await saveWishlistItems(GUEST_WISHLIST_STORAGE_KEY, next);
  }, []);

  const refresh = useCallback(async () => {
    if (!token) {
      const guest = await loadWishlistItems(GUEST_WISHLIST_STORAGE_KEY);
      setItems(guest);
      return;
    }
    const { data } = await fetchWishlist(token);
    const next = data.items || [];
    setItems(next);
    if (contact?.id) {
      await saveWishlistItems(userWishlistStorageKey(contact.id), next);
    }
  }, [token, contact?.id]);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      setHydrated(false);
      try {
        if (token && contact?.id) {
          const guest = await loadWishlistItems(GUEST_WISHLIST_STORAGE_KEY);
          const cached = await loadWishlistItems(
            userWishlistStorageKey(contact.id),
          );
          if (!cancelled && (guest.length || cached.length)) {
            setItems(mergeWishlistItems(cached, guest));
          }
          try {
            if (guest.length) {
              const { data } = await mergeWishlist(
                token,
                guest.map((g) => g.id).slice(0, WISHLIST_MAX_ITEMS),
              );
              if (!cancelled) {
                setItems(data.items || []);
                await saveWishlistItems(
                  userWishlistStorageKey(contact.id),
                  data.items || [],
                );
              }
              await AsyncStorage.removeItem(GUEST_WISHLIST_STORAGE_KEY);
            } else {
              const { data } = await fetchWishlist(token);
              if (!cancelled) {
                setItems(data.items || []);
                await saveWishlistItems(
                  userWishlistStorageKey(contact.id),
                  data.items || [],
                );
              }
            }
          } catch {
            // keep local cache
          }
        } else {
          const guest = await loadWishlistItems(GUEST_WISHLIST_STORAGE_KEY);
          if (!cancelled) {
            setItems(guest);
          }
        }
      } finally {
        if (!cancelled) {
          setHydrated(true);
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [token, contact?.id]);

  const isInWishlist = useCallback(
    (productId: number) => items.some((item) => item.id === productId),
    [items],
  );

  const toggle = useCallback(
    async (product: ProductSummary) => {
      const exists = items.some((item) => item.id === product.id);
      if (token) {
        const { data } = exists
          ? await removeWishlist(token, product.id)
          : await addWishlist(token, product.id);
        const next = data.items || [];
        setItems(next);
        if (contact?.id) {
          await saveWishlistItems(userWishlistStorageKey(contact.id), next);
        }
        return;
      }
      const next = exists
        ? items.filter((item) => item.id !== product.id)
        : mergeWishlistItems(items, [product]);
      await persistGuest(next);
    },
    [items, token, contact?.id, persistGuest],
  );

  const value = useMemo<WishlistContextValue>(
    () => ({
      items,
      count: items.length,
      hydrated,
      isInWishlist,
      toggle,
      refresh,
    }),
    [items, hydrated, isInWishlist, toggle, refresh],
  );

  return (
    <WishlistContext.Provider value={value}>{children}</WishlistContext.Provider>
  );
}

export function useWishlist(): WishlistContextValue {
  const ctx = useContext(WishlistContext);
  if (!ctx) {
    throw new Error("useWishlist must be used within WishlistProvider");
  }
  return ctx;
}
