import type { StoreLocaleCode } from "./i18n/config";
import { translate } from "./i18n/translate";

export type PhoneCountry = {
  name_en: string;
  name_ar: string;
  dial_code: string;
  flag: string;
  country_code: string;
  validation_pattern: string;
};

export type GeoCountry = { code: string; name: string };
export type GeoState = { code: string; name: string };

const PHONE_EXAMPLES: Record<string, string> = {
  "+20": "Example: +201012345678 (must start with 10, 11, 12, 15, or 16)",
  "+966": "Example: +966501234567 (must start with 5)",
  "+971": "Example: +971501234567 (must start with 5)",
  "+1": "Example: +11234567890 (10 digits after country code)",
  "+44": "Example: +441234567890 (10-11 digits after country code)",
  "+91": "Example: +919012345678 (must start with 6-9)",
  "+33": "Example: +33612345678 (9 digits after country code)",
  "+49": "Example: +4915012345678 (10-12 digits after country code)",
  "+81": "Example: +819012345678 (10-11 digits after country code)",
  "+86": "Example: +8613012345678 (must start with 1)",
  "+962": "Example: +962791234567 (must start with 7)",
  "+964": "Example: +964790123456 (must start with 7)",
  "+961": "Example: +9613123456 (must start with 3, 7, 8, or 9)",
  "+212": "Example: +212612345678 (must start with 6 or 7)",
  "+974": "Example: +97431234567 (must start with 3, 5, 6, or 7)",
  "+968": "Example: +96871234567 (must start with 7 or 9)",
  "+965": "Example: +96551234567 (must start with 5, 6, or 9)",
  "+973": "Example: +97331234567 (must start with 3 or 6)",
  "+970": "Example: +970591234567 (must start with 5)",
};

export function findByDialCode(
  countries: PhoneCountry[],
  dialCode: string,
): PhoneCountry | undefined {
  return countries.find((c) => c.dial_code === dialCode);
}

export function sanitizeNationalNumber(input: string): string {
  return input.replace(/\D/g, "");
}

export function buildFullPhone(dialCode: string, national: string): string {
  return dialCode + sanitizeNationalNumber(national);
}

export function phoneHint(dialCode: string, locale: StoreLocaleCode = "en"): string {
  return (
    PHONE_EXAMPLES[dialCode] ??
    translate(locale, "phone.hintDefault", { dialCode })
  );
}

export function validatePhone(
  dialCode: string,
  national: string,
  countries: PhoneCountry[],
  locale: StoreLocaleCode = "en",
): { valid: boolean; message: string; fullPhone: string } {
  const nationalDigits = sanitizeNationalNumber(national);
  const fullPhone = buildFullPhone(dialCode, nationalDigits);

  if (!nationalDigits) {
    return { valid: true, message: "", fullPhone };
  }

  const matching = findByDialCode(countries, dialCode);
  const pattern = matching?.validation_pattern?.trim();

  if (!matching || !pattern) {
    return { valid: true, message: "", fullPhone };
  }

  try {
    const regex = new RegExp(pattern);
    if (regex.test(fullPhone)) {
      return { valid: true, message: "", fullPhone };
    }
  } catch {
    return { valid: true, message: "", fullPhone };
  }

  const hint = phoneHint(dialCode, locale);
  const countryName = locale === "ar" ? matching.name_ar : matching.name_en;
  return {
    valid: false,
    message: translate(locale, "phone.invalid", {
      country: countryName,
      phone: fullPhone,
      hint,
    }),
    fullPhone,
  };
}

/** Split a stored full international number into dial code + national digits. */
export function parseFullPhone(
  fullPhone: string,
  countries: PhoneCountry[],
  fallbackDial = "+20",
): { dialCode: string; nationalNumber: string } {
  const trimmed = fullPhone.trim();
  if (!trimmed) {
    return { dialCode: fallbackDial, nationalNumber: "" };
  }

  const sorted = [...countries].sort((a, b) => b.dial_code.length - a.dial_code.length);
  for (const country of sorted) {
    if (trimmed.startsWith(country.dial_code)) {
      return {
        dialCode: country.dial_code,
        nationalNumber: trimmed.slice(country.dial_code.length).replace(/\D/g, ""),
      };
    }
  }

  return { dialCode: fallbackDial, nationalNumber: sanitizeNationalNumber(trimmed) };
}
