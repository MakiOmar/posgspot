import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
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
  t: (key: string) => string;
  settings: StoreSettings | null;
  accent: string;
  loading: boolean;
  token: string | null;
  contact: AuthContact | null;
  displayName: string;
  refreshSettings: () => Promise<void>;
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
            await fetchProfile(stored.token);
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
  }, []);

  useEffect(() => {
    setUnauthorizedHandler(() => {
      void clearAuthSession();
      setSession(null);
    });
    return () => setUnauthorizedHandler(null);
  }, []);

  useEffect(() => {
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
      : "#0B6E4F";

  const value = useMemo<AppContextValue>(
    () => ({
      locale,
      setLocale: applyLocale,
      t: (key: string) => translate(locale, key),
      settings,
      accent,
      loading,
      token: session?.token ?? null,
      contact: session?.contact ?? null,
      displayName: contactDisplayName(session?.contact),
      refreshSettings,
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
