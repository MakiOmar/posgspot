import type { PaymentSession } from "~/lib/types";

const STORAGE_KEY = "storefront_payment_session";
const ACCESS_KEY = "storefront_order_access";

export function storePaymentSession(session: PaymentSession): void {
  if (typeof sessionStorage === "undefined") {
    return;
  }
  sessionStorage.setItem(STORAGE_KEY, JSON.stringify(session));
}

export function storeOrderAccessToken(orderId: string, token: string): void {
  if (typeof sessionStorage === "undefined" || !orderId || !token) {
    return;
  }
  sessionStorage.setItem(ACCESS_KEY, JSON.stringify({ orderId, token }));
}

export function readOrderAccessToken(orderId: string): string | null {
  if (typeof sessionStorage === "undefined") {
    return null;
  }
  const raw = sessionStorage.getItem(ACCESS_KEY);
  if (!raw) {
    return null;
  }
  try {
    const parsed = JSON.parse(raw) as { orderId?: string; token?: string };
    if (parsed.orderId === orderId && typeof parsed.token === "string" && parsed.token) {
      return parsed.token;
    }
  } catch {
    // ignore
  }
  return null;
}

export function readPaymentSession(): PaymentSession | null {
  if (typeof sessionStorage === "undefined") {
    return null;
  }
  const raw = sessionStorage.getItem(STORAGE_KEY);
  if (!raw) {
    return null;
  }
  try {
    return JSON.parse(raw) as PaymentSession;
  } catch {
    return null;
  }
}

export function clearPaymentSession(): void {
  if (typeof sessionStorage === "undefined") {
    return;
  }
  sessionStorage.removeItem(STORAGE_KEY);
  sessionStorage.removeItem(ACCESS_KEY);
}

export function sessionMatchesOrder(session: PaymentSession | null, orderId: string): boolean {
  if (!session) {
    return false;
  }
  if (session.provider === "fawry") {
    return session.charge.merchantRefNum === orderId;
  }
  if (session.provider === "geidea") {
    return session.merchant_reference_id === orderId || session.return_url.includes(orderId);
  }
  return false;
}
