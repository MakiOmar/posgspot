import * as SecureStore from "expo-secure-store";
import type { AuthContact, AuthSession } from "./types";

const AUTH_KEY = "gs-auth-v1";

export async function loadAuthSession(): Promise<AuthSession | null> {
  try {
    const raw = await SecureStore.getItemAsync(AUTH_KEY);
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
  await SecureStore.setItemAsync(AUTH_KEY, JSON.stringify(session));
}

export async function clearAuthSession(): Promise<void> {
  await SecureStore.deleteItemAsync(AUTH_KEY);
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
