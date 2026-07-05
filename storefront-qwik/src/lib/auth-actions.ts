import type { AuthContact } from "~/lib/types";

/** Serializable auth state held in the auth context. */
export interface AuthState {
  /** Bearer token for API calls; null when signed out. */
  token: string | null;
  /** Cached customer profile; null when signed out or not yet loaded. */
  contact: AuthContact | null;
  /** True once the client has attempted to hydrate from localStorage. */
  ready: boolean;
}

export const AUTH_STORAGE_KEY = "gs-auth-v1";

/** Dispatched when an authenticated API call returns 401 (expired or revoked token). */
export const AUTH_SESSION_EXPIRED_EVENT = "gs-auth-session-expired";

export function dispatchAuthSessionExpired(): void {
  if (typeof window === "undefined") {
    return;
  }
  window.dispatchEvent(new CustomEvent(AUTH_SESSION_EXPIRED_EVENT));
}

export const isAuthenticated = (auth: AuthState): boolean => !!auth.token;

/** Display label for the header/account nav. */
export const accountDisplayName = (auth: AuthState): string => {
  const c = auth.contact;
  if (!c) {
    return "Account";
  }
  return (c.first_name || c.name || c.email || "Account").trim();
};

/** Shape persisted to localStorage (token + contact only). */
export interface PersistedAuth {
  token: string;
  contact: AuthContact;
}
