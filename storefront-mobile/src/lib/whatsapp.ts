/**
 * Digits-only WhatsApp destination → safe wa.me URL (no free-form schemes).
 */
export function normalizeWhatsAppDigits(raw: string | null | undefined): string | null {
  const digits = String(raw || "").replace(/\D+/g, "");
  if (!/^[0-9]{8,15}$/.test(digits)) {
    return null;
  }
  return digits;
}

export function buildWhatsAppUrl(
  rawDigits: string | null | undefined,
  message: string,
): string | null {
  const digits = normalizeWhatsAppDigits(rawDigits);
  if (!digits) {
    return null;
  }
  return `https://wa.me/${digits}?text=${encodeURIComponent(message)}`;
}
