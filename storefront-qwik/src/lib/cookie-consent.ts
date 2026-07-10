/** Cookie / privacy consent choice persisted in the browser. */

export const COOKIE_CONSENT_STORAGE_KEY = "gs-cookie-consent-v1";

export type CookieConsentStatus = "accepted" | "necessary";

export interface CookieConsentRecord {
  status: CookieConsentStatus;
  /** ISO timestamp when the choice was saved. */
  at: string;
}

const isConsentStatus = (value: unknown): value is CookieConsentStatus =>
  value === "accepted" || value === "necessary";

export const parseCookieConsent = (raw: string | null): CookieConsentRecord | null => {
  if (!raw) {
    return null;
  }
  try {
    const parsed = JSON.parse(raw) as unknown;
    if (
      typeof parsed === "object" &&
      parsed !== null &&
      isConsentStatus((parsed as CookieConsentRecord).status)
    ) {
      return {
        status: (parsed as CookieConsentRecord).status,
        at:
          typeof (parsed as CookieConsentRecord).at === "string"
            ? (parsed as CookieConsentRecord).at
            : new Date().toISOString(),
      };
    }
  } catch {
    // Corrupt storage — treat as no choice.
  }
  return null;
};

export const loadCookieConsent = (): CookieConsentRecord | null => {
  if (typeof localStorage === "undefined") {
    return null;
  }
  return parseCookieConsent(localStorage.getItem(COOKIE_CONSENT_STORAGE_KEY));
};

export const persistCookieConsent = (status: CookieConsentStatus): CookieConsentRecord => {
  const record: CookieConsentRecord = {
    status,
    at: new Date().toISOString(),
  };
  if (typeof localStorage !== "undefined") {
    localStorage.setItem(COOKIE_CONSENT_STORAGE_KEY, JSON.stringify(record));
  }
  return record;
};

/** True when the shopper accepted non-essential cookies (analytics, marketing). */
export const hasAnalyticsConsent = (): boolean => loadCookieConsent()?.status === "accepted";
