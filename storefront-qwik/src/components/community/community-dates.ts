import { tStatic } from "~/lib/i18n/context";
import type { StoreLocaleCode } from "~/lib/i18n/config";

/** Format an ISO date for community cards / detail. */
export function formatCommunityWhen(
  value: string | null | undefined,
  locale: StoreLocaleCode,
): string {
  if (!value) return "";
  const d = new Date(value);
  return Number.isNaN(d.getTime())
    ? value
    : d.toLocaleDateString(locale === "ar" ? "ar-EG" : "en-EG", {
        year: "numeric",
        month: "short",
        day: "numeric",
      });
}

/** Format start–end range when both (or one) are present. */
export function formatCommunityRange(
  startsAt: string | null | undefined,
  endsAt: string | null | undefined,
  locale: StoreLocaleCode,
): string {
  const start = formatCommunityWhen(startsAt, locale);
  const end = formatCommunityWhen(endsAt, locale);
  if (start && end) {
    return tStatic(locale, "community.dateRange").replace("{start}", start).replace("{end}", end);
  }
  return start || end;
}
