import {
  component$,
  createContextId,
  Slot,
  useContext,
  useContextProvider,
  useStore,
} from "@builder.io/qwik";
import { useLocation } from "@builder.io/qwik-city";

/** Tracks in-flight client async work (forms, API actions) — separate from route navigation. */
export interface PendingState {
  clientCount: number;
}

export const PendingContext = createContextId<PendingState>("storefront.pending");

export function beginClientPending(state: PendingState): void {
  state.clientCount += 1;
}

export function endClientPending(state: PendingState): void {
  state.clientCount = Math.max(0, state.clientCount - 1);
}

export function usePendingState(): PendingState {
  return useContext(PendingContext);
}

/** True while a Qwik City navigation or any registered client async action is running. */
export function useIsGloballyPending(): boolean {
  const loc = useLocation();
  const pending = usePendingState();
  return loc.isNavigating || pending.clientCount > 0;
}

export const PendingProvider = component$(() => {
  const state = useStore<PendingState>({ clientCount: 0 });
  useContextProvider(PendingContext, state);
  return <Slot />;
});
