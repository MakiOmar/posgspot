import { AUTH_STORAGE_KEY } from "~/lib/auth-actions";

/**
 * Class set on `<html>` before first paint when a saved session exists.
 * Auth lives in localStorage (never in SSR), so pages that differ for guests vs
 * signed-in customers render both views and let CSS pick one until auth hydrates
 * (see `[data-auth-view]` in global.css).
 */
export const SESSION_HINT_CLASS = "gs-has-session";

/** Inline head script; must stay tiny and dependency-free. */
export const SESSION_HINT_BOOTSTRAP = `try{if(localStorage.getItem(${JSON.stringify(
  AUTH_STORAGE_KEY,
)}))document.documentElement.classList.add(${JSON.stringify(SESSION_HINT_CLASS)})}catch(e){}`;

export function syncSessionHint(hasSession: boolean): void {
  if (typeof document === "undefined") {
    return;
  }
  document.documentElement.classList.toggle(SESSION_HINT_CLASS, hasSession);
}
