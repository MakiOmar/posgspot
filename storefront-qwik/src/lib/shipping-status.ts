import type { StoreLocaleCode } from "~/lib/i18n/config";
import { translate } from "~/lib/i18n/translate";

/** Localized POS shipping status (ordered, preparing, packed, …); unknown values are shown as sent by the API. */
export function shippingStatusLabel(locale: StoreLocaleCode, status: string | null | undefined): string {
  if (!status) return "";
  const key = `shippingStatus.${status}`;
  const label = translate(locale, key);
  return label === key ? status : label;
}
