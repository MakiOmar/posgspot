import * as SecureStore from "expo-secure-store";
import AsyncStorage from "@react-native-async-storage/async-storage";
import type { AuthContact, AuthSession } from "./types";

const AUTH_KEY = "gs-auth-v1";

const SECURE_OPTS: SecureStore.SecureStoreOptions = {
  keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
};

/** Always wipe both stores so a SecureStore write cannot leave a stale AsyncStorage copy. */
async function clearBothStores(): Promise<void> {
  try {
    await SecureStore.deleteItemAsync(AUTH_KEY, SECURE_OPTS);
  } catch {
    // ignore
  }
  try {
    await AsyncStorage.removeItem(AUTH_KEY);
  } catch {
    // ignore
  }
}

async function readRaw(): Promise<string | null> {
  try {
    const secure = await SecureStore.getItemAsync(AUTH_KEY, SECURE_OPTS);
    if (secure) {
      // Migrate away from any leftover plaintext copy.
      void AsyncStorage.removeItem(AUTH_KEY).catch(() => undefined);
      return secure;
    }
  } catch {
    // SecureStore unavailable on this platform/build.
  }

  // Dev-only plaintext fallback (never write plaintext in production).
  if (__DEV__) {
    try {
      const legacy = await AsyncStorage.getItem(AUTH_KEY);
      if (legacy) {
        try {
          await SecureStore.setItemAsync(AUTH_KEY, legacy, SECURE_OPTS);
          await AsyncStorage.removeItem(AUTH_KEY);
        } catch {
          // keep AsyncStorage read for this session in dev
        }
        return legacy;
      }
    } catch {
      return null;
    }
  }

  return null;
}

async function writeRaw(value: string): Promise<void> {
  try {
    await SecureStore.setItemAsync(AUTH_KEY, value, SECURE_OPTS);
    // Ensure no leftover plaintext token survives.
    await AsyncStorage.removeItem(AUTH_KEY).catch(() => undefined);
  } catch (e) {
    if (__DEV__) {
      await AsyncStorage.setItem(AUTH_KEY, value);
      return;
    }
    throw e instanceof Error
      ? e
      : new Error("Unable to store auth session securely.");
  }
}

export async function loadAuthSession(): Promise<AuthSession | null> {
  try {
    const raw = await readRaw();
    if (!raw) {
      return null;
    }
    const parsed = JSON.parse(raw) as AuthSession;
    if (!parsed?.token || !parsed?.contact?.id) {
      await clearBothStores();
      return null;
    }
    return parsed;
  } catch {
    return null;
  }
}

export async function saveAuthSession(session: AuthSession): Promise<void> {
  await writeRaw(JSON.stringify(session));
}

export async function clearAuthSession(): Promise<void> {
  await clearBothStores();
}

export function contactDisplayName(contact: AuthContact | null | undefined): string {
  if (!contact) {
    return "";
  }
  const name = (contact.name || "").trim();
  if (name) {
    return name;
  }
  return [contact.first_name, contact.last_name].filter(Boolean).join(" ").trim();
}
