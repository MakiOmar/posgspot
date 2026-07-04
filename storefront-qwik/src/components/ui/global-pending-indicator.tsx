import { component$, useVisibleTask$ } from "@builder.io/qwik";
import { useLocation } from "@builder.io/qwik-city";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { usePendingState } from "~/lib/pending-context";

/** Top progress bar + click-blocking overlay during navigation and async actions. */
export const GlobalPendingIndicator = component$(() => {
  const loc = useLocation();
  const pending = usePendingState();
  const { locale } = useI18n();
  const isActive = loc.isNavigating || pending.clientCount > 0;

  // Sync body busy state for assistive tech (DOM-only; needs client).
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => loc.isNavigating);
    track(() => pending.clientCount);
    const active = loc.isNavigating || pending.clientCount > 0;
    document.body.classList.toggle("is-globally-pending", active);
    document.body.setAttribute("aria-busy", active ? "true" : "false");
  });

  return (
    <>
      <div
        class={`global-pending-bar${isActive ? " global-pending-bar--active" : ""}`}
        role="status"
        aria-live="polite"
        aria-hidden={!isActive}
      >
        <span class="global-pending-bar__track" aria-hidden="true" />
        <span class="sr-only">
          {isActive ? tStatic(locale, "common.loadingPleaseWait") : ""}
        </span>
      </div>
      <div
        class={`global-pending-overlay${isActive ? " global-pending-overlay--visible" : ""}`}
        aria-hidden="true"
      />
    </>
  );
});
