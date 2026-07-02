import { component$, type QRL } from "@builder.io/qwik";
import type { PhoneCountry } from "~/lib/phone-validation";
import { SearchableSelect, type SelectOption } from "./searchable-select";

type Props = {
  id: string;
  countries: PhoneCountry[];
  value: string;
  required?: boolean;
  disabled?: boolean;
  onChange$: QRL<(dialCode: string) => void>;
};

export const DialCodeSelect = component$<Props>((props) => {
  const options: SelectOption[] = props.countries.map((c) => ({
    value: c.dial_code,
    label: `${c.name_en} ${c.dial_code}`,
    searchText: `${c.name_en} ${c.name_ar} ${c.dial_code}`,
    meta: c.flag,
  }));

  const selected = props.countries.find((c) => c.dial_code === props.value);

  return (
    <SearchableSelect
      id={props.id}
      options={options}
      value={props.value}
      displayLabel={selected ? `${selected.flag} ${selected.dial_code}` : undefined}
      placeholder="Dial code"
      required={props.required}
      disabled={props.disabled}
      onChange$={(dialCode) => props.onChange$(dialCode)}
    />
  );
});
