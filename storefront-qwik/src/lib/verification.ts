import type { AuthContact } from "~/lib/types";

/** Signed-in customers must verify email before placing an order (phone OTP later). */
export function needsEmailVerification(
  contact?: Pick<AuthContact, "email_verified"> | null,
): boolean {
  return !!contact && contact.email_verified !== true;
}
