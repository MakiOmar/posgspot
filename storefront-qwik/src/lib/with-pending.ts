import type { Signal } from "@builder.io/qwik";
import {
  beginClientPending,
  endClientPending,
  type PendingState,
} from "~/lib/pending-context";

/**
 * Wraps async user-initiated work with global + optional local busy state.
 * Use for form submits, logout, add-to-cart, and similar actions.
 */
export async function withPendingFeedback(
  pending: PendingState,
  localBusy: Signal<boolean> | null,
  action: () => Promise<void>,
): Promise<void> {
  if (localBusy?.value) {
    return;
  }

  if (localBusy) {
    localBusy.value = true;
  }
  beginClientPending(pending);

  try {
    await action();
  } finally {
    if (localBusy) {
      localBusy.value = false;
    }
    endClientPending(pending);
  }
}
