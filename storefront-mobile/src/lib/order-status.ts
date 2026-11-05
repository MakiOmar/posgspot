import { t } from "./i18n";
import type { ContentLocale } from "./types";

export type PaymentStatusTone = "paid" | "due" | "partial" | "pending" | "failed";

const KNOWN_TONES: readonly PaymentStatusTone[] = ["paid", "due", "partial", "pending", "failed"];

/** Normalizes POS / gateway payment statuses; unknown or empty values are treated as unpaid. */
export function paymentStatusTone(status: string | null | undefined): PaymentStatusTone {
  const normalized = (status ?? "").trim().toLowerCase();
  return (KNOWN_TONES as readonly string[]).includes(normalized)
    ? (normalized as PaymentStatusTone)
    : "due";
}

export function paymentStatusLabel(locale: ContentLocale, status: string | null | undefined): string {
  return t(locale, `paymentStatus.${paymentStatusTone(status)}`);
}

export function isPaidStatus(status: string | null | undefined): boolean {
  return paymentStatusTone(status) === "paid";
}

/** Localized POS shipping status; unknown values are shown as sent by the API. */
export function shippingStatusLabel(locale: ContentLocale, status: string | null | undefined): string {
  if (!status) return "";
  const key = `shippingStatus.${status.trim().toLowerCase()}`;
  const label = t(locale, key);
  return label === key ? status : label;
}

/** Prefer the API flag; older payloads fall back to the unpaid statuses the gateway accepts. */
export function canPayOrderOnline(order: {
  can_pay_online?: boolean;
  payment_status?: string | null;
  storefront_order_id?: string | null;
}): boolean {
  if (!order.storefront_order_id) return false;
  if (typeof order.can_pay_online === "boolean") return order.can_pay_online;
  const tone = (order.payment_status ?? "").trim().toLowerCase();
  return tone === "due" || tone === "failed";
}

export const PAYMENT_BADGE_COLORS: Record<PaymentStatusTone, { backgroundColor: string; color: string }> = {
  paid: { backgroundColor: "#E8F5E9", color: "#1B5E20" },
  pending: { backgroundColor: "#FFF8E1", color: "#F57F17" },
  due: { backgroundColor: "#FFF3E0", color: "#E65100" },
  partial: { backgroundColor: "#E3F2FD", color: "#0D47A1" },
  failed: { backgroundColor: "#FFEBEE", color: "#B71C1C" },
};
