import { component$, createContextId, Slot, useContext, useContextProvider, useStore, useVisibleTask$ } from "@builder.io/qwik";
import type { CartState } from "~/lib/cart-actions";
import type { CartItem } from "~/lib/types";

const STORAGE_KEY = "gs-cart-v1";

export const CartContext = createContextId<CartState>("games-spot.cart");

export const CartProvider = component$(() => {
  const cart = useStore<CartState>({ items: [] });

  useContextProvider(CartContext, cart);

  useVisibleTask$(() => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (raw) {
        const parsed = JSON.parse(raw) as CartItem[];
        if (Array.isArray(parsed)) {
          cart.items = parsed;
        }
      }
    } catch {
      /* ignore corrupt cart */
    }
  });

  useVisibleTask$(({ track }) => {
    track(() => cart.items);
    localStorage.setItem(STORAGE_KEY, JSON.stringify(cart.items));
  });

  return <Slot />;
});

export function useCart() {
  return useContext(CartContext);
}
