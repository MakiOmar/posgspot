import { $ } from "@builder.io/qwik";
import type { CartItem } from "~/lib/types";

/** Serializable cart state (items only). */
export interface CartState {
  items: CartItem[];
}

export const totalCartItems = (cart: CartState) =>
  cart.items.reduce((sum: number, line: CartItem) => sum + line.quantity, 0);

export const cartSubtotal = (cart: CartState) =>
  cart.items.reduce(
    (sum: number, line: CartItem) => sum + line.price * line.quantity,
    0,
  );

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
