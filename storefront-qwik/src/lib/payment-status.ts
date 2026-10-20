import type { StoreLocaleCode } from "~/lib/i18n/config";
import { translate } from "~/lib/i18n/translate";

export type PaymentStatusTone = "paid" | "due" | "partial" | "pending" | "failed";

const KNOWN_TONES: readonly PaymentStatusTone[] = ["paid", "due", "partial", "pending", "failed"];

/** Normalizes POS / gateway payment statuses; unknown or empty values are treated as unpaid. */
export function paymentStatusTone(status: string | null | undefined): PaymentStatusTone {
  const normalized = (status ?? "").trim().toLowerCase();
  return (KNOWN_TONES as readonly string[]).includes(normalized) ? (normalized as PaymentStatusTone) : "due";
}

export function paymentStatusLabel(locale: StoreLocaleCode, status: string | null | undefined): string {
  return translate(locale, `paymentStatus.${paymentStatusTone(status)}`);
}

export function isPaidStatus(status: string | null | undefined): boolean {
  return paymentStatusTone(status) === "paid";
}
