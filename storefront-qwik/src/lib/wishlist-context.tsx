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
import { getActiveContentLocale } from "~/lib/api";
import {
  GUEST_WISHLIST_STORAGE_KEY,
  loadGuestWishlistFromStorage,
  mergeWishlistItems,
  parseStoredWishlist,
  persistWishlistToStorage,
  syncWishlistForUser,
  userWishlistStorageKey,
  type WishlistState,
} from "~/lib/wishlist-actions";

export const WishlistContext = createContextId<WishlistState>("games-spot.wishlist");

export const WishlistProvider = component$(() => {
  const auth = useAuth();
  const wishlist = useStore<WishlistState>({
    items: [],
    mergedForContactId: null,
    hydrated: false,
  });

  useContextProvider(WishlistContext, wishlist);

  // Hydrate wishlist from localStorage on the client only.
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
      const guestItems = loadGuestWishlistFromStorage();
      const userItems = parseStoredWishlist(localStorage.getItem(userWishlistStorageKey(contactId)));
      wishlist.items = mergeWishlistItems(guestItems, userItems);
      wishlist.mergedForContactId = contactId;
    } else {
      wishlist.items = loadGuestWishlistFromStorage();
    }

    wishlist.hydrated = true;
  });

  // When auth becomes ready, merge guest wishlist with the customer's saved list.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => auth.ready);
    track(() => auth.token);
    track(() => auth.contact?.id);
    track(() => wishlist.hydrated);

    if (!auth.ready || !wishlist.hydrated) {
      return;
    }

    if (auth.token && auth.contact?.id) {
      if (wishlist.mergedForContactId === auth.contact.id) {
        return;
      }
      void syncWishlistForUser(wishlist, auth.token, auth.contact.id, getActiveContentLocale());
      return;
    }

    wishlist.mergedForContactId = null;
  });

  // Persist wishlist to guest or user storage whenever items or auth change.
  useVisibleTask$(({ track }) => {
    track(() => wishlist.items);
    track(() => auth.token);
    track(() => auth.contact?.id);
    track(() => auth.ready);
    track(() => wishlist.hydrated);
    track(() => wishlist.mergedForContactId);

    if (!auth.ready || !wishlist.hydrated) {
      return;
    }

    if (auth.token && auth.contact?.id) {
      if (wishlist.mergedForContactId !== auth.contact.id) {
        return;
      }
      persistWishlistToStorage(userWishlistStorageKey(auth.contact.id), wishlist.items);
      localStorage.removeItem(GUEST_WISHLIST_STORAGE_KEY);
      return;
    }

    persistWishlistToStorage(GUEST_WISHLIST_STORAGE_KEY, wishlist.items);
  });

  return <Slot />;
});

export function useWishlist() {
  return useContext(WishlistContext);
}
