import {
  component$,
  createContextId,
  Slot,
  useContext,
  useContextProvider,
  useStore,
} from "@builder.io/qwik";

/** Exclusive header overlays — opening one closes the others. */
export type HeaderDropdownId = "search" | "lang" | "cart" | "nav" | "categories" | null;

export interface HeaderDropdownState {
  openId: HeaderDropdownId;
}

export const HeaderDropdownContext = createContextId<HeaderDropdownState>(
  "storefront.header-dropdown",
);

export function useHeaderDropdown(): HeaderDropdownState {
  return useContext(HeaderDropdownContext);
}

/** Open this dropdown (or close it if it is already the active one). */
export function toggleHeaderDropdown(
  state: HeaderDropdownState,
  id: Exclude<HeaderDropdownId, null>,
): void {
  state.openId = state.openId === id ? null : id;
}

/** Close this dropdown only when it is the one currently open. */
export function closeHeaderDropdown(
  state: HeaderDropdownState,
  id: Exclude<HeaderDropdownId, null>,
): void {
  if (state.openId === id) {
    state.openId = null;
  }
}

export const HeaderDropdownProvider = component$(() => {
  const state = useStore<HeaderDropdownState>({ openId: null });
  useContextProvider(HeaderDropdownContext, state);
  return <Slot />;
});

