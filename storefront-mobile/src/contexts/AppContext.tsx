import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import { I18nManager } from "react-native";
import {
  fetchProfile,
  fetchSettings,
  login as apiLogin,
  logout as apiLogout,
  register as apiRegister,
  setActiveContentLocale,
  setUnauthorizedHandler,
} from "../lib/api";
import {
  clearAuthSession,
  contactDisplayName,
  loadAuthSession,
  saveAuthSession,
} from "../lib/auth-storage";
import { t as translate } from "../lib/i18n";
import {
  clearPushTokenFromApi,
  syncPushTokenWithApi,
} from "../lib/push";
import type {
  AuthContact,
  AuthSession,
  ContentLocale,
  StoreSettings,
} from "../lib/types";

const SETTINGS_TIMEOUT_MS = 12000;

async function withTimeout<T>(promise: Promise<T>, ms: number): Promise<T> {
  let timer: ReturnType<typeof setTimeout> | undefined;
  try {
    return await Promise.race([
      promise,
      new Promise<T>((_, reject) => {
        timer = setTimeout(() => reject(new Error("timeout")), ms);
      }),
    ]);
  } finally {
    if (timer) {
      clearTimeout(timer);
    }
  }
}

interface AppContextValue {
  locale: ContentLocale;
  setLocale: (locale: ContentLocale) => void;
  t: (key: string, vars?: Record<string, string | number>) => string;
  settings: StoreSettings | null;
  accent: string;
  loading: boolean;
  token: string | null;
  contact: AuthContact | null;
  displayName: string;
  refreshSettings: () => Promise<void>;
  refreshContact: () => Promise<void>;
  applySession: (session: AuthSession) => Promise<void>;
  updateContactLocal: (contact: AuthContact) => Promise<void>;
  signIn: (loginId: string, password: string) => Promise<void>;
  signUp: (body: Record<string, unknown>) => Promise<void>;
  signOut: () => Promise<void>;
}

const AppContext = createContext<AppContextValue | null>(null);

export function AppProvider({ children }: { children: React.ReactNode }) {
  const [locale, setLocaleState] = useState<ContentLocale>("en");
  const [settings, setSettings] = useState<StoreSettings | null>(null);
  const [loading, setLoading] = useState(true);
  const [session, setSession] = useState<AuthSession | null>(null);
  const [pushToken, setPushToken] = useState<string | null>(null);

  const applyLocale = useCallback((next: ContentLocale) => {
    setLocaleState(next);
    setActiveContentLocale(next);
    const rtl = next === "ar";
    if (I18nManager.isRTL !== rtl) {
      I18nManager.allowRTL(rtl);
      I18nManager.forceRTL(rtl);
      // Full RTL flip usually needs an app reload; layout still flips textAlign in screens.
    }
  }, []);

  const refreshSettings = useCallback(async () => {
    const { data } = await fetchSettings(locale);
    setSettings(data);
  }, [locale]);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const stored = await loadAuthSession();
        if (stored && !cancelled) {
          setSession(stored);
          try {
            const { data } = await fetchProfile(stored.token);
            const next = { ...stored, contact: data };
            await saveAuthSession(next);
            if (!cancelled) {
              setSession(next);
            }
          } catch {
            await clearAuthSession();
            if (!cancelled) {
              setSession(null);
            }
          }
        }
        const { data } = await withTimeout(
          fetchSettings(locale),
          SETTINGS_TIMEOUT_MS,
        );
        if (!cancelled) {
          setSettings(data);
        }
      } catch {
        // offline / API down / timeout — still show the app shell
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    })();
    return () => {
      cancelled = true;
    };
    // Initial bootstrap only — locale changes handled below.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    setUnauthorizedHandler(() => {
      void clearAuthSession();
      setSession(null);
    });
    return () => setUnauthorizedHandler(null);
  }, []);

  // Refresh settings when locale changes after bootstrap (skip duplicate cold-start fetch).
  const bootstrapped = useRef(false);
  useEffect(() => {
    if (!bootstrapped.current) {
      bootstrapped.current = true;
      return;
    }
    void refreshSettings().catch(() => undefined);
  }, [locale, refreshSettings]);

  useEffect(() => {
    if (!session?.token) {
      return;
    }
    void syncPushTokenWithApi(session.token, locale)
      .then((token) => setPushToken(token))
      .catch(() => undefined);
  }, [session?.token, locale]);

  const applySession = useCallback(async (next: AuthSession) => {
    await saveAuthSession(next);
    setSession(next);
  }, []);

  const updateContactLocal = useCallback(
    async (contact: AuthContact) => {
      if (!session?.token) return;
      const next = { ...session, contact };
      await saveAuthSession(next);
      setSession(next);
    },
    [session],
  );

  const refreshContact = useCallback(async () => {
    if (!session?.token) return;
    const { data } = await fetchProfile(session.token);
    const next = { ...session, contact: data };
    await saveAuthSession(next);
    setSession(next);
  }, [session]);

  const signIn = useCallback(async (loginId: string, password: string) => {
    const { data } = await apiLogin(loginId, password);
    await saveAuthSession(data);
    setSession(data);
  }, []);

  const signUp = useCallback(async (body: Record<string, unknown>) => {
    const { data } = await apiRegister(body);
    await saveAuthSession(data);
    setSession(data);
  }, []);

  const signOut = useCallback(async () => {
    if (session?.token) {
      await clearPushTokenFromApi(session.token, pushToken);
      try {
        await apiLogout(session.token);
      } catch {
        // ignore
      }
    }
    await clearAuthSession();
    setSession(null);
    setPushToken(null);
  }, [session?.token, pushToken]);

  const accent =
    settings?.theme?.accent_color &&
    /^#[0-9A-Fa-f]{6}$/.test(settings.theme.accent_color)
      ? settings.theme.accent_color
      : "#FF7A00";

  const value = useMemo<AppContextValue>(
    () => ({
      locale,
      setLocale: applyLocale,
      t: (key: string, vars?: Record<string, string | number>) =>
        translate(locale, key, vars),
      settings,
      accent,
      loading,
      token: session?.token ?? null,
      contact: session?.contact ?? null,
      displayName: contactDisplayName(session?.contact),
      refreshSettings,
      refreshContact,
      applySession,
      updateContactLocal,
      signIn,
      signUp,
      signOut,
    }),
    [
      locale,
      applyLocale,
      settings,
      accent,
      loading,
      session,
      refreshSettings,
      refreshContact,
      applySession,
      updateContactLocal,
      signIn,
      signUp,
      signOut,
    ],
  );

  return <AppContext.Provider value={value}>{children}</AppContext.Provider>;
}

export function useApp(): AppContextValue {
  const ctx = useContext(AppContext);
  if (!ctx) {
    throw new Error("useApp must be used within AppProvider");
  }
  return ctx;
}
