/**
 * Survives MainActivity / JS remounts during hosted or native checkout.
 * Stores only order ids + order access token — never Sanctum credentials.
 */
import AsyncStorage from "@react-native-async-storage/async-storage";

const KEY = "gs-pending-payment-v1";
const MAX_AGE_MS = 30 * 60 * 1000;

export type PendingPayment = {
  storefrontOrderId: string;
  orderId: string;
  provider: string;
  startedAt: number;
  /** Required for payment session/return APIs. */
  orderAccessToken?: string;
};

export async function savePendingPayment(
  pending: Omit<PendingPayment, "startedAt"> & { startedAt?: number },
): Promise<void> {
  const value: PendingPayment = {
    storefrontOrderId: pending.storefrontOrderId,
    orderId: pending.orderId,
    provider: pending.provider,
    startedAt: pending.startedAt ?? Date.now(),
    ...(pending.orderAccessToken ? { orderAccessToken: pending.orderAccessToken } : {}),
  };
  await AsyncStorage.setItem(KEY, JSON.stringify(value));
}

export async function loadPendingPayment(): Promise<PendingPayment | null> {
  try {
    const raw = await AsyncStorage.getItem(KEY);
    if (!raw) {
      return null;
    }
    const parsed = JSON.parse(raw) as PendingPayment & {
      authToken?: string;
      authContact?: unknown;
    };
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
    // Strip legacy auth fields if present in older blobs.
    return {
      storefrontOrderId: parsed.storefrontOrderId,
      orderId: parsed.orderId,
      provider: parsed.provider,
      startedAt: parsed.startedAt,
      ...(parsed.orderAccessToken ? { orderAccessToken: parsed.orderAccessToken } : {}),
    };
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
