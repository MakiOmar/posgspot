import { useEffect, useMemo, useState } from "react";
import { StyleSheet, Text, View } from "react-native";
import { fetchPhoneCountries } from "../lib/api";
import type { PhoneCountry } from "../lib/types";
import { useApp } from "../contexts/AppContext";
import { useRtl } from "../lib/rtl";
import { FormTextInput } from "./FormTextInput";
import { SelectField } from "./SelectField";

type Props = {
  label: string;
  dialCode: string;
  nationalNumber: string;
  onChange: (next: { dialCode: string; nationalNumber: string; fullPhone: string }) => void;
  required?: boolean;
};

function buildFullPhone(dial: string, national: string): string {
  const digits = national.replace(/\D/g, "");
  const code = dial.startsWith("+") ? dial : `+${dial}`;
  return digits ? `${code}${digits}` : "";
}

/** Searchable dial-code select + national number field. */
export function PhoneInput({
  label,
  dialCode,
  nationalNumber,
  onChange,
}: Props) {
  const { t, locale } = useApp();
  const { textAlign, writingDirection, row } = useRtl();
  const [countries, setCountries] = useState<PhoneCountry[]>([]);

  useEffect(() => {
    void fetchPhoneCountries()
      .then(({ data }) => setCountries(Array.isArray(data) ? data : []))
      .catch(() =>
        setCountries([
          {
            name_en: "Egypt",
            name_ar: "مصر",
            dial_code: "+20",
            flag: "🇪🇬",
            country_code: "EG",
          },
        ]),
      );
  }, []);

  const options = useMemo(
    () =>
      countries.map((c) => {
        const name = locale === "ar" ? c.name_ar || c.name_en : c.name_en;
        return {
          value: c.dial_code,
          label: `${c.flag || ""} ${c.dial_code} · ${name}`.trim(),
        };
      }),
    [countries, locale],
  );

  const emit = (nextDial: string, nextNational: string) => {
    const national = nextNational.replace(/\D/g, "");
    onChange({
      dialCode: nextDial,
      nationalNumber: national,
      fullPhone: buildFullPhone(nextDial, national),
    });
  };

  return (
    <View style={styles.wrap}>
      <Text style={[styles.label, { textAlign, writingDirection }]}>{label}</Text>
      <View style={[styles.row, { flexDirection: row }]}>
        <View style={styles.dial}>
          <SelectField
            label=""
            value={dialCode}
            options={options}
            onChange={(value) => emit(value, nationalNumber)}
            placeholder={t("auth.dialCode")}
          />
        </View>
        <View style={styles.number}>
          <FormTextInput
            value={nationalNumber}
            onChangeText={(v) => emit(dialCode, v)}
            keyboardType="phone-pad"
            placeholder={t("auth.mobileNumber")}
          />
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginBottom: 12 },
  label: { fontWeight: "700", marginBottom: 6, color: "#222", fontSize: 14 },
  row: { gap: 8, alignItems: "flex-start" },
  dial: { width: 132 },
  number: { flex: 1 },
});
