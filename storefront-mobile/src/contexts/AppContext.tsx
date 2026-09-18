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
  ApiError,
} from "../lib/api";
import {
  clearAuthSession,
  contactDisplayName,
  loadAuthSession,
  saveAuthSession,
} from "../lib/auth-storage";
import {
  loadPendingPayment,
  pendingAuthSession,
} from "../lib/pending-payment";
import {
  authenticateBiometric,
  deviceHasBiometrics,
  isBiometricUnlockEnabled,
  setBiometricUnlockEnabled,
} from "../lib/biometric";
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

const SETTINGS_TIMEOUT_MS = 30000;

function sameContact(a: AuthContact | undefined, b: AuthContact): boolean {
  if (!a) return false;
  return (
    a.id === b.id &&
    a.first_name === b.first_name &&
    a.last_name === b.last_name &&
    a.name === b.name &&
    a.email === b.email &&
    a.mobile === b.mobile &&
    a.email_verified === b.email_verified &&
    a.delete_requested === b.delete_requested &&
    a.address_line_1 === b.address_line_1 &&
    a.address_line_2 === b.address_line_2 &&
    a.country === b.country &&
    a.state === b.state &&
    a.city === b.city &&
    a.zip_code === b.zip_code &&
    a.avatar_url === b.avatar_url
  );
}

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
  passkeyEnabled: boolean;
  passkeyCanUnlock: boolean;
  passkeyHardware: boolean;
  enablePasskey: () => Promise<void>;
  disablePasskey: () => Promise<void>;
  unlockWithPasskey: () => Promise<boolean>;
}

const AppContext = createContext<AppContextValue | null>(null);

export function AppProvider({ children }: { children: React.ReactNode }) {
  const [locale, setLocaleState] = useState<ContentLocale>("en");
  const [settings, setSettings] = useState<StoreSettings | null>(null);
  const [loading, setLoading] = useState(true);
  const [session, setSession] = useState<AuthSession | null>(null);
  const [pushToken, setPushToken] = useState<string | null>(null);
  const [passkeyEnabled, setPasskeyEnabled] = useState(false);
  const [passkeyCanUnlock, setPasskeyCanUnlock] = useState(false);
  const [passkeyHardware, setPasskeyHardware] = useState(false);

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
        const storedSecure = await loadAuthSession();
        const pending = await loadPendingPayment();
        const stored = storedSecure ?? pendingAuthSession(pending);
        // Re-seed SecureStore after remount if we only had the pending snapshot.
        if (!storedSecure && stored) {
          await saveAuthSession(stored).catch(() => undefined);
        }
        const bioOn = await isBiometricUnlockEnabled();
        const hardware = await deviceHasBiometrics();
        if (!cancelled) {
          setPasskeyEnabled(bioOn);
          setPasskeyHardware(hardware);
          setPasskeyCanUnlock(!!stored && bioOn);
        }
        // Mid-checkout remount: restore the Sanctum session even when passkey
        // lock is on, so payment resume does not look like a forced logout.
        const resumePayment = !!pending;
        if (stored && (!bioOn || resumePayment) && !cancelled) {
          setSession(stored);
          try {
            const { data } = await fetchProfile(stored.token);
            const next = { ...stored, contact: data };
            await saveAuthSession(next);
            if (!cancelled) {
              setSession(next);
            }
          } catch (e) {
            // Only wipe the session on auth failure — keep token on network/5xx.
            const status = e instanceof ApiError ? e.status : 0;
            if (status === 401 || status === 403) {
              await clearAuthSession();
              if (!cancelled) {
                setSession(null);
              }
            }
          }
        }
        // Don't abandon a slow settings response — mobile → POS can exceed the
        // race window; apply flags when the request eventually finishes.
        const settingsPromise = fetchSettings(locale);
        try {
          const { data } = await withTimeout(settingsPromise, SETTINGS_TIMEOUT_MS);
          if (!cancelled) {
            setSettings(data);
          }
        } catch {
          void settingsPromise
            .then(({ data }) => {
              if (!cancelled) {
                setSettings(data);
              }
            })
            .catch(() => undefined);
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
      void setBiometricUnlockEnabled(false);
      void clearAuthSession();
      setPasskeyEnabled(false);
      setPasskeyCanUnlock(false);
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

  const t = useCallback(
    (key: string, vars?: Record<string, string | number>) =>
      translate(locale, key, vars),
    [locale],
  );

  const applySession = useCallback(async (next: AuthSession) => {
    await saveAuthSession(next);
    setSession(next);
  }, []);

  // Stable updater — skip no-op writes so profile/account screens do not remount.
  const updateContactLocal = useCallback(async (contact: AuthContact) => {
    setSession((prev) => {
      if (!prev?.token) return prev;
      if (sameContact(prev.contact, contact)) return prev;
      const next = { ...prev, contact };
      void saveAuthSession(next);
      return next;
    });
  }, []);

  const refreshContact = useCallback(async () => {
    setSession((prev) => {
      if (!prev?.token) return prev;
      void (async () => {
        try {
          const { data } = await fetchProfile(prev.token);
          const next = { ...prev, contact: data };
          await saveAuthSession(next);
          setSession(next);
        } catch {
          // keep current session
        }
      })();
      return prev;
    });
  }, []);

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
    const keepPasskey =
      passkeyEnabled || (await isBiometricUnlockEnabled());

    if (session?.token && !keepPasskey) {
      await clearPushTokenFromApi(session.token, pushToken);
      try {
        await apiLogout(session.token);
      } catch {
        // ignore
      }
    }

    // Passkey stays on this phone: lock the UI but keep the Sanctum session.
    if (keepPasskey) {
      setSession(null);
      setPushToken(null);
      setPasskeyEnabled(true);
      setPasskeyCanUnlock(true);
      return;
    }

    await setBiometricUnlockEnabled(false);
    await clearAuthSession();
    setPasskeyEnabled(false);
    setPasskeyCanUnlock(false);
    setSession(null);
    setPushToken(null);
  }, [session?.token, pushToken, passkeyEnabled]);

  const enablePasskey = useCallback(async () => {
    const hardware = await deviceHasBiometrics();
    setPasskeyHardware(hardware);
    if (!hardware) {
      throw new Error(translate(locale, "account.passkeyUnavailable"));
    }
    const ok = await authenticateBiometric(translate(locale, "account.passkeyPrompt"));
    if (!ok) {
      throw new Error(translate(locale, "account.passkeyFailed"));
    }
    await setBiometricUnlockEnabled(true);
    setPasskeyEnabled(true);
  }, [locale]);

  const disablePasskey = useCallback(async () => {
    await setBiometricUnlockEnabled(false);
    setPasskeyEnabled(false);
    setPasskeyCanUnlock(false);
  }, []);

  const unlockWithPasskey = useCallback(async () => {
    const ok = await authenticateBiometric(translate(locale, "account.passkeyPrompt"));
    if (!ok) {
      return false;
    }
    const stored = await loadAuthSession();
    if (!stored) {
      setPasskeyCanUnlock(false);
      return false;
    }
    setSession(stored);
    setPasskeyCanUnlock(false);
    try {
      const { data } = await fetchProfile(stored.token);
      const next = { ...stored, contact: data };
      await saveAuthSession(next);
      setSession(next);
    } catch {
      // keep stored session
    }
    return true;
  }, [locale]);

  const accent =
    settings?.theme?.accent_color &&
    /^#[0-9A-Fa-f]{6}$/.test(settings.theme.accent_color)
      ? settings.theme.accent_color
      : "#FF7A00";

  const value = useMemo<AppContextValue>(
    () => ({
      locale,
      setLocale: applyLocale,
      t,
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
      passkeyEnabled,
      passkeyCanUnlock,
      passkeyHardware,
      enablePasskey,
      disablePasskey,
      unlockWithPasskey,
    }),
    [
      locale,
      applyLocale,
      t,
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
      passkeyEnabled,
      passkeyCanUnlock,
      passkeyHardware,
      enablePasskey,
      disablePasskey,
      unlockWithPasskey,
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
