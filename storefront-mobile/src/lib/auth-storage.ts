import * as SecureStore from "expo-secure-store";
import AsyncStorage from "@react-native-async-storage/async-storage";
import type { AuthContact, AuthSession } from "./types";

const AUTH_KEY = "gs-auth-v1";

async function readRaw(): Promise<string | null> {
  try {
    return await SecureStore.getItemAsync(AUTH_KEY);
  } catch {
    return AsyncStorage.getItem(AUTH_KEY);
  }
}

async function writeRaw(value: string): Promise<void> {
  try {
    await SecureStore.setItemAsync(AUTH_KEY, value);
  } catch {
    await AsyncStorage.setItem(AUTH_KEY, value);
  }
}

async function deleteRaw(): Promise<void> {
  try {
    await SecureStore.deleteItemAsync(AUTH_KEY);
  } catch {
    await AsyncStorage.removeItem(AUTH_KEY);
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
  await deleteRaw();
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
