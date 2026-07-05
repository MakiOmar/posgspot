import {
  component$,
  createContextId,
  Slot,
  useContext,
  useContextProvider,
  useStore,
  useVisibleTask$,
} from "@builder.io/qwik";
import { useAuth } from "~/lib/auth-context";
import { AUTH_STORAGE_KEY, type PersistedAuth } from "~/lib/auth-actions";
import {
  GUEST_CART_STORAGE_KEY,
  LEGACY_CART_STORAGE_KEY,
  loadGuestCartFromStorage,
  mergeCartItems,
  mergeGuestCartForUser,
  parseStoredCart,
  persistCartToStorage,
  type CartState,
  userCartStorageKey,
} from "~/lib/cart-actions";

export const CartContext = createContextId<CartState>("games-spot.cart");

export const CartProvider = component$(() => {
  const auth = useAuth();
  const cart = useStore<CartState>({ items: [], mergedForContactId: null, hydrated: false });

  useContextProvider(CartContext, cart);

  // Hydrate cart from localStorage on the client only. When a session token is
  // cached, load the user cart immediately so persist cannot wipe it before merge.
  useVisibleTask$(() => {
    let contactId: number | null = null;
    try {
      const raw = localStorage.getItem(AUTH_STORAGE_KEY);
      if (raw) {
        const parsed = JSON.parse(raw) as PersistedAuth;
        if (parsed?.token && parsed.contact?.id) {
          contactId = parsed.contact.id;
        }
      }
    } catch {
      /* ignore corrupt auth storage */
    }

    if (contactId) {
      const guestItems = loadGuestCartFromStorage();
      const userItems = parseStoredCart(localStorage.getItem(userCartStorageKey(contactId)));
      cart.items = mergeCartItems(guestItems, userItems);
      cart.mergedForContactId = contactId;
    } else {
      cart.items = loadGuestCartFromStorage();
    }

    cart.hydrated = true;
  });

  // When auth becomes ready, merge guest session cart with the customer's saved cart.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => auth.ready);
    track(() => auth.token);
    track(() => auth.contact?.id);
    track(() => cart.hydrated);

    if (!auth.ready || !cart.hydrated) {
      return;
    }

    if (auth.token && auth.contact?.id) {
      if (cart.mergedForContactId === auth.contact.id) {
        return;
      }
      mergeGuestCartForUser(cart, auth.contact.id, cart.items);
      return;
    }

    cart.mergedForContactId = null;
  });

  // Persist cart to guest or user storage whenever lines or auth change.
  useVisibleTask$(({ track }) => {
    track(() => cart.items);
    track(() => auth.token);
    track(() => auth.contact?.id);
    track(() => auth.ready);
    track(() => cart.hydrated);
    track(() => cart.mergedForContactId);

    if (!auth.ready || !cart.hydrated) {
      return;
    }

    if (auth.token && auth.contact?.id) {
      // Avoid writing an empty cart before login merge finishes on this session.
      if (cart.mergedForContactId !== auth.contact.id) {
        return;
      }
      persistCartToStorage(userCartStorageKey(auth.contact.id), cart.items);
      localStorage.removeItem(GUEST_CART_STORAGE_KEY);
      localStorage.removeItem(LEGACY_CART_STORAGE_KEY);
      return;
    }

    persistCartToStorage(GUEST_CART_STORAGE_KEY, cart.items);
  });

  return <Slot />;
});

export function useCart() {
  return useContext(CartContext);
}
