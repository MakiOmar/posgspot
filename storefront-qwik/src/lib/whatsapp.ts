/**
 * Build a safe wa.me deep link. Rejects non-digit / wrong-length numbers.
 * Never accepts free-form URLs from settings.
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
  const text = encodeURIComponent(message);
  return `https://wa.me/${digits}?text=${text}`;
}
