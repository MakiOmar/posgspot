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
  GUEST_CART_STORAGE_KEY,
  LEGACY_CART_STORAGE_KEY,
  loadCartItems,
  mergeCartItems,
  saveCartItems,
  userCartStorageKey,
} from "../lib/cart";
import type { CartItem } from "../lib/types";
import { useApp } from "./AppContext";

interface CartContextValue {
  items: CartItem[];
  count: number;
  subtotal: number;
  hydrated: boolean;
  addItem: (item: CartItem) => Promise<void>;
  updateQty: (variationId: number, quantity: number, digitalKey?: string) => Promise<void>;
  removeItem: (variationId: number, digitalKey?: string) => Promise<void>;
  clear: () => Promise<void>;
  setItems: (items: CartItem[]) => Promise<void>;
}

const CartContext = createContext<CartContextValue | null>(null);

export function CartProvider({ children }: { children: React.ReactNode }) {
  const { contact } = useApp();
  const [items, setItemsState] = useState<CartItem[]>([]);
  const [hydrated, setHydrated] = useState(false);

  const storageKey = contact?.id
    ? userCartStorageKey(contact.id)
    : GUEST_CART_STORAGE_KEY;

  useEffect(() => {
    let cancelled = false;
    (async () => {
      setHydrated(false);
      if (contact?.id) {
        const userItems = await loadCartItems(userCartStorageKey(contact.id));
        const guestItems = await loadCartItems(GUEST_CART_STORAGE_KEY);
        const legacy = await loadCartItems(LEGACY_CART_STORAGE_KEY);
        const merged = mergeCartItems(
          mergeCartItems(userItems, guestItems),
          legacy,
        );
        if (!cancelled) {
          setItemsState(merged);
          await saveCartItems(userCartStorageKey(contact.id), merged);
          await AsyncStorage.removeItem(GUEST_CART_STORAGE_KEY);
          await AsyncStorage.removeItem(LEGACY_CART_STORAGE_KEY);
          setHydrated(true);
        }
        return;
      }
      const guest = await loadCartItems(GUEST_CART_STORAGE_KEY);
      const legacy = await loadCartItems(LEGACY_CART_STORAGE_KEY);
      const merged = mergeCartItems(guest, legacy);
      if (!cancelled) {
        setItemsState(merged);
        if (legacy.length) {
          await saveCartItems(GUEST_CART_STORAGE_KEY, merged);
          await AsyncStorage.removeItem(LEGACY_CART_STORAGE_KEY);
        }
        setHydrated(true);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [contact?.id]);

  const persist = useCallback(
    async (next: CartItem[]) => {
      setItemsState(next);
      await saveCartItems(storageKey, next);
    },
    [storageKey],
  );

  const addItem = useCallback(
    async (item: CartItem) => {
      let next: CartItem[] = [];
      setItemsState((prev) => {
        next = mergeCartItems(prev, [item]);
        return next;
      });
      await saveCartItems(storageKey, next);
    },
    [storageKey],
  );

  const updateQty = useCallback(
    async (variationId: number, quantity: number, digitalKey?: string) => {
      let next: CartItem[] = [];
      setItemsState((prev) => {
        next = prev
          .map((item) => {
            const match = digitalKey
              ? item.digital?.line_key === digitalKey
              : !item.digital && item.variationId === variationId;
            if (!match) {
              return item;
            }
            return { ...item, quantity: Math.max(1, quantity) };
          })
          .filter((item) => item.quantity > 0);
        return next;
      });
      await saveCartItems(storageKey, next);
    },
    [storageKey],
  );

  const removeItem = useCallback(
    async (variationId: number, digitalKey?: string) => {
      let next: CartItem[] = [];
      setItemsState((prev) => {
        next = prev.filter((item) => {
          if (digitalKey) {
            return item.digital?.line_key !== digitalKey;
          }
          return item.digital || item.variationId !== variationId;
        });
        return next;
      });
      await saveCartItems(storageKey, next);
    },
    [storageKey],
  );

  const clear = useCallback(async () => {
    await persist([]);
  }, [persist]);

  const setItems = useCallback(
    async (next: CartItem[]) => {
      await persist(next);
    },
    [persist],
  );

  const value = useMemo(
    () => ({
      items,
      count: items.reduce((sum, item) => sum + item.quantity, 0),
      subtotal: items.reduce(
        (sum, item) => sum + item.unitPrice * item.quantity,
        0,
      ),
      hydrated,
      addItem,
      updateQty,
      removeItem,
      clear,
      setItems,
    }),
    [items, hydrated, addItem, updateQty, removeItem, clear, setItems],
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart(): CartContextValue {
  const ctx = useContext(CartContext);
  if (!ctx) {
    throw new Error("useCart must be used within CartProvider");
  }
  return ctx;
}
