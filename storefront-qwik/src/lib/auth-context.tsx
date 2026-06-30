import {
  component$,
  createContextId,
  Slot,
  useContext,
  useContextProvider,
  useStore,
  useVisibleTask$,
} from "@builder.io/qwik";
import { ApiError, fetchProfile } from "~/lib/api";
import {
  AUTH_STORAGE_KEY,
  type AuthState,
  type PersistedAuth,
} from "~/lib/auth-actions";

export const AuthContext = createContextId<AuthState>("games-spot.auth");

export const AuthProvider = component$(() => {
  const auth = useStore<AuthState>({ token: null, contact: null, ready: false });

  useContextProvider(AuthContext, auth);

  // Hydrate session from localStorage on the client only (tokens never touch
  // SSR), then validate it against the server so revoked/deleted customers are
  // logged out instead of appearing signed in from stale local data.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async () => {
    let token: string | null = null;
    try {
      const raw = localStorage.getItem(AUTH_STORAGE_KEY);
      if (raw) {
        const parsed = JSON.parse(raw) as PersistedAuth;
        if (parsed && typeof parsed.token === "string" && parsed.contact) {
          auth.token = parsed.token;
          auth.contact = parsed.contact;
          token = parsed.token;
        }
      }
    } catch {
      // Corrupt storage: start signed out.
    } finally {
      // Mark ready optimistically so the UI can render the cached identity
      // while we confirm it in the background.
      auth.ready = true;
    }

    if (!token) {
      return;
    }

    try {
      // A deleted/disabled customer or revoked token returns 401/403.
      const { data } = await fetchProfile(token);
      auth.contact = data;
    } catch (e) {
      if (e instanceof ApiError && (e.status === 401 || e.status === 403)) {
        auth.token = null;
        auth.contact = null;
      }
      // Network/other errors: keep the optimistic session for offline tolerance.
    }
  });

  // Persist (or clear) the session whenever token/contact change.
  useVisibleTask$(({ track }) => {
    track(() => auth.token);
    track(() => auth.contact);

    if (auth.token && auth.contact) {
      const payload: PersistedAuth = { token: auth.token, contact: auth.contact };
      localStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify(payload));
    } else if (auth.ready) {
      localStorage.removeItem(AUTH_STORAGE_KEY);
    }
  });

  return <Slot />;
});

export function useAuth() {
  return useContext(AuthContext);
}
