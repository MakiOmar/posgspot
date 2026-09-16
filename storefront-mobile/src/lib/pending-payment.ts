/**
 * Survives MainActivity / JS remounts during hosted or native checkout.
 * Order ids are not secrets; the optional auth snapshot is a short-lived
 * remount recovery copy (cleared when payment ends).
 */
import AsyncStorage from "@react-native-async-storage/async-storage";
import type { AuthContact, AuthSession } from "./types";

const KEY = "gs-pending-payment-v1";
const MAX_AGE_MS = 30 * 60 * 1000;

export type PendingPayment = {
  storefrontOrderId: string;
  orderId: string;
  provider: string;
  startedAt: number;
  /** Short-lived Sanctum resume if SecureStore is briefly unavailable after remount. */
  authToken?: string;
  authContact?: AuthContact;
};

export async function savePendingPayment(
  pending: Omit<PendingPayment, "startedAt"> & { startedAt?: number },
): Promise<void> {
  const value: PendingPayment = {
    storefrontOrderId: pending.storefrontOrderId,
    orderId: pending.orderId,
    provider: pending.provider,
    startedAt: pending.startedAt ?? Date.now(),
    ...(pending.authToken && pending.authContact
      ? { authToken: pending.authToken, authContact: pending.authContact }
      : {}),
  };
  await AsyncStorage.setItem(KEY, JSON.stringify(value));
}

export async function loadPendingPayment(): Promise<PendingPayment | null> {
  try {
    const raw = await AsyncStorage.getItem(KEY);
    if (!raw) {
      return null;
    }
    const parsed = JSON.parse(raw) as PendingPayment;
    if (
      !parsed?.storefrontOrderId ||
      !parsed?.orderId ||
      !parsed?.provider ||
      !parsed?.startedAt
    ) {
      await clearPendingPayment();
      return null;
    }
    if (Date.now() - parsed.startedAt > MAX_AGE_MS) {
      await clearPendingPayment();
      return null;
    }
    return parsed;
  } catch {
    return null;
  }
}

export async function clearPendingPayment(): Promise<void> {
  try {
    await AsyncStorage.removeItem(KEY);
  } catch {
    // ignore
  }
}

export async function hasPendingPayment(): Promise<boolean> {
  return (await loadPendingPayment()) !== null;
}

/** Rebuild an AuthSession from the pending snapshot when SecureStore is empty. */
export function pendingAuthSession(
  pending: PendingPayment | null,
): AuthSession | null {
  if (!pending?.authToken || !pending?.authContact?.id) {
    return null;
  }
  return { token: pending.authToken, contact: pending.authContact };
}
