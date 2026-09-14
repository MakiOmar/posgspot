export type DialCountry = { dial_code: string };

const FALLBACK_DIAL = "+20";

/**
 * Split a stored mobile into a known dial code + national digits.
 * Local Egypt numbers like 01026795795 become +20 / 1026795795.
 */
export function parseStoredMobile(
  fullPhone: string | undefined,
  countries: DialCountry[] = [],
  fallbackDial = FALLBACK_DIAL,
): { dialCode: string; nationalNumber: string } {
  const trimmed = (fullPhone || "").trim();
  if (!trimmed) {
    return { dialCode: fallbackDial, nationalNumber: "" };
  }

  const known = countries.length > 0 ? countries : [{ dial_code: fallbackDial }];
  const sorted = [...known].sort((a, b) => b.dial_code.length - a.dial_code.length);

  if (trimmed.startsWith("+")) {
    for (const country of sorted) {
      if (country.dial_code && trimmed.startsWith(country.dial_code)) {
        return {
          dialCode: country.dial_code,
          nationalNumber: trimmed.slice(country.dial_code.length).replace(/\D/g, ""),
        };
      }
    }
    if (trimmed.startsWith(fallbackDial)) {
      return {
        dialCode: fallbackDial,
        nationalNumber: trimmed.slice(fallbackDial.length).replace(/\D/g, ""),
      };
    }
    return {
      dialCode: fallbackDial,
      nationalNumber: trimmed.replace(/\D/g, ""),
    };
  }

  let digits = trimmed.replace(/\D/g, "");
  if (digits.startsWith("0")) {
    digits = digits.slice(1);
  }
  return { dialCode: fallbackDial, nationalNumber: digits };
}
