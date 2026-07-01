/** Escape special characters per vCard 3.0 (RFC 2426). */
function escapeVCard(value: string): string {
  return value
    .replace(/\\/g, "\\\\")
    .replace(/;/g, "\\;")
    .replace(/,/g, "\\,")
    .replace(/\n/g, "\\n");
}

/** Build a vCard 3.0 payload for name, email, and mobile. */
export function buildCustomerContactVCard(
  name: string,
  email: string | null,
  mobile: string | null,
): string {
  const lines = ["BEGIN:VCARD", "VERSION:3.0"];

  const trimmedName = name.trim();
  if (trimmedName) {
    lines.push(`FN:${escapeVCard(trimmedName)}`);
  }

  const trimmedEmail = email?.trim();
  if (trimmedEmail) {
    lines.push(`EMAIL;TYPE=INTERNET:${escapeVCard(trimmedEmail)}`);
  }

  const trimmedMobile = mobile?.trim();
  if (trimmedMobile) {
    lines.push(`TEL;TYPE=CELL:${escapeVCard(trimmedMobile)}`);
  }

  lines.push("END:VCARD");
  return lines.join("\n");
}
