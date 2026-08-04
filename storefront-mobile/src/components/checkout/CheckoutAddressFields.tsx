import { StyleSheet, Text } from "react-native";
import { LabeledInput } from "../LabeledInput";
import { SelectField } from "../SelectField";
import { useApp } from "../../contexts/AppContext";
import type { BostaDistrict, GeoCountry, GeoState } from "../../lib/types";

type Props = {
  digitalMode: boolean;
  pickupMode: boolean;
  country: string;
  countries: GeoCountry[];
  useStateSelect: boolean;
  stateCode: string;
  stateText: string;
  states: GeoState[];
  bostaEnabled: boolean;
  districts: BostaDistrict[];
  districtId: string;
  city: string;
  address: string;
  onCountryChange: (code: string) => void;
  onStateCodeChange: (code: string) => void;
  onStateTextChange: (value: string) => void;
  onDistrictChange: (id: string, label: string) => void;
  onCityChange: (value: string) => void;
  onAddressChange: (value: string) => void;
};

/**
 * Shipping address / geo selects (country, state, district) + address lines.
 * Renders digital/pickup hints when address collection is not needed.
 */
export function CheckoutAddressFields({
  digitalMode,
  pickupMode,
  country,
  countries,
  useStateSelect,
  stateCode,
  stateText,
  states,
  bostaEnabled,
  districts,
  districtId,
  city,
  address,
  onCountryChange,
  onStateCodeChange,
  onStateTextChange,
  onDistrictChange,
  onCityChange,
  onAddressChange,
}: Props) {
  const { t } = useApp();

  if (digitalMode) {
    return <Text style={styles.hint}>{t("checkout.digitalOnly")}</Text>;
  }

  if (pickupMode) {
    return <Text style={styles.hint}>{t("checkout.pickupHint")}</Text>;
  }

  return (
    <>
      <Text style={styles.section}>{t("checkout.shippingAddress")}</Text>
      <SelectField
        label={t("checkout.country")}
        value={country}
        options={countries.map((c) => ({ value: c.code, label: c.name }))}
        onChange={(code) => onCountryChange(code)}
      />
      {useStateSelect ? (
        <SelectField
          label={t("checkout.state")}
          value={stateCode}
          options={states.map((s) => ({ value: s.code, label: s.name }))}
          onChange={(code) => onStateCodeChange(code)}
          placeholder={t("checkout.selectState")}
        />
      ) : (
        <LabeledInput
          label={t("checkout.state")}
          value={stateText}
          onChangeText={onStateTextChange}
          placeholder={t("checkout.statePlaceholder")}
        />
      )}
      {bostaEnabled && districts.length > 0 ? (
        <SelectField
          label={t("checkout.district")}
          value={districtId}
          options={districts.map((d) => ({
            value: d.id,
            label: d.label,
          }))}
          onChange={(id, opt) => onDistrictChange(id, opt.label)}
          placeholder={t("checkout.selectDistrict")}
        />
      ) : null}
      <LabeledInput
        label={t("checkout.city")}
        value={city}
        onChangeText={onCityChange}
      />
      <LabeledInput
        label={t("checkout.address")}
        value={address}
        onChangeText={onAddressChange}
        multiline
      />
    </>
  );
}

const styles = StyleSheet.create({
  section: { fontWeight: "800", fontSize: 16, marginTop: 8, marginBottom: 10 },
  hint: { color: "#666", marginBottom: 8, lineHeight: 18 },
});
