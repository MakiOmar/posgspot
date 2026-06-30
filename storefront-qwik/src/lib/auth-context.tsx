import {
  component$,
  createContextId,
  Slot,
  useContext,
  useContextProvider,
  useStore,
  useVisibleTask$,
} from "@builder.io/qwik";
import {
  AUTH_STORAGE_KEY,
  type AuthState,
  type PersistedAuth,
} from "~/lib/auth-actions";

export const AuthContext = createContextId<AuthState>("games-spot.auth");

export const AuthProvider = component$(() => {
  const auth = useStore<AuthState>({ token: null, contact: null, ready: false });

  useContextProvider(AuthContext, auth);

  // Hydrate session from localStorage on the client only (tokens never touch SSR).
  useVisibleTask$(() => {
    try {
      const raw = localStorage.getItem(AUTH_STORAGE_KEY);
      if (raw) {
        const parsed = JSON.parse(raw) as PersistedAuth;
        if (parsed && typeof parsed.token === "string" && parsed.contact) {
          auth.token = parsed.token;
          auth.contact = parsed.contact;
        }
      }
    } catch {
      // Corrupt storage: start signed out.
    } finally {
      auth.ready = true;
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
